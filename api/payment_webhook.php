<?php
/**
 * TasadorIA — api/payment_webhook.php
 * Recibe notificaciones de MercadoPago y Stripe, activa el plan del usuario.
 *
 * URLs a registrar:
 *   MP:     https://tudominio.com/tasador/api/payment_webhook.php?gw=mp
 *   Stripe: https://tudominio.com/tasador/api/payment_webhook.php?gw=stripe
 */

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
try {
    $pdo = new PDO("mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch (\Throwable $e) { http_response_code(500); exit; }

$gw   = $_GET['gw'] ?? '';
$body = file_get_contents('php://input');

function activatePlan(PDO $pdo, string $email, string $plan, string $gateway, string $ref, float $amount, string $currency): void {
    $tierMap = ['pro'=>'pro','agency'=>'agency','enterprise'=>'enterprise'];
    $tier    = $tierMap[$plan] ?? 'pro';
    $stmt    = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user    = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $pass = bin2hex(random_bytes(8));
        $pdo->prepare("INSERT INTO users (email,password_hash,tier,tasaciones_limit) VALUES (?,?,'free',5)")
            ->execute([$email, password_hash($pass, PASSWORD_BCRYPT)]);
        $userId = (int)$pdo->lastInsertId();
    } else { $userId = (int)$user['id']; }
    $pdo->prepare("UPDATE users SET tier=?, tasaciones_limit=NULL, updated_at=NOW() WHERE id=?")->execute([$tier, $userId]);
    $pdo->prepare("INSERT INTO subscriptions (user_id,plan,status,currency,amount,stripe_subscription_id,mp_subscription_id,current_period_start,current_period_end)
                   VALUES (?,?,  'active',?,?,?,?,NOW(),DATE_ADD(NOW(), INTERVAL 1 MONTH))")
        ->execute([$userId, $plan.'_monthly', $currency, $amount, $gateway==='stripe'?$ref:null, $gateway==='mercadopago'?$ref:null]);
    $pdo->prepare("INSERT INTO payments (user_id,gateway,gateway_ref,amount,currency,status,description) VALUES (?,?,?,?,?,'approved',?)")
        ->execute([$userId,$gateway,$ref,$amount,$currency,"Plan {$plan} activado"]);
}

if ($gw === 'mp') {
    $data = json_decode($body, true) ?? [];
    if (($data['type']??'') === 'payment') {
        $mpToken = $cfg['mercadopago']['access_token'] ?? '';
        $ch = curl_init("https://api.mercadopago.com/v1/payments/{$data['data']['id']}");
        curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$mpToken],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
        $payment = json_decode(curl_exec($ch), true); curl_close($ch);
        if (($payment['status']??'') === 'approved') {
            $ref  = $payment['external_reference'] ?? '';
            $plan = explode('_',$ref)[0] ?? 'pro';
            activatePlan($pdo, $payment['payer']['email']??'', $plan, 'mercadopago', (string)$payment['id'], (float)($payment['transaction_amount']??0), 'ARS');
        }
    }
    http_response_code(200); exit;
}

if ($gw === 'stripe') {
    $event = json_decode($body, true);
    $type  = $event['type'] ?? '';
    if (in_array($type, ['checkout.session.completed','invoice.payment_succeeded'])) {
        $obj   = $event['data']['object'];
        $email = $obj['customer_email'] ?? $obj['customer_details']['email'] ?? '';
        $plan  = $obj['metadata']['plan'] ?? 'pro';
        $ref   = $obj['subscription'] ?? $obj['id'] ?? '';
        $amt   = isset($obj['amount_total']) ? $obj['amount_total']/100 : 0;
        if ($email) activatePlan($pdo, $email, $plan, 'stripe', $ref, $amt, 'USD');
    }
    http_response_code(200); exit;
}
http_response_code(200);
