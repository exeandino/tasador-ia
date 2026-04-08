<!DOCTYPE html>
<?php
// tasador/admin_zonas.php
// Comparación de precios configurados vs datos reales importados
// Editor de zonas + tipo de cambio

define('ADMIN_PASS', 'anper2025');
session_start();
if (isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === ADMIN_PASS) $_SESSION['az_auth'] = true;
    else $loginErr = 'Contraseña incorrecta';
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin_zonas.php'); exit; }
$logged = ($_SESSION['az_auth'] ?? false);

$cfg   = require __DIR__ . '/config/settings.php';
$zones = require __DIR__ . '/config/zones.php';
$msg   = '';

// ── Conectar BD ──────────────────────────────────────────────────────────────
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {}

// ── Guardar tipo de cambio ───────────────────────────────────────────────────
if ($logged && isset($_POST['save_usd'])) {
    $rate = (int)($_POST['usd_rate'] ?? 1450);
    $settingsFile = __DIR__ . '/config/settings.php';
    $content = file_get_contents($settingsFile);
    $content = preg_replace("/'ars_usd_rate'\s*=>\s*\d+/", "'ars_usd_rate' => $rate", $content);
    file_put_contents($settingsFile, $content);
    $cfg = require $settingsFile;
    $msg = "✅ Tipo de cambio actualizado a $" . number_format($rate,0,',','.') . " ARS/USD";
}

// ── Guardar precios de zona ───────────────────────────────────────────────────
if ($logged && isset($_POST['save_zone'])) {
    $cityKey = $_POST['city_key'];
    $zoneKey = $_POST['zone_key'];
    $min     = (int)$_POST['price_min'];
    $avg     = (int)$_POST['price_avg'];
    $max     = (int)$_POST['price_max'];

    if (isset($zones[$cityKey]['zones'][$zoneKey])) {
        $zones[$cityKey]['zones'][$zoneKey]['price_m2'] = ['min' => $min, 'avg' => $avg, 'max' => $max];

        // Reescribir zones.php
        $out = "<?php\n// tasador/config/zones.php\n// Editado: " . date('Y-m-d H:i:s') . "\nreturn [\n\n";
        foreach ($zones as $ck => $city) {
            $out .= "    '$ck' => [\n";
            $out .= "        'label'    => " . var_export($city['label'], true) . ",\n";
            $out .= "        'country'  => " . var_export($city['country'] ?? 'AR', true) . ",\n";
            $out .= "        'currency' => " . var_export($city['currency'] ?? 'USD', true) . ",\n";
            $out .= "        'updated'  => '" . date('Y-m') . "',\n";
            $out .= "        'bounds'   => " . var_export($city['bounds'], true) . ",\n";
            $out .= "        'zones' => [\n";
            foreach ($city['zones'] as $zk => $zone) {
                $out .= "            '$zk' => [\n";
                $out .= "                'label'       => " . var_export($zone['label'], true) . ",\n";
                $out .= "                'price_m2'    => ['min' => {$zone['price_m2']['min']}, 'max' => {$zone['price_m2']['max']}, 'avg' => {$zone['price_m2']['avg']}],\n";
                $out .= "                'description' => " . var_export($zone['description'], true) . ",\n";
                $out .= "                'coords'      => " . var_export($zone['coords'], true) . ",\n";
                $out .= "                'keywords'    => " . var_export($zone['keywords'], true) . ",\n";
                $out .= "                'multipliers' => [],\n";
                $out .= "            ],\n";
            }
            $out .= "        ],\n    ],\n\n";
        }
        $out .= "];\n";
        file_put_contents(__DIR__ . '/config/zones.php', $out);
        $zones = require __DIR__ . '/config/zones.php';
        $msg = "✅ Zona '{$zones[$cityKey]['zones'][$zoneKey]['label']}' actualizada: min=\${$min} avg=\${$avg} max=\${$max} USD/m²";
    }
}

// ── Datos reales de la BD por zona ───────────────────────────────────────────
function getMarketData(PDO $pdo, string $citySearch, string $propType = 'departamento'): array {
    try {
        $stmt = $pdo->prepare("
            SELECT
                city, zone,
                COUNT(*) as count,
                ROUND(AVG(price_per_m2), 0) as avg_ppm2,
                ROUND(MIN(price_per_m2), 0) as min_ppm2,
                ROUND(MAX(price_per_m2), 0) as max_ppm2,
                ROUND(STDDEV(price_per_m2), 0) as std_ppm2,
                MAX(scraped_at) as last_update
            FROM market_listings
            WHERE active = 1
              AND price_per_m2 BETWEEN 100 AND 20000
              AND property_type = :type
              AND (city LIKE :city OR zone LIKE :city OR address LIKE :city)
            GROUP BY city, zone
            ORDER BY count DESC
        ");
        $stmt->execute([':type' => $propType, ':city' => '%' . $citySearch . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function getGlobalStats(PDO $pdo): array {
    try {
        return $pdo->query("
            SELECT property_type, COUNT(*) as total,
                   SUM(CASE WHEN price_per_m2 IS NOT NULL AND price_per_m2 > 0 THEN 1 ELSE 0 END) as con_precio,
                   ROUND(AVG(CASE WHEN price_per_m2 BETWEEN 100 AND 20000 THEN price_per_m2 END), 0) as avg_ppm2
            FROM market_listings WHERE active=1
            GROUP BY property_type ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}

$statsGlobales = $pdo ? getGlobalStats($pdo) : [];
$marketSF = $pdo ? getMarketData($pdo, 'Santa Fe', 'departamento') : [];
?>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>TasadorIA — Zonas y Precios</title>
<style>
:root{--bg:#0d0f14;--bg2:#141720;--bg3:#1c2030;--card:#1e2235;--border:#2a2f45;--gold:#c9a84c;--text:#e8e8f0;--muted:#7a7a9a;--green:#00c896;--red:#ff4f6e;--blue:#4a8ff7;--r:10px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:var(--bg);color:var(--text);padding:20px;min-height:100vh}
.wrap{max-width:1100px;margin:0 auto}
h1{color:var(--gold);font-size:22px}
h2{font-size:14px;color:var(--text);margin:20px 0 10px;text-transform:uppercase;letter-spacing:.5px;padding-bottom:6px;border-bottom:1px solid var(--border)}
a{color:var(--gold);text-decoration:none}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;padding-bottom:14px;border-bottom:1px solid var(--border)}
.msg{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:20px;background:rgba(0,200,150,.1);border:1px solid rgba(0,200,150,.4);color:var(--green)}
.login-card{max-width:380px;margin:80px auto;background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:32px}
label{display:block;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
input{width:100%;padding:10px 12px;background:var(--bg3);border:1.5px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;outline:none;margin-bottom:12px}
input:focus{border-color:var(--gold)}
.btn{padding:10px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-gold{background:var(--gold);color:#0d0f14}
.btn-sm{padding:6px 12px;font-size:12px}

/* Stats globales */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.stat{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:14px}
.stat .v{font-size:26px;font-weight:700;color:var(--gold)}
.stat .l{font-size:11px;color:var(--muted);margin-top:2px}

/* Dolar card */
.usd-card{background:var(--card);border:1px solid rgba(201,168,76,.4);border-radius:var(--r);padding:20px;margin-bottom:20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.usd-input{width:160px;padding:10px 14px;background:var(--bg3);border:2px solid var(--gold);border-radius:8px;color:var(--gold);font-size:20px;font-weight:700;text-align:center;outline:none}

/* Comparación por zona */
.zone-table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:20px}
.zone-table th{padding:8px 12px;background:var(--bg2);color:var(--muted);font-size:11px;text-align:left;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border)}
.zone-table td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.zone-table tr:hover td{background:rgba(255,255,255,.02)}
.badge-ok{background:rgba(0,200,150,.15);color:var(--green);padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
.badge-high{background:rgba(255,79,110,.15);color:var(--red);padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
.badge-low{background:rgba(74,143,247,.15);color:var(--blue);padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
.badge-nodata{background:rgba(255,255,255,.06);color:var(--muted);padding:2px 8px;border-radius:10px;font-size:11px}
.diff-arrow{font-size:16px;margin-right:4px}

/* Editor inline */
.edit-form{background:var(--bg2);border:1px solid var(--gold);border-radius:8px;padding:14px;margin-top:6px;display:none}
.edit-form.open{display:block}
.price-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.price-field{flex:1;min-width:80px}
.price-field label{margin-bottom:4px}
.price-input{width:100%;padding:8px 10px;text-align:center;font-size:15px;font-weight:700;border-radius:6px;border:2px solid var(--border);background:var(--bg3);color:var(--text);outline:none}
.price-input.min{border-color:rgba(255,79,110,.5)}
.price-input.avg{border-color:rgba(201,168,76,.6);color:var(--gold)}
.price-input.max{border-color:rgba(0,200,150,.5)}
.price-preview{font-size:11px;color:var(--muted);margin-top:4px;text-align:center}
.suggestion-box{background:rgba(74,143,247,.08);border:1px solid rgba(74,143,247,.3);border-radius:6px;padding:8px 12px;font-size:12px;color:var(--blue);margin-bottom:10px}

@media(max-width:700px){.stats-grid{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<div class="wrap">

<?php if (!$logged): ?>
<div class="login-card">
  <h1 style="text-align:center;margin-bottom:20px">TasadorIA — Zonas</h1>
  <?php if (isset($loginErr)): ?><div style="color:var(--red);font-size:13px;margin-bottom:14px"><?=htmlspecialchars($loginErr)?></div><?php endif;?>
  <form method="POST">
    <label>Contraseña</label>
    <input type="password" name="login_pass" autofocus placeholder="anper2025">
    <button type="submit" class="btn btn-gold" style="width:100%">Ingresar</button>
  </form>
</div>

<?php else: ?>
<div class="header">
  <div>
    <h1>🗺 Zonas y Precios — TasadorIA</h1>
    <div style="font-size:13px;color:var(--muted)">Comparación datos reales vs configuración · <?= count($statsGlobales) ?> tipos importados</div>
  </div>
  <div style="display:flex;gap:10px">
    <a href="admin.php" style="font-size:13px;padding:8px 14px;border:1px solid var(--border);border-radius:8px">⚙ Admin</a>
    <a href="?logout=1" style="font-size:13px;padding:8px 14px;border:1px solid var(--border);border-radius:8px;color:var(--muted)">Salir</a>
  </div>
</div>

<?php if ($msg): ?><div class="msg"><?=htmlspecialchars($msg)?></div><?php endif;?>

<!-- ── TIPO DE CAMBIO ─────────────────────────────────────────────────────── -->
<div class="usd-card">
  <div>
    <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">💱 Tipo de cambio</div>
    <div style="font-size:12px;color:var(--muted)">Afecta todos los precios en ARS del tasador</div>
  </div>
  <form method="POST" style="display:flex;align-items:center;gap:12px">
    <div style="font-size:16px;color:var(--muted)">1 USD =</div>
    <input type="number" name="usd_rate" class="usd-input" value="<?=(int)$cfg['ars_usd_rate']?>" min="100" max="9999999" step="50">
    <div style="font-size:16px;color:var(--muted)">ARS</div>
    <button type="submit" name="save_usd" class="btn btn-gold">Guardar</button>
  </form>
  <div style="font-size:13px;color:var(--muted);margin-left:auto">
    Actual: <strong style="color:var(--gold)">$<?=number_format((int)$cfg['ars_usd_rate'],0,',','.')?>/USD</strong>
  </div>
</div>

<!-- ── ESTADÍSTICAS GLOBALES BD ──────────────────────────────────────────── -->
<h2>📊 Datos importados (<?=array_sum(array_column($statsGlobales,'total'))?> propiedades totales)</h2>
<div class="stats-grid">
  <?php foreach ($statsGlobales as $s): ?>
  <div class="stat">
    <div class="v"><?=number_format($s['total'])?></div>
    <div class="l"><?=htmlspecialchars(ucfirst($s['property_type']))?></div>
    <?php if ($s['avg_ppm2']): ?>
      <div style="font-size:12px;color:var(--gold);margin-top:4px">USD <?=number_format($s['avg_ppm2'],0,',','.')?>/m²</div>
      <div style="font-size:11px;color:var(--muted)"><?=$s['con_precio']?> con precio</div>
    <?php endif;?>
  </div>
  <?php endforeach;?>
</div>

<!-- ── COMPARACIÓN POR ZONA ──────────────────────────────────────────────── -->
<?php foreach ($zones as $cityKey => $city): ?>
<?php
// Obtener datos reales de la BD para esta ciudad
$marketData = $pdo ? getMarketData($pdo, $city['label'], 'departamento') : [];
$marketByZone = [];
foreach ($marketData as $row) {
    $zoneLabel = strtolower($row['zone'] ?? $row['city'] ?? '');
    $marketByZone[$zoneLabel] = $row;
}
// También obtener por ciudad general
$marketCity = $pdo ? getMarketData($pdo, $city['label']) : [];
?>

<h2>🏙 <?=htmlspecialchars($city['label'])?></h2>
<table class="zone-table">
  <thead>
    <tr>
      <th style="width:200px">Zona</th>
      <th>Config Min</th>
      <th>Config Avg ★</th>
      <th>Config Max</th>
      <th>Real BD (deptos)</th>
      <th>Listings</th>
      <th>Diferencia</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($city['zones'] as $zoneKey => $zone):
    $cfg_avg = (int)$zone['price_m2']['avg'];
    $cfg_min = (int)$zone['price_m2']['min'];
    $cfg_max = (int)$zone['price_m2']['max'];

    // Buscar datos reales — por label, keywords, etc.
    $realData = null;
    $zLabelLow = strtolower($zone['label']);
    foreach ($marketByZone as $zLabel => $mRow) {
        if (str_contains($zLabel, strtolower(explode(' ', $zone['label'])[0])) ||
            str_contains($zLabelLow, $zLabel)) {
            $realData = $mRow; break;
        }
    }
    // Fallback: buscar por keywords
    if (!$realData && !empty($zone['keywords'])) {
        foreach ($zone['keywords'] as $kw) {
            foreach ($marketByZone as $zLabel => $mRow) {
                if (str_contains($zLabel, strtolower($kw))) {
                    $realData = $mRow; break 2;
                }
            }
        }
    }

    $realAvg   = $realData ? (int)$realData['avg_ppm2'] : null;
    $realCount = $realData ? (int)$realData['count']    : 0;

    if ($realAvg && $cfg_avg > 0) {
        $diff    = $realAvg - $cfg_avg;
        $diffPct = round(($diff / $cfg_avg) * 100);
        if (abs($diffPct) <= 10) {
            $badge = '<span class="badge-ok">OK ±' . abs($diffPct) . '%</span>';
        } elseif ($diff > 0) {
            $badge = '<span class="badge-high">▲ Real +' . abs($diffPct) . '%</span>';
        } else {
            $badge = '<span class="badge-low">▼ Real -' . abs($diffPct) . '%</span>';
        }
        // Sugerencia de ajuste
        $suggest = $realAvg;
        $sugMin  = (int)round($suggest * 0.80);
        $sugMax  = (int)round($suggest * 1.25);
    } else {
        $badge   = '<span class="badge-nodata">Sin datos</span>';
        $suggest = null;
        $sugMin  = $cfg_min; $sugMax = $cfg_max;
    }

    $formId = "form-{$cityKey}-{$zoneKey}";
  ?>
  <tr>
    <td>
      <div style="font-weight:600"><?=htmlspecialchars($zone['label'])?></div>
      <div style="font-size:11px;color:var(--muted);margin-top:2px"><?=htmlspecialchars(substr($zone['description'],0,50))?>...</div>
    </td>
    <td style="color:var(--red)">$<?=number_format($cfg_min,0,',','.')?></td>
    <td style="color:var(--gold);font-weight:700">$<?=number_format($cfg_avg,0,',','.')?></td>
    <td style="color:var(--green)">$<?=number_format($cfg_max,0,',','.')?></td>
    <td>
      <?php if ($realAvg): ?>
        <strong style="color:var(--text)">$<?=number_format($realAvg,0,',','.')?></strong>
        <?php if ($realData['std_ppm2']): ?>
          <div style="font-size:11px;color:var(--muted)">±$<?=number_format((int)$realData['std_ppm2'],0,',','.')?> std</div>
        <?php endif;?>
      <?php else: ?>
        <span style="color:var(--muted)">—</span>
      <?php endif;?>
    </td>
    <td>
      <?php if ($realCount > 0): ?>
        <span style="font-size:13px"><?=$realCount?></span>
        <div style="font-size:11px;color:var(--muted)"><?=substr($realData['last_update']??'',0,10)?></div>
      <?php else: ?>
        <span style="color:var(--muted)">0</span>
      <?php endif;?>
    </td>
    <td><?=$badge?></td>
    <td>
      <button class="btn btn-sm" style="background:var(--bg3);color:var(--muted);border:1px solid var(--border)"
              onclick="toggleEdit('<?=$formId?>')">✏ Editar</button>
    </td>
  </tr>
  <!-- Editor inline -->
  <tr id="<?=$formId?>-row" style="display:none">
    <td colspan="8" style="padding:0 12px 14px">
      <div class="edit-form open">
        <?php if ($suggest): ?>
        <div class="suggestion-box">
          💡 <strong>Sugerencia basada en <?=$realCount?> datos reales:</strong>
          Min=$<?=number_format($sugMin,0,',','.')?> · Avg=$<?=number_format($suggest,0,',','.')?> · Max=$<?=number_format($sugMax,0,',','.')?>
          <button type="button" class="btn btn-sm" style="background:rgba(74,143,247,.2);color:var(--blue);border:1px solid var(--blue);margin-left:10px"
                  onclick="fillSuggested('<?=$formId?>',<?=$sugMin?>,<?=$suggest?>,<?=$sugMax?>)">Aplicar sugerencia</button>
        </div>
        <?php endif;?>
        <form method="POST">
          <input type="hidden" name="save_zone" value="1">
          <input type="hidden" name="city_key" value="<?=$cityKey?>">
          <input type="hidden" name="zone_key" value="<?=$zoneKey?>">
          <div class="price-row">
            <div class="price-field">
              <label style="color:var(--red)">Mínimo USD/m²</label>
              <input type="number" name="price_min" class="price-input min" id="<?=$formId?>-min"
                     value="<?=$cfg_min?>" min="50" max="20000" step="50"
                     oninput="previewPrice(this,'<?=$formId?>-prev-min')">
              <div class="price-preview" id="<?=$formId?>-prev-min">65m² = $<?=number_format($cfg_min*65,0,',','.')?> USD</div>
            </div>
            <div class="price-field">
              <label style="color:var(--gold)">Promedio ★ USD/m²</label>
              <input type="number" name="price_avg" class="price-input avg" id="<?=$formId?>-avg"
                     value="<?=$cfg_avg?>" min="50" max="20000" step="50"
                     oninput="previewPrice(this,'<?=$formId?>-prev-avg')">
              <div class="price-preview" id="<?=$formId?>-prev-avg">65m² = $<?=number_format($cfg_avg*65,0,',','.')?> USD</div>
            </div>
            <div class="price-field">
              <label style="color:var(--green)">Máximo USD/m²</label>
              <input type="number" name="price_max" class="price-input max" id="<?=$formId?>-max"
                     value="<?=$cfg_max?>" min="50" max="20000" step="50"
                     oninput="previewPrice(this,'<?=$formId?>-prev-max')">
              <div class="price-preview" id="<?=$formId?>-prev-max">65m² = $<?=number_format($cfg_max*65,0,',','.')?> USD</div>
            </div>
            <div>
              <label style="color:transparent">.</label>
              <button type="submit" class="btn btn-gold">💾 Guardar zona</button>
            </div>
          </div>
        </form>
      </div>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
<?php endforeach;?>

<!-- ── DATOS REALES POR ZONA (tabla detallada) ───────────────────────────── -->
<?php if (!empty($marketSF)): ?>
<h2>📋 Distribución real en BD — Santa Fe (departamentos)</h2>
<table class="zone-table">
  <thead><tr><th>Zona/Ciudad en BD</th><th>Listings</th><th>Min USD/m²</th><th>Avg USD/m²</th><th>Max USD/m²</th><th>Std Dev</th><th>Última actualización</th></tr></thead>
  <tbody>
  <?php foreach ($marketSF as $row): ?>
  <tr>
    <td><strong><?=htmlspecialchars($row['zone'] ?: $row['city'])?></strong></td>
    <td><?=$row['count']?></td>
    <td style="color:var(--red)">$<?=number_format($row['min_ppm2'],0,',','.')?></td>
    <td style="color:var(--gold);font-weight:700">$<?=number_format($row['avg_ppm2'],0,',','.')?></td>
    <td style="color:var(--green)">$<?=number_format($row['max_ppm2'],0,',','.')?></td>
    <td style="color:var(--muted)">±<?=number_format($row['std_ppm2'],0,',','.')?></td>
    <td style="color:var(--muted);font-size:12px"><?=substr($row['last_update']??'',0,10)?></td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
<?php endif;?>

<script>
function toggleEdit(formId) {
  const row = document.getElementById(formId + '-row');
  row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
function previewPrice(input, previewId) {
  const ppm2 = parseInt(input.value) || 0;
  document.getElementById(previewId).textContent = '65m² = $' + (ppm2 * 65).toLocaleString('es-AR') + ' USD';
}
function fillSuggested(formId, min, avg, max) {
  document.getElementById(formId+'-min').value = min;
  document.getElementById(formId+'-avg').value = avg;
  document.getElementById(formId+'-max').value = max;
  ['min','avg','max'].forEach(k => {
    previewPrice(document.getElementById(formId+'-'+k), formId+'-prev-'+k);
  });
}
</script>

<?php endif;?>
</div>
</body>
</html>
