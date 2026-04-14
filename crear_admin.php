<?php
/**
 * TasadorIA — crear_admin.php
 * Script de uso único para crear/restablecer el usuario super_admin.
 * ⚠️ BORRAR del servidor después de usar.
 *
 * Uso: abrir en el navegador → completar email + contraseña → Crear.
 */

$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];
$brand = $cfg['brand_name'] ?? 'TasadorIA';

$msg = '';
$ok  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = $_POST['pass'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';
    $name  = trim($_POST['name'] ?? 'Admin');
    $secret= trim($_POST['secret'] ?? '');

    // Clave de seguridad simple para evitar que cualquiera lo ejecute
    $expectedSecret = $cfg['admin_secret'] ?? 'tasadoria2025';

    if ($secret !== $expectedSecret) {
        $msg = '❌ Clave de seguridad incorrecta.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = '❌ Email inválido.';
    } elseif (strlen($pass) < 6) {
        $msg = '❌ La contraseña debe tener al menos 6 caracteres.';
    } elseif ($pass !== $pass2) {
        $msg = '❌ Las contraseñas no coinciden.';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
                $cfg['db']['user'], $cfg['db']['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $hash = password_hash($pass, PASSWORD_BCRYPT);

            // Insertar o actualizar
            $pdo->prepare("
                INSERT INTO users (email, password_hash, name, role, tier, tasaciones_limit, email_verified, status, created_at, updated_at)
                VALUES (?, ?, ?, 'super_admin', 'enterprise', NULL, 1, 'active', NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    password_hash  = VALUES(password_hash),
                    name           = VALUES(name),
                    role           = 'super_admin',
                    tier           = 'enterprise',
                    tasaciones_limit = NULL,
                    email_verified = 1,
                    status         = 'active',
                    updated_at     = NOW()
            ")->execute([$email, $hash, $name]);

            $ok  = true;
            $msg = "✅ Usuario <strong>{$email}</strong> creado/actualizado como super_admin.<br>Podés <a href='auth/login.php' style='color:#c9a84c'>iniciar sesión</a> ahora.<br><br><strong>⚠️ Borrá este archivo del servidor:</strong> <code>crear_admin.php</code>";
        } catch (\Throwable $e) {
            $msg = '❌ Error de base de datos: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Crear Admin · <?= htmlspecialchars($brand) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0e0e0e;color:#ccc;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:14px;padding:36px 32px;width:100%;max-width:400px}
h2{color:#eee;font-size:17px;margin-bottom:6px}
p.sub{color:#555;font-size:12px;margin-bottom:24px}
label{display:block;font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;margin-top:16px}
input{width:100%;background:#111;border:1px solid #2a2a2a;color:#ddd;padding:11px 13px;border-radius:7px;font-size:14px;outline:none}
input:focus{border-color:#c9a84c}
.btn{width:100%;margin-top:22px;padding:12px;background:#c9a84c;color:#000;font-weight:700;font-size:14px;border:none;border-radius:8px;cursor:pointer}
.btn:hover{opacity:.85}
.msg{padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:18px;line-height:1.5}
.msg.ok{background:rgba(60,160,60,.1);border:1px solid #1a4a1a;color:#5aaa5a}
.msg.err{background:rgba(160,60,60,.1);border:1px solid #4a1a1a;color:#cc6060}
.warn{background:rgba(200,120,0,.1);border:1px solid #3a2a00;border-radius:8px;padding:10px 14px;font-size:12px;color:#aa8844;margin-bottom:20px}
</style>
</head><body>
<div class="card">
  <h2>🔑 Crear usuario admin</h2>
  <p class="sub">Configura el acceso de super_admin para <?= htmlspecialchars($brand) ?></p>

  <div class="warn">⚠️ Script de uso único. Borrá este archivo después de usarlo.</div>

  <?php if ($msg): ?>
  <div class="msg <?= $ok ? 'ok' : 'err' ?>"><?= $msg ?></div>
  <?php endif; ?>

  <?php if (!$ok): ?>
  <form method="POST">
    <label>Nombre</label>
    <input type="text" name="name" value="Admin" required>

    <label>Email *</label>
    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>

    <label>Contraseña *</label>
    <input type="password" name="pass" placeholder="Mínimo 6 caracteres" required>

    <label>Repetir contraseña *</label>
    <input type="password" name="pass2" required>

    <label>Clave de seguridad * <span style="color:#555">(ver settings.php → admin_secret)</span></label>
    <input type="password" name="secret" placeholder="tasadoria2025" required>

    <button class="btn" type="submit">Crear super_admin</button>
  </form>
  <?php endif; ?>
</div>
</body></html>
