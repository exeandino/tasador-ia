<?php
$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];
require __DIR__.'/auth/middleware.php';
$user  = requireAuth($cfg, '/tasador/auth/login.php');
$pdo   = authPdo($cfg);
$brand = $cfg['brand_name']   ?? 'TasadorIA';
$color = $cfg['primary_color']?? '#c9a84c';
$appUrl= rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');

// Cargar stats
$stats = $pdo->prepare("SELECT COUNT(*) as total, MAX(created_at) as last FROM user_tasaciones WHERE user_id=?");
$stats->execute([$user['id']]);
$ts = $stats->fetch();

// Últimas 5 tasaciones
$recents = $pdo->prepare("SELECT * FROM user_tasaciones WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$recents->execute([$user['id']]);
$recent = $recents->fetchAll();

$tierLabels = ['free'=>'Gratis','pro'=>'Pro','agency'=>'Agencia','enterprise'=>'Enterprise'];
$tierColors = ['free'=>'#444','pro'=>'#c9a84c','agency'=>'#5a9fd4','enterprise'=>'#9b59b6'];
$tierLabel  = $tierLabels[$user['tier']] ?? $user['tier'];
$tierColor  = $tierColors[$user['tier']] ?? '#444';
$limit      = $user['tasaciones_limit'];
$used       = $user['tasaciones_count'];
$pct        = $limit ? min(100, round($used/$limit*100)) : 0;
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mi Cuenta · <?= htmlspecialchars($brand) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0e0e0e;color:#ccc;min-height:100vh}
/* Topbar */
.topbar{background:#141414;border-bottom:1px solid #1e1e1e;padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:56px}
.topbar .logo{color:<?= $color ?>;font-weight:700;font-size:16px;text-decoration:none}
.topbar-links{display:flex;gap:20px;align-items:center}
.topbar-links a{color:#666;font-size:13px;text-decoration:none}
.topbar-links a:hover{color:#ccc}
.topbar-links a.active{color:<?= $color ?>}
/* Layout */
.wrap{max-width:1000px;margin:0 auto;padding:28px 20px}
h2{color:#eee;font-size:18px;margin-bottom:20px}
/* Cards */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
.card{background:#1a1a1a;border:1px solid #252525;border-radius:12px;padding:20px}
.card h3{font-size:13px;color:#666;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
/* Plan card */
.plan-badge{display:inline-block;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;background:<?= $tierColor ?>22;color:<?= $tierColor ?>;border:1px solid <?= $tierColor ?>44;margin-bottom:10px}
.usage-bar{background:#111;border-radius:6px;height:8px;margin:8px 0;overflow:hidden}
.usage-fill{background:<?= $pct>=80?'#c94c4c':$color ?>;height:100%;border-radius:6px;width:<?= $pct ?>%;transition:width .4s}
.usage-text{font-size:12px;color:#555}
/* Profile */
.profile-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #1e1e1e;font-size:13px}
.profile-row:last-child{border-bottom:none}
.profile-row .lbl{color:#555}
.profile-row .val{color:#ccc}
/* Recent */
.recent-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}
.recent-table th{color:#555;font-weight:500;text-align:left;padding:6px 10px;border-bottom:1px solid #1e1e1e}
.recent-table td{padding:8px 10px;border-bottom:1px solid #141414;color:#ccc}
.recent-table tr:hover td{background:#161616}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:opacity .15s}
.btn:hover{opacity:.85}
.btn-gold{background:<?= $color ?>;color:#000}
.btn-outline{background:transparent;border:1px solid #2a2a2a;color:#888}
.btn-sm{padding:5px 12px;font-size:12px}
/* Warning */
.warning{background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);border-radius:8px;padding:12px 16px;font-size:13px;color:#c9a84c;margin-bottom:20px}
@media(max-width:600px){.grid-2{grid-template-columns:1fr}}
</style>
</head><body>

<div class="topbar">
  <a href="<?= $appUrl ?>" class="logo">🏠 <?= htmlspecialchars($brand) ?></a>
  <div class="topbar-links">
    <a href="mi-cuenta.php" class="active">Mi cuenta</a>
    <a href="mis-tasaciones.php">Mis tasaciones</a>
    <?php if (in_array($user['role'], ['agency_admin','super_admin'])): ?>
    <a href="admin_users.php">Gestionar equipo</a>
    <?php endif; ?>
    <a href="auth/logout.php">Salir</a>
  </div>
</div>

<div class="wrap">

<?php if ($user['tier'] === 'free' && $used >= $limit - 1): ?>
<div class="warning">
  ⚠️ <?php if ($used >= $limit): ?>
    Usaste todas tus tasaciones gratuitas. <a href="planes.php" style="color:<?= $color ?>;font-weight:bold">Elegí un plan →</a>
  <?php else: ?>
    Te queda <?= $limit - $used ?> tasación gratuita. <a href="planes.php" style="color:<?= $color ?>">Ver planes</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<h2>Hola, <?= htmlspecialchars($user['name'] ?: explode('@', $user['email'])[0]) ?> 👋</h2>

<div class="grid-2">
  <!-- Plan -->
  <div class="card">
    <h3>Tu plan</h3>
    <div class="plan-badge"><?= $tierLabel ?></div>
    <?php if ($user['tier'] === 'free'): ?>
      <div class="usage-bar"><div class="usage-fill"></div></div>
      <div class="usage-text"><?= $used ?> / <?= $limit ?> tasaciones usadas</div>
      <br>
      <a href="planes.php" class="btn btn-gold btn-sm">⚡ Mejorar plan</a>
    <?php else: ?>
      <div style="font-size:13px;color:#666;margin-bottom:12px">Tasaciones ilimitadas</div>
      <a href="planes.php" class="btn btn-outline btn-sm">Ver planes</a>
    <?php endif; ?>
  </div>

  <!-- Perfil -->
  <div class="card">
    <h3>Perfil</h3>
    <div class="profile-row"><span class="lbl">Email</span><span class="val"><?= htmlspecialchars($user['email']) ?></span></div>
    <div class="profile-row"><span class="lbl">Nombre</span><span class="val"><?= htmlspecialchars($user['name'] ?: '—') ?></span></div>
    <div class="profile-row"><span class="lbl">Rol</span><span class="val"><?= htmlspecialchars($user['role']) ?></span></div>
    <div class="profile-row"><span class="lbl">Miembro desde</span><span class="val"><?= date('M Y', strtotime($user['created_at'])) ?></span></div>
    <div class="profile-row"><span class="lbl">Email verificado</span><span class="val"><?= $user['email_verified'] ? '✅' : '⚠️ Pendiente' ?></span></div>
  </div>
</div>

<!-- Tasaciones recientes -->
<div class="card">
  <h3 style="margin-bottom:0;display:flex;justify-content:space-between;align-items:center">
    Tasaciones recientes
    <a href="mis-tasaciones.php" class="btn btn-outline btn-sm">Ver todas →</a>
  </h3>
  <?php if (!$recent): ?>
    <div style="text-align:center;padding:40px;color:#333">
      <div style="font-size:40px;margin-bottom:10px">📋</div>
      <p>Aún no tenés tasaciones guardadas.</p><br>
      <a href="<?= $appUrl ?>" class="btn btn-gold">🏠 Tasar una propiedad</a>
    </div>
  <?php else: ?>
  <table class="recent-table">
    <thead><tr><th>Propiedad</th><th>Zona</th><th>Precio</th><th>Fecha</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recent as $t): ?>
    <tr>
      <td><?= htmlspecialchars($t['title'] ?: ucfirst($t['property_type']).' '.$t['covered_area'].'m²') ?></td>
      <td><?= htmlspecialchars($t['zone'] ?: $t['city']) ?></td>
      <td>USD <?= number_format($t['price_suggested'], 0, ',', '.') ?></td>
      <td><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
      <td><a href="mis-tasaciones.php?code=<?= urlencode($t['tasacion_code']) ?>" class="btn btn-outline btn-sm">Ver</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div style="text-align:center;margin-top:24px">
  <a href="<?= $appUrl ?>" class="btn btn-gold">🏠 Nueva tasación</a>
</div>

</div>
</body></html>
