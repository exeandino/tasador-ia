<?php
/**
 * TasadorIA — api/freemium_gate.php
 * Controla el límite de tasaciones gratuitas y el auto-registro.
 *
 * Flujo:
 *   1. check($email, $ip)  — ¿puede tasar? (retorna bool + info)
 *   2. register($email, $name, $code) — post-tasación, crear/actualizar usuario
 *
 * Incluir con: require __DIR__.'/freemium_gate.php';
 * O llamar vía AJAX: POST api/freemium_gate.php
 */

if (!defined('TASADOR_INCLUDED')) {
    // Llamado directo por AJAX
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? $_GET['action'] ?? 'check';
    $gate   = new FreemiumGate($cfg);

    if ($action === 'check') {
        echo json_encode($gate->check($input['email'] ?? null));
    } elseif ($action === 'register') {
        echo json_encode($gate->autoRegister(
            $input['email']        ?? '',
            $input['name']         ?? '',
            $input['tasacion_code']?? ''
        ));
    } else {
        echo json_encode(['success'=>false,'error'=>'Acción desconocida']);
    }
    exit;
}

class FreemiumGate {
    private PDO $pdo;
    private array $cfg;
    private int $freeLimit = 5;

    public function __construct(array $cfg) {
        $this->cfg = $cfg;
        try {
            $this->pdo = new PDO(
                "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
                $cfg['db']['user'], $cfg['db']['pass'],
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
            );
        } catch (\Throwable $e) {}
    }

    /**
     * ¿Puede el visitante realizar una tasación?
     * Returns: ['allowed'=>bool, 'used'=>int, 'limit'=>int, 'tier'=>string,
     *           'user_id'=>int|null, 'needs_payment'=>bool, 'plans_url'=>string]
     */
    public function check(?string $email = null): array {
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $appUrl  = rtrim($this->cfg['site_url'] ?? $this->cfg['app_url'] ?? '', '/');
        $plansUrl = "{$appUrl}/planes.php";

        if ($email) {
            $email = trim(strtolower($email));
            $stmt  = $this->pdo->prepare("SELECT id, tier, tasaciones_count, tasaciones_limit FROM users WHERE email=? AND status='active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $limit   = (int)($user['tasaciones_limit'] ?? $this->freeLimit);
                $used    = (int)$user['tasaciones_count'];
                $tier    = $user['tier'];
                $allowed = ($tier !== 'free') || ($used < $limit);
                return [
                    'allowed'       => $allowed,
                    'used'          => $used,
                    'limit'         => $limit,
                    'tier'          => $tier,
                    'user_id'       => (int)$user['id'],
                    'needs_payment' => !$allowed,
                    'plans_url'     => $plansUrl,
                    'message'       => $allowed ? null : "Alcanzaste el límite de {$limit} tasaciones gratuitas.",
                ];
            }
        }

        // Usuario anónimo — contar por IP en los últimos 30 días
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasaciones WHERE ip_address=? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$ip]);
        $used = (int)$stmt->fetchColumn();
        $allowed = $used < $this->freeLimit;

        return [
            'allowed'       => $allowed,
            'used'          => $used,
            'limit'         => $this->freeLimit,
            'tier'          => 'anonymous',
            'user_id'       => null,
            'needs_payment' => !$allowed,
            'plans_url'     => $plansUrl,
            'message'       => $allowed ? null : "Alcanzaste el límite gratuito. Creá tu cuenta para continuar.",
        ];
    }

    /**
     * Post-tasación: crear usuario si no existe, enviar credenciales, vincular tasación.
     */
    public function autoRegister(string $email, string $name, string $tasacionCode): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success'=>false,'error'=>'Email inválido'];
        }

        $ch = $this->pdo->prepare("SELECT id, tier, tasaciones_count, tasaciones_limit FROM users WHERE email=?");
        $ch->execute([trim(strtolower($email))]);
        $user = $ch->fetch();

        if ($user) {
            // Usuario existente: incrementar contador y vincular
            $this->pdo->prepare("UPDATE users SET tasaciones_count=tasaciones_count+1 WHERE id=?")->execute([$user['id']]);
            if ($tasacionCode) $this->pdo->prepare("UPDATE tasaciones SET user_id=? WHERE code=?")->execute([$user['id'], $tasacionCode]);
            $newCount = (int)$user['tasaciones_count'] + 1;
            $limit    = (int)$user['tasaciones_limit'];
            return [
                'success'         => true,
                'is_new'          => false,
                'user_id'         => $user['id'],
                'tasaciones_used' => $newCount,
                'tasaciones_limit'=> $limit,
                'needs_payment'   => ($user['tier'] === 'free' && $newCount >= $limit),
                'password_sent'   => false,
            ];
        }

        // Nuevo usuario: generar contraseña legible
        $password  = $this->generatePassword();
        $hash      = password_hash($password, PASSWORD_BCRYPT);
        $verifyTok = bin2hex(random_bytes(16));

        $this->pdo->prepare("
            INSERT INTO users (email, password_hash, name, role, tier, tasaciones_count, tasaciones_limit, email_verify_token)
            VALUES (?, ?, ?, 'user', 'free', 1, ?, ?)
        ")->execute([
            trim(strtolower($email)), $hash, $name ?: null, $this->freeLimit, $verifyTok
        ]);
        $userId = (int)$this->pdo->lastInsertId();

        if ($tasacionCode) $this->pdo->prepare("UPDATE tasaciones SET user_id=? WHERE code=?")->execute([$userId, $tasacionCode]);

        $this->sendWelcomeEmail($email, $name, $password, $verifyTok, $tasacionCode);

        return [
            'success'         => true,
            'is_new'          => true,
            'user_id'         => $userId,
            'tasaciones_used' => 1,
            'tasaciones_limit'=> $this->freeLimit,
            'needs_payment'   => false,
            'password_sent'   => true,
        ];
    }

    private function generatePassword(): string {
        $words  = ['Casa','Rio','Sol','Luz','Mar','Alto','Real','Villa'];
        $word   = $words[array_rand($words)];
        $num    = rand(100, 999);
        $sym    = ['!','@','#','$'][rand(0,3)];
        return $word . $num . $sym;
    }

    private function sendWelcomeEmail(string $email, string $name, string $password, string $verifyToken, string $code): void {
        $cfg     = $this->cfg;
        $appUrl  = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');
        $brand   = $cfg['brand_name'] ?? 'TasadorIA';
        $color   = $cfg['primary_color'] ?? '#c9a84c';
        $verifyUrl = "{$appUrl}/api/user_auth.php?action=verify_email&token={$verifyToken}";
        $plansUrl  = "{$appUrl}/planes.php";

        $greeting = $name ? "Hola {$name}," : "¡Hola!";

        $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:system-ui,sans-serif;background:#f4f4f4;margin:0;padding:20px">
<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden">
  <div style="background:{$color};padding:24px;text-align:center">
    <h1 style="color:#000;margin:0;font-size:22px">🏠 {$brand}</h1>
  </div>
  <div style="padding:28px 32px;color:#333;line-height:1.7">
    <h2 style="margin-top:0">Tu tasación fue guardada ✅</h2>
    <p>{$greeting}</p>
    <p>Creamos tu cuenta para que puedas ver tu historial de tasaciones en cualquier momento.</p>

    <div style="background:#f9f4e8;border:1px solid #e8d87a;border-radius:8px;padding:16px;margin:20px 0">
      <strong>🔑 Tus credenciales:</strong><br><br>
      <b>Email:</b> {$email}<br>
      <b>Contraseña:</b>
      <span style="background:#fff;padding:3px 10px;border-radius:4px;font-size:17px;font-weight:bold;letter-spacing:1px;font-family:monospace">{$password}</span>
    </div>

    <div style="background:#f0f7ff;border:1px solid #b8d4f0;border-radius:8px;padding:14px;margin:16px 0">
      🎁 <strong>5 tasaciones gratuitas</strong> — Esta fue la #1.<br>
      Al usar las 5, podés seguir con un plan Pro desde <strong>USD 9/mes</strong>.
    </div>

    <div style="text-align:center;margin:28px 0">
      <a href="{$appUrl}" style="background:{$color};color:#000;padding:13px 30px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block">Ver mi tasación →</a>
    </div>

    <p style="font-size:12px;color:#999;text-align:center">
      <a href="{$verifyUrl}" style="color:#888">Verificar email</a> ·
      <a href="{$plansUrl}" style="color:#888">Ver planes</a>
    </p>
  </div>
  <div style="padding:16px;background:#f9f9f9;text-align:center;font-size:12px;color:#999">
    {$brand} · Santa Fe, Argentina 🇦🇷
  </div>
</div></body></html>
HTML;

        $subject = "🏠 Tu tasación quedó guardada · {$brand}";
        $smtp = $cfg['smtp'] ?? [];

        if (!empty($smtp['host']) && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP(); $mail->Host=$smtp['host']; $mail->SMTPAuth=true;
                $mail->Username=$smtp['user']; $mail->Password=$smtp['pass'];
                $mail->SMTPSecure=$smtp['secure']??'tls'; $mail->Port=$smtp['port']??587;
                $mail->CharSet='UTF-8';
                $mail->setFrom($smtp['from'], $smtp['from_name']??$brand);
                $mail->addAddress($email, $name);
                $mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$html;
                $mail->send();
                return;
            } catch (\Throwable $e) {}
        }
        @mail($email, $subject, $html,
            "From: {$brand} <".($smtp['from']??'noreply@tasadoria.com').">\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0");
    }
}
