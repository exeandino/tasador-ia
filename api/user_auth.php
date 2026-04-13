<?php
/**
 * TasadorIA — api/user_auth.php
 * Sistema de autenticación de usuarios
 *
 * Acciones:
 *   register          — crear cuenta (manual)
 *   auto_register     — crear cuenta automática post-tasación (genera password)
 *   login             — iniciar sesión
 *   logout            — cerrar sesión
 *   me                — datos del usuario autenticado
 *   history           — historial de tasaciones del usuario
 *   forgot_password   — enviar email de reset
 *   reset_password    — cambiar password con token
 *   verify_email      — verificar email con token
 *   check_limit       — consultar tasaciones disponibles (sin auth)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function out(array $d, int $c = 200): void {
    http_response_code($c);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];

try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (\Throwable $e) {
    out(['success' => false, 'error' => 'DB error'], 500);
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

// ── Helper: usuario autenticado por header Authorization: Bearer <token> ──────
function getAuthUser(PDO $pdo): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) return null;
    $token = trim($m[1]);
    $hash  = hash('sha256', $token);
    $stmt  = $pdo->prepare("
        SELECT u.* FROM user_sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.session_token = ? AND s.expires_at > NOW() AND u.status = 'active'
    ");
    $stmt->execute([$hash]);
    return $stmt->fetch() ?: null;
}

// ── Helper: crear sesión ──────────────────────────────────────
function createSession(PDO $pdo, int $userId): string {
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $pdo->prepare("
        INSERT INTO user_sessions (user_id, session_token, ip, user_agent, expires_at)
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
    ")->execute([
        $userId, $hash,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
    ]);
    return $token;  // devolver token en claro
}

// ── Helper: enviar email ──────────────────────────────────────
function sendAuthEmail(array $cfg, string $to, string $subject, string $body): bool {
    $smtp = $cfg['smtp'] ?? [];
    if (empty($smtp['host'])) {
        return mail($to, $subject, $body, "From: {$cfg['agency_name']} <{$smtp['from'] ?? 'noreply@example.com'}>\r\nContent-Type: text/html; charset=UTF-8");
    }
    // PHPMailer si está disponible
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['user'];
            $mail->Password   = $smtp['pass'];
            $mail->SMTPSecure = $smtp['secure'] ?? 'tls';
            $mail->Port       = $smtp['port'] ?? 587;
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($smtp['from'], $smtp['from_name'] ?? 'TasadorIA');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (\Throwable $e) { return false; }
    }
    return @mail($to, $subject, $body,
        "From: {$smtp['from_name']} <{$smtp['from']}>\r\nContent-Type: text/html; charset=UTF-8");
}

// ── Helper: template email ────────────────────────────────────
function emailTemplate(string $title, string $content, array $cfg): string {
    $brand = $cfg['brand_name'] ?? 'TasadorIA';
    $color = $cfg['primary_color'] ?? '#c9a84c';
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:system-ui,sans-serif;background:#f4f4f4;margin:0;padding:20px">
<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden">
  <div style="background:{$color};padding:24px;text-align:center">
    <h1 style="color:#000;margin:0;font-size:22px">🏠 {$brand}</h1>
  </div>
  <div style="padding:28px 32px;color:#333;line-height:1.6">
    <h2 style="margin-top:0">{$title}</h2>
    {$content}
  </div>
  <div style="padding:16px;background:#f9f9f9;text-align:center;font-size:12px;color:#999">
    {$brand} · Este email fue generado automáticamente
  </div>
</div>
</body></html>
HTML;
}

// ════════════════════════════════════════════════════════════
// ACCIONES
// ════════════════════════════════════════════════════════════

// ── CHECK_LIMIT — sin auth, por email o IP ────────────────────
if ($action === 'check_limit') {
    $email = $input['email'] ?? $_GET['email'] ?? '';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';

    if ($email) {
        $u = $pdo->prepare("SELECT id, tasaciones_count, tasaciones_limit, tier FROM users WHERE email=?");
        $u->execute([$email]);
        $user = $u->fetch();
        if ($user) {
            $limit  = $user['tasaciones_limit'];
            $used   = $user['tasaciones_count'];
            $tier   = $user['tier'];
            $canTasar = ($limit === null || $used < $limit) || $tier !== 'free';
            out(['success'=>true,'can_tasar'=>$canTasar,'used'=>$used,'limit'=>$limit,'tier'=>$tier]);
        }
    }
    // Por IP (usuario anónimo)
    $count = $pdo->prepare("SELECT COUNT(*) FROM tasaciones WHERE ip_address=? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $count->execute([$ip]);
    $used = (int)$count->fetchColumn();
    out(['success'=>true,'can_tasar'=>($used < 5),'used'=>$used,'limit'=>5,'tier'=>'anonymous']);
}

// ── AUTO_REGISTER — llamado desde valuar.php post-tasación ────
if ($action === 'auto_register') {
    $email = trim(strtolower($input['email'] ?? ''));
    $name  = trim($input['name']  ?? '');
    $code  = $input['tasacion_code'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        out(['success'=>false,'error'=>'Email inválido']);
    }

    // ¿Ya existe?
    $existing = $pdo->prepare("SELECT id, tier, tasaciones_count, tasaciones_limit FROM users WHERE email=?");
    $existing->execute([$email]);
    $user = $existing->fetch();

    if ($user) {
        // Incrementar contador
        $pdo->prepare("UPDATE users SET tasaciones_count=tasaciones_count+1, updated_at=NOW() WHERE id=?")->execute([$user['id']]);
        // Vincular tasación
        if ($code) $pdo->prepare("UPDATE tasaciones SET user_id=? WHERE code=?")->execute([$user['id'], $code]);

        $limit  = $user['tasaciones_limit'];
        $newCount = $user['tasaciones_count'] + 1;
        $needsPay = ($user['tier'] === 'free' && $limit !== null && $newCount >= $limit);

        $token = createSession($pdo, $user['id']);
        out(['success'=>true,'is_new'=>false,'user_id'=>$user['id'],'token'=>$token,
             'needs_payment'=>$needsPay,'tasaciones_used'=>$newCount,'tasaciones_limit'=>$limit]);
    }

    // Crear usuario nuevo
    $password  = strtoupper(substr(md5(uniqid()), 0, 3)) . rand(10,99) . strtolower(substr(md5(uniqid()), 0, 3));
    $hash      = password_hash($password, PASSWORD_BCRYPT);
    $verifyTok = bin2hex(random_bytes(16));

    $pdo->prepare("
        INSERT INTO users (email, password_hash, name, role, tier, tasaciones_count, tasaciones_limit, email_verify_token, created_at)
        VALUES (?, ?, ?, 'user', 'free', 1, 5, ?, NOW())
    ")->execute([$email, $hash, $name ?: null, $verifyTok]);
    $userId = (int)$pdo->lastInsertId();

    // Vincular tasación
    if ($code) $pdo->prepare("UPDATE tasaciones SET user_id=? WHERE code=?")->execute([$userId, $code]);

    // Enviar email con credenciales
    $appUrl = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');
    $verifyUrl = "{$appUrl}/api/user_auth.php?action=verify_email&token={$verifyTok}";
    $content = "
        <p>¡Tu tasación quedó guardada! Creamos tu cuenta para que puedas consultarla cuando quieras.</p>
        <div style='background:#f9f4e8;border:1px solid #e8d87a;border-radius:8px;padding:16px;margin:16px 0'>
            <strong>🔑 Tus credenciales de acceso:</strong><br><br>
            <b>Email:</b> {$email}<br>
            <b>Contraseña temporal:</b> <code style='background:#fff;padding:2px 6px;border-radius:4px;font-size:16px'>{$password}</code>
        </div>
        <p>Tenés <strong>5 tasaciones gratuitas</strong>. Esta fue la número 1.</p>
        <div style='text-align:center;margin:24px 0'>
            <a href='{$appUrl}' style='background:#c9a84c;color:#000;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold'>Ver mi tasación →</a>
        </div>
        <p style='font-size:12px;color:#999'><a href='{$verifyUrl}'>Verificar email</a> · Podés cambiar tu contraseña desde tu perfil.</p>
    ";

    sendAuthEmail($cfg, $email, "🏠 Tu tasación fue guardada · TasadorIA",
        emailTemplate("¡Bienvenido a TasadorIA!", $content, $cfg));

    $token = createSession($pdo, $userId);
    out(['success'=>true,'is_new'=>true,'user_id'=>$userId,'token'=>$token,
         'password_sent'=>true,'tasaciones_used'=>1,'tasaciones_limit'=>5,'needs_payment'=>false]);
}

// ── REGISTER — manual ─────────────────────────────────────────
if ($action === 'register') {
    $email = trim(strtolower($input['email'] ?? ''));
    $pass  = $input['password'] ?? '';
    $name  = trim($input['name'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['success'=>false,'error'=>'Email inválido']);
    if (strlen($pass) < 6) out(['success'=>false,'error'=>'Contraseña mínimo 6 caracteres']);

    $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $check->execute([$email]);
    if ($check->fetch()) out(['success'=>false,'error'=>'Email ya registrado']);

    $verifyTok = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO users (email,password_hash,name,email_verify_token) VALUES (?,?,?,?)")
        ->execute([$email, password_hash($pass, PASSWORD_BCRYPT), $name ?: null, $verifyTok]);
    $userId = (int)$pdo->lastInsertId();

    $appUrl = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');
    $verifyUrl = "{$appUrl}/api/user_auth.php?action=verify_email&token={$verifyTok}";
    $content = "<p>Confirmá tu email para activar tu cuenta:</p>
        <div style='text-align:center;margin:24px 0'>
          <a href='{$verifyUrl}' style='background:#c9a84c;color:#000;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold'>Verificar email →</a>
        </div>";
    sendAuthEmail($cfg, $email, "Verificá tu cuenta · TasadorIA", emailTemplate("Verificación de email", $content, $cfg));

    $token = createSession($pdo, $userId);
    out(['success'=>true,'user_id'=>$userId,'token'=>$token,'message'=>'Cuenta creada. Revisá tu email.']);
}

// ── LOGIN ─────────────────────────────────────────────────────
if ($action === 'login') {
    $email = trim(strtolower($input['email'] ?? ''));
    $pass  = $input['password'] ?? '';
    if (!$email || !$pass) out(['success'=>false,'error'=>'Email y contraseña requeridos']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND status='active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        out(['success'=>false,'error'=>'Credenciales incorrectas'], 401);
    }

    $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
    $token = createSession($pdo, (int)$user['id']);

    out(['success'=>true,'token'=>$token,'user'=>[
        'id'    => $user['id'],
        'email' => $user['email'],
        'name'  => $user['name'],
        'tier'  => $user['tier'],
        'role'  => $user['role'],
        'tasaciones_used'  => $user['tasaciones_count'],
        'tasaciones_limit' => $user['tasaciones_limit'],
    ]]);
}

// ── LOGOUT ────────────────────────────────────────────────────
if ($action === 'logout') {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        $hash = hash('sha256', trim($m[1]));
        $pdo->prepare("DELETE FROM user_sessions WHERE session_token=?")->execute([$hash]);
    }
    out(['success'=>true]);
}

// ── ME ────────────────────────────────────────────────────────
if ($action === 'me') {
    $user = getAuthUser($pdo);
    if (!$user) out(['success'=>false,'error'=>'No autenticado'], 401);
    out(['success'=>true,'user'=>[
        'id'    => $user['id'],
        'email' => $user['email'],
        'name'  => $user['name'],
        'tier'  => $user['tier'],
        'role'  => $user['role'],
        'email_verified'   => (bool)$user['email_verified'],
        'tasaciones_used'  => $user['tasaciones_count'],
        'tasaciones_limit' => $user['tasaciones_limit'],
        'created_at'       => $user['created_at'],
    ]]);
}

// ── HISTORY ───────────────────────────────────────────────────
if ($action === 'history') {
    $user = getAuthUser($pdo);
    if (!$user) out(['success'=>false,'error'=>'No autenticado'], 401);

    $stmt = $pdo->prepare("
        SELECT code, city, zone, property_type, operation, covered_area,
               price_suggested, created_at
        FROM tasaciones
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['id']]);
    out(['success'=>true,'tasaciones'=>$stmt->fetchAll()]);
}

// ── VERIFY_EMAIL ──────────────────────────────────────────────
if ($action === 'verify_email') {
    $token = $_GET['token'] ?? $input['token'] ?? '';
    if (!$token) out(['success'=>false,'error'=>'Token requerido']);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email_verify_token=?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) out(['success'=>false,'error'=>'Token inválido o expirado']);

    $pdo->prepare("UPDATE users SET email_verified=1, email_verify_token=NULL WHERE id=?")->execute([$user['id']]);

    // Redirigir al tasador con mensaje
    $appUrl = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');
    header("Location: {$appUrl}?verified=1");
    exit;
}

// ── FORGOT_PASSWORD ───────────────────────────────────────────
if ($action === 'forgot_password') {
    $email = trim(strtolower($input['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['success'=>false,'error'=>'Email inválido']);

    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email=? AND status='active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token   = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $pdo->prepare("UPDATE users SET reset_token=?, reset_token_expires=? WHERE id=?")
            ->execute([$token, $expires, $user['id']]);

        $appUrl  = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');
        $resetUrl = "{$appUrl}?reset_token={$token}";
        $content = "
            <p>Recibimos una solicitud para restablecer tu contraseña.</p>
            <div style='text-align:center;margin:24px 0'>
              <a href='{$resetUrl}' style='background:#c9a84c;color:#000;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold'>Cambiar contraseña →</a>
            </div>
            <p style='font-size:12px;color:#999'>Este link expira en 2 horas. Si no solicitaste el cambio, ignorá este email.</p>
        ";
        sendAuthEmail($cfg, $email, "Restablecer contraseña · TasadorIA",
            emailTemplate("Restablecer contraseña", $content, $cfg));
    }
    // Siempre responder igual para no revelar si el email existe
    out(['success'=>true,'message'=>'Si el email existe, te enviamos instrucciones.']);
}

// ── RESET_PASSWORD ────────────────────────────────────────────
if ($action === 'reset_password') {
    $token   = $input['token']    ?? '';
    $newPass = $input['password'] ?? '';
    if (!$token || strlen($newPass) < 6) out(['success'=>false,'error'=>'Token y contraseña (mín 6 chars) requeridos']);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token=? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) out(['success'=>false,'error'=>'Token inválido o expirado']);

    $pdo->prepare("UPDATE users SET password_hash=?, reset_token=NULL, reset_token_expires=NULL WHERE id=?")
        ->execute([password_hash($newPass, PASSWORD_BCRYPT), $user['id']]);

    $sessionToken = createSession($pdo, (int)$user['id']);
    out(['success'=>true,'token'=>$sessionToken,'message'=>'Contraseña actualizada.']);
}

out(['success'=>false,'error'=>"Acción desconocida: {$action}"], 400);
