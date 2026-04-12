<?php
/**
 * TasadorIA — api/payment_webhook.php
 * Recibe notificaciones IPN/webhook de Mercado Pago y Stripe.
 * Cuando el pago es aprobado: genera token de descarga y envía email al comprador.
 *
 * MP:     POST ?gateway=mp  { type, data.id }
 * Stripe: POST ?gateway=stripe  (con Stripe-Signature header)
 */
header('Content-Type: application/json; charset=utf-8');

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];

function log_wh(string $msg): void {
    error_log('[TasadorIA Webhook] ' . $msg);
}

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d);
    exit;
}

// ── BD ────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host='.($cfg['db']['host']??'localhost').';dbname='.($cfg['db']['name']??'').';charset=utf8mb4',
        $cfg['db']['user']??'', $cfg['db']['pass']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch (\Throwable $e) {
    log_wh('DB error: ' . $e->getMessage());
    out(['ok'=>false], 500);
}

$gateway = $_GET['gateway'] ?? 'mp';
$rawBody = file_get_contents('php://input');

// ── MERCADO PAGO ──────────────────────────────────────────────
if ($gateway === 'mp') {
    $data   = json_decode($rawBody, true) ?? [];
    $type   = $data['type']           ?? $_GET['type'] ?? '';
    $dataId = $data['data']['id']     ?? $_GET['data_id'] ?? '';

    if ($type !== 'payment' || !$dataId) {
        out(['ok'=>true,'skipped'=>true]);
    }

    $mpToken = $cfg['mercadopago']['access_token'] ?? '';
    if (!$mpToken) out(['ok'=>false,'error'=>'MP not configured'], 500);

    // Consultar el pago en MP
    $ch = curl_init("https://api.mercadopago.com/v1/payments/{$dataId}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $mpToken],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $payment = json_decode($resp, true);
    if (!$payment) out(['ok'=>false,'error'=>'No payment data'], 400);

    $status      = $payment['status']             ?? '';
    $extRef      = $payment['external_reference'] ?? '';
    $payerEmail  = $payment['payer']['email']      ?? '';
    $payerName   = trim(($payment['payer']['first_name']??'').' '.($payment['payer']['last_name']??''));
    $amount      = floatval($payment['transaction_amount'] ?? 0);

    log_wh("MP payment {$dataId} status={$status} ref={$extRef}");

    if ($status === 'approved' && $extRef) {
        approveOrder($pdo, $cfg, $extRef, (string)$dataId, $payerEmail ?: null, $payerName ?: null);
    } elseif (in_array($status, ['rejected','cancelled','refunded'])) {
        $pdo->prepare("UPDATE tasador_purchases SET status=? WHERE order_id=?")
            ->execute([$status, $extRef]);
    }

    out(['ok'=>true]);
}

// ── STRIPE ────────────────────────────────────────────────────
if ($gateway === 'stripe') {
    $stripeKey    = $cfg['stripe']['secret_key']      ?? '';
    $stripeSecret = $cfg['stripe']['webhook_secret']  ?? '';

    // Verificar firma del webhook
    if ($stripeSecret) {
        $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        if (!verifyStripeSignature($rawBody, $sig, $stripeSecret)) {
            log_wh('Stripe signature invalid');
            out(['ok'=>false,'error'=>'Invalid signature'], 400);
        }
    }

    $event = json_decode($rawBody, true);
    $type  = $event['type'] ?? '';

    if ($type === 'checkout.session.completed') {
        $session    = $event['data']['object'];
        $internalRef = $session['metadata']['internal_ref'] ?? '';
        $email       = $session['customer_email']           ?? $session['metadata']['buyer_email'] ?? '';
        $stripeId    = $session['id']                       ?? '';
        $status      = $session['payment_status']           ?? '';

        log_wh("Stripe session {$stripeId} status={$status} ref={$internalRef}");

        if ($status === 'paid' && $internalRef) {
            approveOrder($pdo, $cfg, $internalRef, $stripeId, $email ?: null, null);
        }
    }

    out(['ok'=>true]);
}

out(['ok'=>false,'error'=>'Unknown gateway'], 400);

// ── Aprobar orden y generar token ─────────────────────────────
function approveOrder(PDO $pdo, array $cfg, string $ref, string $gatewayId, ?string $email, ?string $name): void {
    // Verificar que no esté ya aprobada
    $order = $pdo->prepare("SELECT * FROM tasador_purchases WHERE order_id=? LIMIT 1");
    $order->execute([$ref]);
    $row = $order->fetch();

    if (!$row) {
        log_wh("Order not found: $ref");
        return;
    }
    if ($row['status'] === 'approved') {
        log_wh("Order already approved: $ref");
        return;
    }

    // Generar token único de descarga (válido 72 hs)
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+72 hours'));

    $pdo->prepare("UPDATE tasador_purchases
        SET status='approved', download_token=?, download_expires=?,
            email=COALESCE(NULLIF(email,''), ?),
            buyer_name=COALESCE(NULLIF(buyer_name,''), ?)
        WHERE order_id=?")
        ->execute([$token, $expires, $email ?? $row['email'], $name, $ref]);

    $finalEmail = $email ?: $row['email'];
    $siteUrl    = rtrim($cfg['site_url'] ?? 'https://anperprimo.com/tasador', '/');
    $downloadUrl = "$siteUrl/api/download_plugin.php?token=$token";

    log_wh("Approved order $ref → token generated, email: $finalEmail");

    // Enviar email con link de descarga
    if ($finalEmail) {
        sendDownloadEmail($cfg, $finalEmail, $row['plugin_name'], $downloadUrl, $expires);
    }
}

// ── Email de descarga ─────────────────────────────────────────
function sendDownloadEmail(array $cfg, string $email, string $pluginName, string $url, string $expires): void {
    $subject = "Tu plugin está listo: $pluginName — TasadorIA";
    $expDate = date('d/m/Y H:i', strtotime($expires));

    $html = <<<HTML
<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;background:#f5f5f5;padding:32px">
<div style="max-width:520px;margin:auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08)">
  <div style="background:#1a1a1a;padding:24px;text-align:center">
    <h1 style="color:#c9a84c;font-size:20px;margin:0">🏗 TasadorIA</h1>
    <p style="color:#888;font-size:12px;margin:6px 0 0">Sistema de valuación inmobiliaria</p>
  </div>
  <div style="padding:28px">
    <h2 style="font-size:18px;color:#1a1a1a;margin-bottom:8px">¡Tu compra fue aprobada!</h2>
    <p style="color:#555;font-size:14px;line-height:1.6;margin-bottom:20px">
      Plugin adquirido: <strong>$pluginName</strong><br>
      Usá el botón de abajo para descargar tu plugin. El link es válido hasta el <strong>$expDate</strong>.
    </p>
    <div style="text-align:center;margin:28px 0">
      <a href="$url" style="background:#c9a84c;color:#000;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;font-size:15px;display:inline-block">
        📦 Descargar Plugin
      </a>
    </div>
    <div style="background:#f9f9f9;border-radius:8px;padding:16px;font-size:12px;color:#777;margin-top:20px">
      <strong>¿Cómo instalarlo?</strong><br>
      1. Descargá el ZIP<br>
      2. Andá a <strong>Admin → Plugins</strong><br>
      3. Arrastrá el ZIP a la zona de upload<br>
      4. Activalo con un clic
    </div>
  </div>
  <div style="background:#f0f0f0;padding:16px;text-align:center;font-size:11px;color:#aaa">
    TasadorIA · anperprimo.com · soporte: exeandino@gmail.com
  </div>
</div>
</body></html>
HTML;

    $smtpCfg = $cfg['smtp'] ?? [];
    if (!empty($smtpCfg['host'])) {
        // Usar SMTP (require send_email API internamente)
        $apiUrl = rtrim($cfg['site_url'] ?? '', '/') . '/api/send_email.php';
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS     => json_encode(['to'=>$email,'subject'=>$subject,'html'=>$html,'internal'=>true]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 8,
        ]);
        curl_exec($ch); curl_close($ch);
    } else {
        // Fallback: mail() nativo
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: TasadorIA <noreply@anperprimo.com>";
        mail($email, $subject, $html, $headers);
    }

    log_wh("Download email sent to $email");
}

// ── Verificar firma Stripe ────────────────────────────────────
function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool {
    $parts    = explode(',', $sigHeader);
    $ts       = null;
    $v1       = null;
    foreach ($parts as $part) {
        [$k, $v] = explode('=', $part, 2) + ['',''];
        if ($k === 't') $ts = $v;
        if ($k === 'v1') $v1 = $v;
    }
    if (!$ts || !$v1) return false;
    $expected = hash_hmac('sha256', "$ts.$payload", $secret);
    return hash_equals($expected, $v1);
}
