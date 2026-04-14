<?php
session_start();
$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
require __DIR__.'/middleware.php';

// Ya logueado → redirigir
if (getCurrentUser($cfg)) {
    header('Location: ../mi-cuenta.php'); exit;
}

$error  = '';
$next   = $_GET['next'] ?? '../mi-cuenta.php';
$brand  = $cfg['brand_name']   ?? 'TasadorIA';
$color  = $cfg['primary_color']?? '#c9a84c';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);
    try {
        $stmt = authPdo($cfg)->prepare("SELECT * FROM users WHERE email=? AND status='active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            authPdo($cfg)->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            createUserSession($cfg, (int)$user['id'], $remember);
            header('Location: ' . ($next ?: '../mi-cuenta.php')); exit;
        }
        $error = 'Email o contraseña incorrectos.';
    } catch (\Throwable $e) { $error = 'Error de conexión.'; }
}
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ingresar · <?= htmlspecialchars($brand) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0e0e0e;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#1a1a1a;border:1px solid #252525;border-radius:14px;padding:36px;width:100%;max-width:380px}
.logo{text-align:center;font-size:28px;margin-bottom:6px}
h1{text-align:center;color:#eee;font-size:20px;margin-bottom:4px}
.sub{text-align:center;color:#555;font-size:13px;margin-bottom:24px}
label{font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px;margin-top:14px}
input[type=email],input[type=password]{width:100%;background:#111;border:1px solid #2a2a2a;color:#ddd;border-radius:7px;padding:10px 13px;font-size:14px;outline:none;transition:border-color .15s}
input:focus{border-color:<?= $color ?>}
.remember{display:flex;align-items:center;gap:7px;margin-top:12px;font-size:13px;color:#666;cursor:pointer}
.remember input{width:auto}
.btn{display:block;width:100%;margin-top:20px;background:<?= $color ?>;color:#000;border:none;padding:12px;border-radius:7px;font-size:14px;font-weight:700;cursor:pointer}
.btn:hover{opacity:.88}
.error{background:rgba(180,60,60,.15);border:1px solid #4a1a1a;color:#cc6060;padding:10px 13px;border-radius:7px;font-size:13px;margin-bottom:4px}
.links{display:flex;justify-content:space-between;margin-top:18px;font-size:12px}
.links a{color:#555;text-decoration:none}
.links a:hover{color:<?= $color ?>}
.divider{border:none;border-top:1px solid #1e1e1e;margin:20px 0}
.register-link{text-align:center;font-size:13px;color:#555}
.register-link a{color:<?= $color ?>;text-decoration:none;font-weight:600}
</style>
</head><body>
<div class="card">
  <div class="logo">🏠</div>
  <h1><?= htmlspecialchars($brand) ?></h1>
  <div class="sub">Ingresá a tu cuenta</div>
  <?php if ($error): ?><div class="error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="POST">
    <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
    <label>Email</label>
    <input type="email" name="email" placeholder="tu@email.com" required autofocus
           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    <label>Contraseña</label>
    <input type="password" name="password" placeholder="••••••••" required>
    <label class="remember">
      <input type="checkbox" name="remember"> Recordarme por 30 días
    </label>
    <button class="btn" type="submit">Ingresar →</button>
  </form>
  <div class="links">
    <a href="forgot.php">Olvidé mi contraseña</a>
    <a href="../<?= basename(dirname(__DIR__)) ?>">← Volver al tasador</a>
  </div>
  <hr class="divider">
  <div class="register-link">¿No tenés cuenta? <a href="register.php">Registrate gratis</a></div>
</div>
</body></html>
