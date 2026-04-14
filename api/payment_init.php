<?php
/**
 * TasadorIA — api/payment_init.php
 * Inicia el checkout de pago con MercadoPago o Stripe.
 *
 * GET params: plan, email, gateway (mercadopago|stripe), currency (USD|ARS)
 *
 * MercadoPago: redirige a checkout MP (Checkout Pro)
 * Stripe: redirige a Stripe Checkout Session
 *
 * Configurar en settings.php:
 *   'mercadopago' => ['access_token' => 'APP_USR-...', 'public_key' => 'APP_USR-...'],
 *   'stripe'      => ['secret_key'   => 'sk_live_...', 'public_key'  => 'pk_live_...'],
 */

$cfg    = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
$plan   = $_GET['plan']     ?? 'pro';
$email  = trim($_GET['email']    ?? '');
$gw     = $_GET['gateway']  ?? 'mercadopago';
$curr   = $_GET['currency'] ?? 'USD';
$appUrl = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');

// ── Tabla de planes ───────────────────────────────────────────
$plans = [
    'pro'    => ['name'=>'TasadorIA Pro',    'price_usd'=>9.00,  'price_ars'=>12600, 'desc'=>'Tasaciones ilimitadas + historial + PDF + multi-IA'],
    'agency' => ['name'=>'TasadorIA Agencia','price_usd'=>29.00, 'price_ars'=>40600, 'desc'=>'Todo Pro + 5 usuarios + BIM + CRM Export'],
];

if (!isset($plans[$plan])) {
    http_response_code(400); echo "Plan inválido"; exit;
}
$p = $plans[$plan];

// ── MercadoPago ───────────────────────────────────────────────
if ($gw === 'mercadopago') {
    $mpToken = $cfg['mercadopago']['access_token'] ?? '';
    if (!$mpToken || str_starts_with($mpToken, 'APP_USR-xxx')) {
        // Token no configurado — redirigir a instrucciones
        header("Location: {$appUrl}/planes.php?error=mp_not_configured");
        exit;
    }

    $amount = $curr === 'ARS' ? $p['price_ars'] : round($p['price_usd'] * ($cfg['ars_usd_rate'] ?? 1400));

    $body = [
        'items' => [[
            'id'          => $plan,
            'title'       => $p['name'],
            'description' => $p['desc'],
            'quantity'    => 1,
            'currency_id' => 'ARS',
            'unit_price'  => (float)$amount,
        ]],
        'payer' => ['email' => $email],
        'back_urls' => [
            'success' => "{$appUrl}/api/payment_callback.php?gw=mp&status=success&plan={$plan}",
            'failure' => "{$appUrl}/planes.php?error=mp_failure",
            'pending' => "{$appUrl}/planes.php?msg=mp_pending",
        ],
        'auto_return'          => 'approved',
        'external_reference'   => $plan . '_' . time() . '_' . substr(md5($email), 0, 6),
        'statement_descriptor' => 'TASADORIA',
        'notification_url'     => "{$appUrl}/api/payment_webhook.php?gw=mp",
    ];

    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$mpToken, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res  = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true);

    if (!empty($data['init_point'])) {
        header('Location: ' . $data['init_point']); exit;
    }
    // Error MP
    header("Location: {$appUrl}/planes.php?error=mp_error&msg=".urlencode($data['message'] ?? 'Error'));
    exit;
}

// ── Stripe ────────────────────────────────────────────────────
if ($gw === 'stripe') {
    $stripeKey = $cfg['stripe']['secret_key'] ?? '';
    if (!$stripeKey || str_starts_with($stripeKey, 'sk_live_xxx') || str_starts_with($stripeKey, 'sk_test_xxx')) {
        header("Location: {$appUrl}/planes.php?error=stripe_not_configured");
        exit;
    }

    $amount = (int)round($p['price_usd'] * 100); // Stripe trabaja en centavos

    $body = http_build_query([
        'mode'                                  => 'subscription',
        'payment_method_types[]'                => 'card',
        'customer_email'                        => $email,
        'success_url'                           => "{$appUrl}/api/payment_callback.php?gw=stripe&status=success&plan={$plan}&session_id={CHECKOUT_SESSION_ID}",
        'cancel_url'                            => "{$appUrl}/planes.php?cancelled=1",
        'line_items[0][price_data][currency]'   => 'usd',
        'line_items[0][price_data][product_data][name]' => $p['name'],
        'line_items[0][price_data][recurring][interval]' => 'month',
        'line_items[0][price_data][unit_amount]'=> $amount,
        'line_items[0][quantity]'               => 1,
        'metadata[plan]'                        => $plan,
        'metadata[email]'                       => $email,
    ]);

    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $stripeKey . ':',
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res  = curl_exec($ch); curl_close($ch);
    $data = json_decode($res, true);

    if (!empty($data['url'])) {
        header('Location: ' . $data['url']); exit;
    }
    header("Location: {$appUrl}/planes.php?error=stripe_error&msg=".urlencode($data['error']['message'] ?? 'Error'));
    exit;
}

header("Location: {$appUrl}/planes.php?error=unknown_gateway");
exit;
