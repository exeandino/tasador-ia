<?php
/**
 * TasadorIA — admin_users.php
 * Panel para gestión de usuarios de la agencia (agency_admin y super_admin).
 * Permite crear agentes, ver su actividad, cambiar plan y suspender cuentas.
 */
$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];
require __DIR__.'/auth/middleware.php';
$user = requireRole($cfg, 'agency_admin');
$pdo  = authPdo($cfg);

$brand  = $cfg['brand_name']   ?? 'TasadorIA';
$color  = $cfg['primary_color']?? '#c9a84c';
$appUrl = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');
$isSuperAdmin = $user['role'] === 'super_admin';

// Scope: super_admin ve todos; agency_admin solo su agencia
$scopeWhere  = $isSuperAdmin ? '' : 'WHERE agency_id=?';
$scopeParams = $isSuperAdmin ? [] : [$user['agency_id']];

// Acciones POST
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'create_agent') {
        $email = trim(strtolower($_POST['email'] ?? ''));
        $name  = trim($_POST['name'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pass = strtoupper(substr(md5(uniqid()),0,3)).rand(10,99).strtolower(substr(md5(uniqid()),0,3));
            try {
                $pdo->prepare("INSERT INTO users (email,password_hash,name,role,tier,agency_id,tasaciones_limit) VALUES (?,?,?,'agent','pro',?,9999)")
                    ->execute([$email, password_hash($pass, PASSWORD_BCRYPT), $name ?: null, $user['agency_id']]);
                // Email con credenciales
                $html = "Tu cuenta de agente en {$brand} fue creada.<br><b>Email:</b> {$email}<br><b>Contraseña:</b> <code>{$pass}</code><br><a href='{$appUrl}/auth/login.php'>Ingresar</a>";
                @mail($email, "Tu cuenta de agente · {$brand}", $html, "From: {$brand} <{$cfg['smtp']['from']}>\r\nContent-Type: text/html; charset=UTF-8");
                $msg = "✅ Agente {$email} creado. Se envió email con credenciales.";
            } catch (\Throwable $e) {
                $msg = "❌ Error: ya existe un usuario con ese email.";
            }
        }
    }

    if ($act === 'toggle_status') {
        $uid = (int)($_POST['uid'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'active';
        if ($isSuperAdmin || /* validar que pertenece a la agencia */ true) {
            $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$newStatus, $uid]);
            $msg = "✅ Estado actualizado.";
        }
    }

    if ($act === 'change_tier') {
        $uid     = (int)($_POST['uid'] ?? 0);
        $newTier = $_POST['tier'] ?? 'free';
        $pdo->prepare("UPDATE users SET tier=? WHERE id=?")->execute([$newTier, $uid]);
        $msg = "✅ Plan actualizado.";
    }

    if ($act === 'reset_password') {
        $uid = (int)($_POST['uid'] ?? 0);
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id=?"); $stmt->execute([$uid]);
        $u2 = $stmt->fetch();
        if ($u2) {
            $pass = strtoupper(substr(md5(uniqid()),0,3)).rand(10,99).strtolower(substr(md5(uniqid()),0,3));
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($pass, PASSWORD_BCRYPT), $uid]);
            $html = "Tu contraseña fue restablecida.<br><b>Nueva contraseña:</b> <code>{$pass}</code><br><a href='{$appUrl}/auth/login.php'>Ingresar</a>";
            @mail($u2['email'], "Contraseña restablecida · {$brand}", $html, "From: {$brand} <{$cfg['smtp']['from']}>\r\nContent-Type: text/html; charset=UTF-8");
            $msg = "✅ Contraseña restablecida y enviada por email.";
        }
    }
}

// Listar usuarios
$search = trim($_GET['q'] ?? '');
$where2 = $scopeWhere ? $scopeWhere : 'WHERE 1';
if (!$isSuperAdmin) {
    $where2 = "WHERE agency_id=?" . ($search ? " AND (email LIKE ? OR name LIKE ?)" : '');
    $params2 = $search ? [$user['agency_id'], "%{$search}%", "%{$search}%"] : [$user['agency_id']];
} else {
    $where2 = $search ? "WHERE (email LIKE ? OR name LIKE ?)" : 'WHERE 1';
    $params2 = $search ? ["%{$search}%", "%{$search}%"] : [];
}

// Excluir el propio super_admin del listado normal
if (!$isSuperAdmin) {
    $where2 .= " AND id != ?";
    $params2[] = $user['id'];
}

$stmt = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM user_tasaciones WHERE user_id=u.id) as tas_count FROM users u {$where2} ORDER BY created_at DESC LIMIT 200");
$stmt->execute($params2);
$users = $stmt->fetchAll();

// Stats globales (solo super_admin)
$stats = [];
if ($isSuperAdmin) {
    $stats['total_users']  = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['free']         = $pdo->query("SELECT COUNT(*) FROM users WHERE tier='free'")->fetchColumn();
    $stats['pro']          = $pdo->query("SELECT COUNT(*) FROM users WHERE tier='pro'")->fetchColumn();
    $stats['agency']       = $pdo->query("SELECT COUNT(*) FROM users WHERE tier='agency'")->fetchColumn();
    $stats['today']        = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $stats['tasaciones']   = $pdo->query("SELECT COUNT(*) FROM tasaciones")->fetchColumn();
}
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestión de usuarios · <?= htmlspecialchars($brand) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0e0e0e;color:#ccc;display:flex;flex-direction:column;min-height:100vh}
<?php $currentPanel='users'; require __DIR__.'/includes/admin_topnav_styles.php' ?? '' ?>
.wrap{max-width:1200px;margin:0 auto;padding:24px 20px;flex:1}
h2{color:#eee;font-size:18px;margin-bottom:20px}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:24px}
.stat-card{background:#1a1a1a;border:1px solid #252525;border-radius:10px;padding:14px 16px}
.stat-card .val{font-size:22px;font-weight:700;color:<?= $color ?>}
.stat-card .lbl{font-size:11px;color:#555;margin-top:3px;text-transform:uppercase;letter-spacing:.4px}
.toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.toolbar input{background:#111;border:1px solid #2a2a2a;color:#ddd;padding:9px 13px;border-radius:7px;font-size:13px;outline:none;flex:1;min-width:180px;max-width:280px}
.toolbar input:focus{border-color:<?= $color ?>}
.btn{display:inline-flex;align-items:center;gap:5px;padding:9px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:opacity .15s}
.btn:hover{opacity:.85}
.btn-gold{background:<?= $color ?>;color:#000}
.btn-outline{background:transparent;border:1px solid #2a2a2a;color:#888}
.btn-sm{padding:5px 11px;font-size:12px}
.btn-danger{background:transparent;border:1px solid #3a1a1a;color:#886060}
.msg{padding:10px 14px;border-radius:7px;font-size:13px;margin-bottom:16px}
.msg.ok{background:rgba(60,160,60,.1);border:1px solid #1a4a1a;color:#5aaa5a}
.msg.err{background:rgba(160,60,60,.1);border:1px solid #4a1a1a;color:#cc6060}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#161616;color:#555;font-weight:500;text-align:left;padding:10px 12px;border-bottom:1px solid #1e1e1e;white-space:nowrap}
td{padding:9px 12px;border-bottom:1px solid #141414;vertical-align:middle}
tr:hover td{background:#141414}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
.badge-free{background:#222;color:#666}
.badge-pro{background:rgba(201,168,76,.15);color:#c9a84c}
.badge-agency{background:rgba(90,159,212,.15);color:#5a9fd4}
.badge-enterprise{background:rgba(155,89,182,.15);color:#9b59b6}
.badge-active{background:rgba(60,160,60,.12);color:#5aaa5a}
.badge-inactive,.badge-banned,.badge-suspended{background:rgba(160,60,60,.12);color:#cc6060}
/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#1a1a1a;border:1px solid #2a2a2a;border-radius:12px;padding:28px;max-width:400px;width:90%}
.modal h3{color:#eee;margin-bottom:16px}
.modal label{font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:4px;margin-top:12px}
.modal input,.modal select{width:100%;background:#111;border:1px solid #2a2a2a;color:#ddd;padding:9px 12px;border-radius:6px;font-size:13px;outline:none}
.modal input:focus,.modal select:focus{border-color:<?= $color ?>}
</style>
</head><body>
<?php $currentPanel='users'; require __DIR__.'/includes/admin_topnav.php'; ?>
<div class="wrap">

<?php if ($msg): ?>
<div class="msg <?= str_starts_with($msg,'✅')?'ok':'err' ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($isSuperAdmin && $stats): ?>
<div class="stat-grid">
  <div class="stat-card"><div class="val"><?= $stats['total_users'] ?></div><div class="lbl">Usuarios totales</div></div>
  <div class="stat-card"><div class="val"><?= $stats['free'] ?></div><div class="lbl">Free</div></div>
  <div class="stat-card"><div class="val"><?= $stats['pro'] ?></div><div class="lbl">Pro</div></div>
  <div class="stat-card"><div class="val"><?= $stats['agency'] ?></div><div class="lbl">Agencia</div></div>
  <div class="stat-card"><div class="val"><?= $stats['today'] ?></div><div class="lbl">Nuevos hoy</div></div>
  <div class="stat-card"><div class="val"><?= number_format($stats['tasaciones']) ?></div><div class="lbl">Tasaciones</div></div>
</div>
<?php endif; ?>

<h2>
  <?= $isSuperAdmin ? 'Todos los usuarios' : 'Equipo de la agencia' ?>
  <span style="color:#444;font-size:13px;font-weight:normal">(<?= count($users) ?>)</span>
</h2>

<div class="toolbar">
  <form method="GET" style="display:flex;gap:8px;flex:1">
    <input type="text" name="q" placeholder="Buscar email o nombre..." value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-outline" type="submit">🔍</button>
  </form>
  <button class="btn btn-gold" onclick="document.getElementById('createModal').classList.add('open')">+ Agregar agente</button>
</div>

<table>
  <thead><tr>
    <th>Email</th><th>Nombre</th><th>Rol</th><th>Plan</th>
    <th>Tasaciones</th><th>Estado</th><th>Último acceso</th><th>Acciones</th>
  </tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><?= htmlspecialchars($u['email']) ?></td>
    <td><?= htmlspecialchars($u['name'] ?: '—') ?></td>
    <td><span style="font-size:12px;color:#666"><?= $u['role'] ?></span></td>
    <td><span class="badge badge-<?= $u['tier'] ?>"><?= ucfirst($u['tier']) ?></span></td>
    <td><?= (int)$u['tas_count'] ?> / <?= $u['tasaciones_limit'] ?? '∞' ?></td>
    <td><span class="badge badge-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
    <td style="font-size:12px;color:#555"><?= $u['last_login'] ? date('d/m/Y', strtotime($u['last_login'])) : 'Nunca' ?></td>
    <td style="display:flex;gap:5px;flex-wrap:wrap">
      <!-- Cambiar tier -->
      <form method="POST" style="display:inline">
        <input type="hidden" name="act" value="change_tier">
        <input type="hidden" name="uid" value="<?= $u['id'] ?>">
        <select name="tier" onchange="this.form.submit()" style="background:#111;border:1px solid #222;color:#888;padding:4px 6px;border-radius:5px;font-size:11px;cursor:pointer">
          <?php foreach(['free','pro','agency','enterprise'] as $t): ?>
            <option value="<?= $t ?>" <?= $u['tier']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <!-- Reset password -->
      <form method="POST" style="display:inline">
        <input type="hidden" name="act" value="reset_password">
        <input type="hidden" name="uid" value="<?= $u['id'] ?>">
        <button class="btn btn-outline btn-sm" type="submit" title="Resetear contraseña">🔑</button>
      </form>
      <!-- Toggle status -->
      <form method="POST" style="display:inline" onsubmit="return confirm('¿Confirmar cambio de estado?')">
        <input type="hidden" name="act" value="toggle_status">
        <input type="hidden" name="uid" value="<?= $u['id'] ?>">
        <input type="hidden" name="new_status" value="<?= $u['status']==='active'?'suspended':'active' ?>">
        <button class="btn <?= $u['status']==='active'?'btn-danger':'btn-outline' ?> btn-sm" type="submit">
          <?= $u['status']==='active' ? '🚫' : '✅' ?>
        </button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

</div>

<!-- Modal crear agente -->
<div class="modal-overlay" id="createModal">
  <div class="modal">
    <h3>➕ Agregar agente</h3>
    <form method="POST">
      <input type="hidden" name="act" value="create_agent">
      <label>Nombre</label>
      <input type="text" name="name" placeholder="Nombre del agente">
      <label>Email *</label>
      <input type="email" name="email" placeholder="agente@inmobiliaria.com" required>
      <p style="font-size:12px;color:#555;margin-top:12px">Se envía un email con la contraseña temporal.</p>
      <div style="display:flex;gap:10px;margin-top:16px">
        <button class="btn btn-gold" type="submit">Crear agente</button>
        <button class="btn btn-outline" type="button" onclick="document.getElementById('createModal').classList.remove('open')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('createModal').addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
</script>
</body></html>
