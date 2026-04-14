<?php
$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];
require __DIR__.'/auth/middleware.php';
$user  = requireAuth($cfg, '/tasador/auth/login.php');
$pdo   = authPdo($cfg);
$brand = $cfg['brand_name']   ?? 'TasadorIA';
$color = $cfg['primary_color']?? '#c9a84c';
$appUrl= rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? '', '/');

// Ver detalle de una tasación
$viewCode = $_GET['code'] ?? '';
$detail   = null;
if ($viewCode) {
    $d = $pdo->prepare("SELECT * FROM user_tasaciones WHERE tasacion_code=? AND user_id=?");
    $d->execute([$viewCode, $user['id']]);
    $detail = $d->fetch();
}

// Listar todas
$page  = max(1, (int)($_GET['p'] ?? 1));
$per   = 20;
$off   = ($page - 1) * $per;
$search= trim($_GET['q'] ?? '');

$where = "WHERE user_id=?";
$params= [$user['id']];
if ($search) {
    $where .= " AND (city LIKE ? OR zone LIKE ? OR title LIKE ? OR tasacion_code LIKE ?)";
    $s = "%{$search}%";
    array_push($params, $s, $s, $s, $s);
}

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM user_tasaciones {$where}")->execute($params) ?
         $pdo->prepare("SELECT COUNT(*) FROM user_tasaciones {$where}")->execute($params) ? 0 : 0 : 0;
// Corregir el conteo
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM user_tasaciones {$where}");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();

$listStmt = $pdo->prepare("SELECT * FROM user_tasaciones {$where} ORDER BY created_at DESC LIMIT {$per} OFFSET {$off}");
$listStmt->execute($params);
$list = $listStmt->fetchAll();
$pages = ceil($total / $per);
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mis Tasaciones · <?= htmlspecialchars($brand) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0e0e0e;color:#ccc;min-height:100vh}
.topbar{background:#141414;border-bottom:1px solid #1e1e1e;padding:0 24px;display:flex;align-items:center;justify-content:space-between;height:56px}
.topbar .logo{color:<?= $color ?>;font-weight:700;font-size:16px;text-decoration:none}
.topbar-links{display:flex;gap:20px;align-items:center}
.topbar-links a{color:#666;font-size:13px;text-decoration:none}
.topbar-links a:hover,.topbar-links a.active{color:<?= $color ?>}
.wrap{max-width:1000px;margin:0 auto;padding:28px 20px}
h2{color:#eee;font-size:18px;margin-bottom:20px}
.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:20px}
.toolbar input{background:#111;border:1px solid #2a2a2a;color:#ddd;padding:9px 13px;border-radius:7px;font-size:13px;outline:none;flex:1;min-width:200px;max-width:320px}
.toolbar input:focus{border-color:<?= $color ?>}
.btn{display:inline-flex;align-items:center;gap:5px;padding:9px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:opacity .15s}
.btn:hover{opacity:.85}
.btn-gold{background:<?= $color ?>;color:#000}
.btn-outline{background:transparent;border:1px solid #2a2a2a;color:#888}
.btn-sm{padding:5px 11px;font-size:12px}
.btn-danger{background:transparent;border:1px solid #3a1a1a;color:#886060}
table{width:100%;border-collapse:collapse;font-size:13px;background:#1a1a1a;border-radius:10px;overflow:hidden}
th{background:#161616;color:#555;font-weight:500;text-align:left;padding:10px 14px;border-bottom:1px solid #1e1e1e}
td{padding:10px 14px;border-bottom:1px solid #141414;color:#ccc;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#161616}
.code{font-family:monospace;font-size:11px;color:#888}
.fav{cursor:pointer;font-size:16px}
.empty{text-align:center;padding:60px;color:#333}
.empty .icon{font-size:48px;margin-bottom:12px}
/* Detail panel */
.detail-panel{background:#1a1a1a;border:1px solid #252525;border-radius:12px;padding:24px;margin-bottom:24px}
.detail-panel h3{color:#eee;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:16px}
.detail-item{background:#141414;border-radius:8px;padding:12px}
.detail-item .lbl{font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.5px}
.detail-item .val{font-size:18px;font-weight:700;color:#c9a84c;margin-top:3px}
.detail-item .sub{font-size:11px;color:#444;margin-top:2px}
.back-link{color:#666;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:5px;margin-bottom:16px}
.back-link:hover{color:#ccc}
/* Pagination */
.pagination{display:flex;gap:6px;justify-content:center;margin-top:20px}
.pagination a,.pagination span{padding:7px 13px;border-radius:6px;font-size:13px;text-decoration:none;border:1px solid #252525;color:#666}
.pagination a:hover{border-color:<?= $color ?>;color:<?= $color ?>}
.pagination .active{background:<?= $color ?>;color:#000;border-color:<?= $color ?>}
</style>
</head><body>

<div class="topbar">
  <a href="<?= $appUrl ?>" class="logo">🏠 <?= htmlspecialchars($brand) ?></a>
  <div class="topbar-links">
    <a href="mi-cuenta.php">Mi cuenta</a>
    <a href="mis-tasaciones.php" class="active">Mis tasaciones</a>
    <?php if (in_array($user['role'], ['agency_admin','super_admin'])): ?>
    <a href="admin_users.php">Gestionar equipo</a>
    <?php endif; ?>
    <a href="auth/logout.php">Salir</a>
  </div>
</div>

<div class="wrap">

<?php if ($detail): ?>
<!-- ── DETALLE ── -->
<a href="mis-tasaciones.php" class="back-link">← Volver a la lista</a>
<div class="detail-panel">
  <h3>
    <?= htmlspecialchars($detail['title'] ?: ucfirst($detail['property_type']).' '.$detail['covered_area'].'m²') ?>
    <span class="code"><?= htmlspecialchars($detail['tasacion_code']) ?></span>
  </h3>
  <div class="detail-grid">
    <div class="detail-item">
      <div class="lbl">Precio sugerido</div>
      <div class="val">USD <?= number_format($detail['price_suggested'],0,',','.') ?></div>
    </div>
    <div class="detail-item">
      <div class="lbl">Rango</div>
      <div class="val" style="font-size:13px">
        <?= number_format($detail['price_min'],0,',','.') ?> – <?= number_format($detail['price_max'],0,',','.') ?>
      </div>
    </div>
    <div class="detail-item">
      <div class="lbl">Superficie</div>
      <div class="val"><?= $detail['covered_area'] ?> m²</div>
    </div>
    <div class="detail-item">
      <div class="lbl">Zona</div>
      <div class="val" style="font-size:14px"><?= htmlspecialchars($detail['zone'] ?: '—') ?></div>
      <div class="sub"><?= htmlspecialchars($detail['city'] ?: '') ?></div>
    </div>
    <div class="detail-item">
      <div class="lbl">Tipo</div>
      <div class="val" style="font-size:14px"><?= htmlspecialchars(ucfirst($detail['property_type'])) ?></div>
      <div class="sub"><?= htmlspecialchars($detail['operation']) ?></div>
    </div>
    <div class="detail-item">
      <div class="lbl">Fecha</div>
      <div class="val" style="font-size:14px"><?= date('d/m/Y', strtotime($detail['created_at'])) ?></div>
    </div>
  </div>
  <?php if ($detail['result_json']):
    $result = json_decode($detail['result_json'], true); ?>
  <details style="margin-top:12px">
    <summary style="cursor:pointer;color:#555;font-size:12px">Ver resultado completo JSON</summary>
    <pre style="background:#111;padding:14px;border-radius:6px;font-size:11px;color:#666;overflow-x:auto;margin-top:8px"><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
  </details>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ── LISTA ── -->
<h2>Mis tasaciones <span style="color:#444;font-size:14px">(<?= $total ?>)</span></h2>
<div class="toolbar">
  <form method="GET" style="display:flex;gap:10px;flex:1">
    <input type="text" name="q" placeholder="Buscar por zona, ciudad, código..." value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-outline" type="submit">🔍</button>
    <?php if ($search): ?><a href="mis-tasaciones.php" class="btn btn-outline">✕</a><?php endif; ?>
  </form>
  <a href="<?= $appUrl ?>" class="btn btn-gold">+ Nueva tasación</a>
</div>

<?php if (!$list): ?>
<div class="empty">
  <div class="icon">📋</div>
  <p>Aún no tenés tasaciones guardadas.</p><br>
  <a href="<?= $appUrl ?>" class="btn btn-gold">🏠 Hacer mi primera tasación</a>
</div>
<?php else: ?>
<table>
  <thead><tr>
    <th>★</th><th>Código</th><th>Propiedad</th><th>Zona</th>
    <th>Precio USD</th><th>m²</th><th>Fecha</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($list as $t): ?>
  <tr>
    <td><span class="fav" onclick="toggleFav(<?= $t['id'] ?>,this)" title="Favorito"><?= $t['is_favorite'] ? '⭐' : '☆' ?></span></td>
    <td class="code"><?= htmlspecialchars($t['tasacion_code']) ?></td>
    <td><?= htmlspecialchars($t['title'] ?: ucfirst($t['property_type'])) ?></td>
    <td><?= htmlspecialchars($t['zone'] ?: $t['city']) ?></td>
    <td>USD <?= number_format($t['price_suggested'],0,',','.') ?></td>
    <td><?= $t['covered_area'] ?></td>
    <td><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
    <td style="display:flex;gap:6px">
      <a href="?code=<?= urlencode($t['tasacion_code']) ?>" class="btn btn-outline btn-sm">Ver</a>
      <button class="btn btn-danger btn-sm" onclick="deleteTas(<?= $t['id'] ?>)">🗑</button>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php for ($i=1; $i<=$pages; $i++): ?>
    <?php if ($i===$page): ?>
      <span class="active"><?= $i ?></span>
    <?php else: ?>
      <a href="?p=<?= $i ?><?= $search ? '&q='.urlencode($search) : '' ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

</div>

<script>
async function toggleFav(id, el) {
  const res  = await fetch('api/user_tasaciones.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'toggle_fav', id})
  });
  const data = await res.json();
  if (data.success) el.textContent = data.is_favorite ? '⭐' : '☆';
}
async function deleteTas(id) {
  if (!confirm('¿Eliminar esta tasación?')) return;
  const res  = await fetch('api/user_tasaciones.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'delete', id})
  });
  const data = await res.json();
  if (data.success) location.reload();
}
</script>
</body></html>
