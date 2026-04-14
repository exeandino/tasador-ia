<?php
session_start();
$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
require __DIR__.'/middleware.php';

if (getCurrentUser($cfg)) { header('Location: ../mi-cuenta.php'); exit; }

$error  = '';
$ok     = false;
$brand  = $cfg['brand_name']   ?? 'TasadorIA';
$color  = $cfg['primary_color']?? '#c9a84c';
$appUrl = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '..', '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $name  = trim($_POST['name'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } elseif (strlen($pass) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            $pdo = authPdo($cfg);
            $chk = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $error = 'Ya existe una cuenta con ese email. <a href="login.php">Ingresá</a>.';
            } else {
                $tok = bin2hex(random_bytes(16));
                $pdo->prepare("INSERT INTO users (email,password_hash,name,email_verify_token) VALUES (?,?,?,?)")
                    ->execute([$email, password_hash($pass, PASSWORD_BCRYPT), $name ?: null, $tok]);
                $uid = (int)$pdo->lastInsertId();
                // Enviar verificación
                $verUrl = "{$appUrl}/api/user_auth.php?action=verify_email&token={$tok}";
                $html   = "Confirmá tu email haciendo clic: <a href='{$verUrl}'>{$verUrl}</a>";
                @mail($email, "Verificá tu cuenta · {$brand}", $html,
                    "From: {$brand} <{$cfg['smtp']['from']}>\r\nContent-Type: text/html; charset=UTF-8");
                createUserSession($cfg, $uid);
                $ok = true;
            }
        } catch (\Throwable $e) { $error = 'Error al crear la cuenta.'; }
    }
}
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Crear cuenta · <?= htmlspecialchars($brand) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0e0e0e;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#1a1a1a;border:1px solid #252525;border-radius:14px;padding:36px;width:100%;max-width:380px}
.logo{text-align:center;font-size:28px;margin-bottom:6px}
h1{text-align:center;color:#eee;font-size:20px;margin-bottom:4px}
.sub{text-align:center;color:#555;font-size:13px;margin-bottom:24px}
label{font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px;margin-top:14px}
input{width:100%;background:#111;border:1px solid #2a2a2a;color:#ddd;border-radius:7px;padding:10px 13px;font-size:14px;outline:none;transition:border-color .15s}
input:focus{border-color:<?= $color ?>}
.btn{display:block;width:100%;margin-top:20px;background:<?= $color ?>;color:#000;border:none;padding:12px;border-radius:7px;font-size:14px;font-weight:700;cursor:pointer}
.btn:hover{opacity:.88}
.error{background:rgba(180,60,60,.15);border:1px solid #4a1a1a;color:#cc6060;padding:10px 13px;border-radius:7px;font-size:13px;margin-bottom:4px}
.success{background:rgba(60,160,60,.12);border:1px solid #1a4a1a;color:#5aaa5a;padding:14px;border-radius:7px;font-size:14px;text-align:center}
.free-badge{background:#1e3a1e;border:1px solid #2a4a2a;color:#5aaa5a;border-radius:8px;padding:10px 14px;font-size:12px;margin-bottom:16px;text-align:center}
.divider{border:none;border-top:1px solid #1e1e1e;margin:20px 0}
.login-link{text-align:center;font-size:13px;color:#555}
.login-link a{color:<?= $color ?>;text-decoration:none;font-weight:600}
</style>
</head><body>
<div class="card">
  <div class="logo">🏠</div>
  <h1><?= htmlspecialchars($brand) ?></h1>
  <?php if ($ok): ?>
    <div class="success">
      ✅ ¡Cuenta creada!<br><br>
      Revisá tu email para verificar tu cuenta.<br><br>
      <a href="../mi-cuenta.php" style="color:<?= $color ?>;font-weight:bold">Ir a mi cuenta →</a>
    </div>
  <?php else: ?>
  <div class="sub">Creá tu cuenta gratis</div>
  <div class="free-badge">🎁 <strong>5 tasaciones gratis</strong> · Sin tarjeta de crédito</div>
  <?php if ($error): ?><div class="error">⚠️ <?= $error ?></div><?php endif; ?>
  <form method="POST">
    <label>Nombre</label>
    <input type="text" name="name" placeholder="Tu nombre" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    <label>Email</label>
    <input type="email" name="email" placeholder="tu@email.com" required autofocus
           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    <label>Contraseña</label>
    <input type="password" name="password" placeholder="Mínimo 6 caracteres" required>
    <button class="btn" type="submit">Crear cuenta gratis →</button>
  </form>
  <hr class="divider">
  <div class="login-link">¿Ya tenés cuenta? <a href="login.php">Ingresá</a></div>
  <?php endif; ?>
</div>
</body></html>
