<?php
/**
 * TasadorIA — api/checkout.php
 * Crea una sesión de pago en Mercado Pago (ARS) o Stripe (USD).
 *
 * POST { slug, gateway: 'mercadopago'|'stripe', email, buyer_name? }
 * → { success, checkout_url }  (redirect al gateway)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg   = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ── CHECK STATUS (polling from payment_success.php) ──────────
if (($_GET['action'] ?? '') === 'check') {
    $ref = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['ref'] ?? '');
    if ($ref) {
        try {
            $pdo2 = new PDO(
                'mysql:host='.($cfg['db']['host']??'localhost').';dbname='.($cfg['db']['name']??'').';charset=utf8mb4',
                $cfg['db']['user']??'', $cfg['db']['pass']??'',
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
            );
            $s = $pdo2->prepare("SELECT status FROM tasador_purchases WHERE order_id=? LIMIT 1");
            $s->execute([$ref]);
            $r = $s->fetch();
            out(['status' => $r['status'] ?? 'not_found']);
        } catch (\Throwable $e) {
            out(['status' => 'error']);
        }
    }
    out(['status' => 'not_found']);
}

$slug       = preg_replace('/[^a-z0-9\-_]/', '', strtolower($input['slug']     ?? ''));
$gateway    = in_array($input['gateway'] ?? '', ['mercadopago','stripe']) ? $input['gateway'] : 'mercadopago';
$email      = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$buyerName  = trim($input['buyer_name'] ?? '');
$lang       = $input['lang'] ?? 'es';

if (!$slug || !$email) {
    out(['success'=>false,'error'=>'Slug y email requeridos']);
}

// ── BD ────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host='.($cfg['db']['host']??'localhost').';dbname='.($cfg['db']['name']??'').';charset=utf8mb4',
        $cfg['db']['user']??'', $cfg['db']['pass']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (\Throwable $e) {
    out(['success'=>false,'error'=>'DB: '.$e->getMessage()]);
}

// Obtener precio
$product = $pdo->prepare("SELECT * FROM tasador_plugin_prices WHERE slug=? AND active=1 LIMIT 1");
$product->execute([$slug]);
$prod = $product->fetch();
if (!$prod) out(['success'=>false,'error'=>'Producto no encontrado o no disponible']);

$priceUsd = floatval($prod['price_usd']);
$usdRate  = floatval($cfg['ars_usd_rate'] ?? $cfg['usd_rate'] ?? 1400);
$priceArs = round($priceUsd * $usdRate);

$siteUrl = rtrim($cfg['site_url'] ?? 'https://anperprimo.com/tasador', '/');

// ── MERCADO PAGO ──────────────────────────────────────────────
if ($gateway === 'mercadopago') {
    $mpToken = $cfg['mercadopago']['access_token'] ?? '';
    if (!$mpToken) out(['success'=>false,'error'=>'Mercado Pago no configurado. Agregá access_token en settings.php']);

    $externalRef = 'tasador_' . $slug . '_' . time() . '_' . bin2hex(random_bytes(4));

    $payload = [
        'items' => [[
            'title'       => $prod['name'] . ' — TasadorIA Plugin',
            'description' => 'Módulo descargable para TasadorIA. Entrega inmediata por email.',
            'quantity'    => 1,
            'currency_id' => 'ARS',
            'unit_price'  => $priceArs,
        ]],
        'payer' => [
            'email' => $email,
            'name'  => $buyerName ?: null,
        ],
        'back_urls' => [
            'success' => "$siteUrl/api/payment_success.php?gateway=mp&ref=$externalRef",
            'failure' => "$siteUrl/admin_plugins.php?payment=failed",
            'pending' => "$siteUrl/admin_plugins.php?payment=pending",
        ],
        'auto_return'      => 'approved',
        'notification_url' => "$siteUrl/api/payment_webhook.php?gateway=mp",
        'external_reference' => $externalRef,
        'statement_descriptor' => 'TasadorIA',
        'metadata' => [
            'plugin_slug' => $slug,
            'buyer_email' => $email,
            'lang'        => $lang,
        ],
    ];

    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $mpToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 201) {
        $err = json_decode($resp, true);
        out(['success'=>false,'error'=>'MP: '.($err['message']??'Error al crear preferencia')]);
    }

    $mp = json_decode($resp, true);

    // Guardar orden pending en BD
    $pdo->prepare("INSERT INTO tasador_purchases
        (order_id, gateway, plugin_slug, plugin_name, amount, currency, amount_usd, email, buyer_name, status, metadata)
        VALUES (?,?,?,?,?,?,?,?,?,'pending',?)")
        ->execute([
            $externalRef, 'mercadopago', $slug, $prod['name'],
            $priceArs, 'ARS', $priceUsd,
            $email, $buyerName ?: null,
            json_encode(['mp_preference_id' => $mp['id']]),
        ]);

    // Usar init_point en producción, sandbox_init_point para tests
    $isTest     = ($cfg['mercadopago']['test_mode'] ?? false);
    $checkoutUrl = $isTest ? ($mp['sandbox_init_point'] ?? $mp['init_point']) : $mp['init_point'];

    out(['success'=>true,'checkout_url'=>$checkoutUrl,'gateway'=>'mercadopago','amount_ars'=>$priceArs,'amount_usd'=>$priceUsd]);
}

// ── STRIPE ────────────────────────────────────────────────────
if ($gateway === 'stripe') {
    $stripeKey = $cfg['stripe']['secret_key'] ?? '';
    if (!$stripeKey) out(['success'=>false,'error'=>'Stripe no configurado. Agregá secret_key en settings.php']);

    $sessionId = 'tasador_' . $slug . '_' . time() . '_' . bin2hex(random_bytes(4));

    $payload = http_build_query([
        'payment_method_types[]' => 'card',
        'line_items[0][price_data][currency]'                => 'usd',
        'line_items[0][price_data][product_data][name]'      => $prod['name'] . ' — TasadorIA Plugin',
        'line_items[0][price_data][product_data][description]' => 'Módulo descargable para TasadorIA. Entrega inmediata.',
        'line_items[0][price_data][unit_amount]'             => (int)($priceUsd * 100), // centavos
        'line_items[0][quantity]'                            => 1,
        'mode'                   => 'payment',
        'customer_email'         => $email,
        'metadata[plugin_slug]'  => $slug,
        'metadata[buyer_email]'  => $email,
        'metadata[internal_ref]' => $sessionId,
        'metadata[lang]'         => $lang,
        'success_url'            => "$siteUrl/api/payment_success.php?gateway=stripe&session_id={CHECKOUT_SESSION_ID}&ref=$sessionId",
        'cancel_url'             => "$siteUrl/admin_plugins.php?payment=cancelled",
    ]);

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_USERPWD        => $stripeKey . ':',
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        $err = json_decode($resp, true);
        out(['success'=>false,'error'=>'Stripe: '.($err['error']['message']??'Error al crear sesión')]);
    }

    $session = json_decode($resp, true);

    // Guardar orden pending
    $pdo->prepare("INSERT INTO tasador_purchases
        (order_id, gateway, plugin_slug, plugin_name, amount, currency, amount_usd, email, buyer_name, status, metadata)
        VALUES (?,?,?,?,?,?,?,?,?,'pending',?)")
        ->execute([
            $sessionId, 'stripe', $slug, $prod['name'],
            $priceUsd, 'USD', $priceUsd,
            $email, $buyerName ?: null,
            json_encode(['stripe_session_id' => $session['id']]),
        ]);

    out(['success'=>true,'checkout_url'=>$session['url'],'gateway'=>'stripe','amount_usd'=>$priceUsd]);
}

out(['success'=>false,'error'=>'Gateway no válido'], 400);
