<!DOCTYPE html>
<?php
// tasador/admin_market.php — Datos de mercado + Zonaprop en vivo
// Acceso: https://anperprimo.com/tasador/admin_market.php

define('ADMIN_PASS', 'anper2025');
session_start();
if (isset($_POST['login_pass'])) { if ($_POST['login_pass'] === ADMIN_PASS) { $_SESSION['mkt_auth'] = true; } else $loginErr = 'Contraseña incorrecta'; }
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin_market.php'); exit; }
$logged = ($_SESSION['mkt_auth'] ?? false);

$cfg = require __DIR__ . '/config/settings.php';
$zones = require __DIR__ . '/config/zones.php';
$msg = '';

$pdo = null;
try { $pdo = new PDO("mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4", $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } catch (Throwable $e) {}

function dbq($p, $sql, $b=[]): array { try { $s=$p->prepare($sql); $s->execute($b); return $s->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; } }

// Estadísticas
$marketStats = $pdo ? dbq($pdo, "SELECT property_type, COUNT(*) c, ROUND(AVG(price_per_m2),0) avg, ROUND(MIN(price_per_m2),0) mn, ROUND(MAX(price_per_m2),0) mx, MAX(scraped_at) last FROM market_listings WHERE active=1 AND price_per_m2 BETWEEN 100 AND 20000 GROUP BY property_type ORDER BY c DESC") : [];
$zoneStats   = $pdo ? dbq($pdo, "SELECT city, zone, COUNT(*) c, ROUND(AVG(price_per_m2),0) avg, ROUND(MIN(price_per_m2),0) mn, ROUND(MAX(price_per_m2),0) mx FROM market_listings WHERE active=1 AND price_per_m2 BETWEEN 100 AND 20000 GROUP BY city, zone ORDER BY c DESC LIMIT 30") : [];
$totalImported = $pdo ? (int)($pdo->query("SELECT COUNT(*) FROM market_listings WHERE active=1")->fetchColumn()) : 0;

// URL del endpoint de importación
$importUrl = ($cfg['app_url'] ?? 'https://anperprimo.com/tasador') . '/api/import_market.php';

// Zonaprop URLs por zona para Santa Fe
$zonapropUrls = [
    'santa_fe_capital' => [
        'general'        => 'https://www.zonaprop.com.ar/departamentos-venta-ciudad-de-santa-fe-sf.html',
        'centro'         => 'https://www.zonaprop.com.ar/departamentos-venta-santa-fe-microcentro.html',
        'candioti_norte' => 'https://www.zonaprop.com.ar/departamentos-venta-santa-fe-candioti-norte.html',
        'candioti_sur'   => 'https://www.zonaprop.com.ar/departamentos-venta-santa-fe-candioti.html',
        'la_costanera'   => 'https://www.zonaprop.com.ar/departamentos-venta-santa-fe-barrio-sur.html',
    ],
    'buenos_aires' => [
        'general'        => 'https://www.zonaprop.com.ar/departamentos-venta-capital-federal.html',
        'palermo'        => 'https://www.zonaprop.com.ar/departamentos-venta-palermo.html',
        'recoleta'       => 'https://www.zonaprop.com.ar/departamentos-venta-recoleta.html',
        'belgrano'       => 'https://www.zonaprop.com.ar/departamentos-venta-belgrano.html',
    ],
];

$adminKey = ADMIN_PASS;
?>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>TasadorIA — Mercado en vivo</title>
<style>
:root{--bg:#0d0f14;--bg2:#141720;--bg3:#1c2030;--card:#1e2235;--border:#2a2f45;--gold:#c9a84c;--text:#e8e8f0;--muted:#7a7a9a;--green:#00c896;--red:#ff4f6e;--blue:#4a8ff7;--r:10px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:var(--bg);color:var(--text);padding:20px;min-height:100vh}
.wrap{max-width:1100px;margin:0 auto}
a{color:var(--gold)}
h1{color:var(--gold);font-size:22px}
h2{font-size:14px;text-transform:uppercase;letter-spacing:.5px;color:var(--text);margin:20px 0 12px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;padding-bottom:14px;border-bottom:1px solid var(--border)}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-bottom:16px}
.btn{padding:9px 18px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
.btn-gold{background:var(--gold);color:#0d0f14}.btn-gold:hover{background:#f0cc7a}
.btn-outline{background:transparent;border:1.5px solid var(--border);color:var(--muted)}.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.btn-blue{background:var(--blue);color:#fff}
.btn-sm{padding:6px 12px;font-size:12px}
.msg-ok{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px;background:rgba(0,200,150,.1);border:1px solid rgba(0,200,150,.4);color:var(--green)}
.login-card{max-width:380px;margin:80px auto;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:32px}
input[type=password]{width:100%;padding:10px 12px;background:var(--bg3);border:1.5px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;outline:none;margin-bottom:12px}
.tabs{display:flex;gap:4px;background:var(--bg2);border-radius:var(--r);padding:4px;border:1px solid var(--border);margin-bottom:20px}
.tab{padding:8px 16px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--muted);background:none;border:none;transition:all .15s}
.tab.active{background:var(--card);color:var(--gold)}
.panel{display:none}.panel.active{display:block}

/* Stats */
.stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.stat{background:var(--card);border:1px solid var(--border);border-radius:8px;padding:14px}
.stat .v{font-size:26px;font-weight:700;color:var(--gold)}
.stat .l{font-size:11px;color:var(--muted);margin-top:2px}

/* Tabla */
.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th{padding:8px 12px;background:var(--bg2);color:var(--muted);font-size:11px;text-align:left;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border)}
.tbl td{padding:8px 12px;border-bottom:1px solid var(--border)}
.tbl tr:hover td{background:rgba(255,255,255,.02)}
.chip{padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}

/* Bookmarklet */
.bookmarklet-box{background:linear-gradient(135deg,rgba(74,143,247,.1),rgba(201,168,76,.08));border:1px solid rgba(74,143,247,.3);border-radius:12px;padding:24px;margin-bottom:20px}
.bm-step{display:flex;align-items:flex-start;gap:14px;margin-bottom:16px}
.bm-num{width:28px;height:28px;border-radius:50%;background:var(--gold);color:#0d0f14;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px}
.bm-link{display:inline-block;background:linear-gradient(135deg,var(--gold),#f0cc7a);color:#0d0f14;padding:10px 20px;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;cursor:grab}
.bm-link:hover{background:linear-gradient(135deg,#f0cc7a,var(--gold))}

/* Comparador */
.compare-row{display:grid;grid-template-columns:200px repeat(3,1fr) repeat(2,1fr) 80px;gap:8px;align-items:center;padding:10px 12px;border-bottom:1px solid var(--border);font-size:13px}
.compare-row:first-child{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;background:var(--bg2)}
.diff-ok{color:var(--green);font-weight:700}
.diff-hi{color:var(--red);font-weight:700}
.diff-lo{color:var(--blue);font-weight:700}

/* Zonaprop live */
.zp-zone-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px;gap:12px}
.zp-status{font-size:12px;color:var(--muted);min-width:100px;text-align:right}
.zp-result{color:var(--gold);font-weight:700;min-width:100px;text-align:right}

/* Apify */
.apify-box{background:rgba(74,143,247,.06);border:1px solid rgba(74,143,247,.25);border-radius:10px;padding:16px}
</style>
</head>
<body><div class="wrap">

<?php if (!$logged): ?>
<div class="login-card">
  <h1 style="text-align:center;margin-bottom:20px">Datos de Mercado</h1>
  <?php if (isset($loginErr)): ?><div style="color:var(--red);font-size:13px;margin-bottom:12px"><?=htmlspecialchars($loginErr)?></div><?php endif;?>
  <form method="POST">
    <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Contraseña</div>
    <input type="password" name="login_pass" autofocus placeholder="anper2025">
    <button type="submit" class="btn btn-gold" style="width:100%">Ingresar</button>
  </form>
</div>

<?php else: ?>
<div class="header">
  <div><h1>📊 Datos de Mercado en Vivo</h1><div style="font-size:13px;color:var(--muted)"><?=$totalImported?> listings importados · Comparación con config</div></div>
  <div style="display:flex;gap:10px">
    <a href="admin.php" style="padding:8px 14px;border:1px solid var(--border);border-radius:8px;font-size:13px">← Admin</a>
    <a href="?logout=1" style="padding:8px 14px;border:1px solid var(--border);border-radius:8px;font-size:13px;color:var(--muted)">Salir</a>
  </div>
</div>

<div class="stat-row">
  <div class="stat"><div class="v"><?=number_format($totalImported)?></div><div class="l">Total listings BD</div></div>
  <div class="stat">
    <?php $dptos = array_filter($marketStats, fn($r) => $r['property_type']==='departamento'); $d=reset($dptos);?>
    <div class="v"><?=$d?'$'.number_format($d['avg'],0,',','.'):'—'?></div>
    <div class="l">Dptos avg USD/m²</div>
  </div>
  <div class="stat">
    <?php $casas = array_filter($marketStats, fn($r) => $r['property_type']==='casa'); $ca=reset($casas);?>
    <div class="v"><?=$ca?'$'.number_format($ca['avg'],0,',','.'):'—'?></div>
    <div class="l">Casas avg USD/m²</div>
  </div>
  <div class="stat"><div class="v"><?=count($zoneStats)?></div><div class="l">Zonas con datos</div></div>
</div>

<div class="tabs">
  <button class="tab active" onclick="showT('zonaprop',this)">🔴 Zonaprop en vivo</button>
  <button class="tab" onclick="showT('comparar',this)">📊 Comparar vs config</button>
  <button class="tab" onclick="showT('bd',this)">🗃 Datos en BD</button>
  <button class="tab" onclick="showT('bookmarklet',this)">🔖 Bookmarklet</button>
  <button class="tab" onclick="showT('apify',this)">🤖 Apify (automático)</button>
</div>

<!-- ── ZONAPROP EN VIVO ───────────────────────────────────────────────────── -->
<div class="panel active" id="tab-zonaprop">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div>
        <div style="font-size:15px;font-weight:600">🔴 Búsqueda en vivo en Zonaprop</div>
        <div style="font-size:12px;color:var(--muted);margin-top:3px">Abre Zonaprop con el bookmarklet activo → extrae precios al instante</div>
      </div>
    </div>

    <div style="background:rgba(255,79,110,.07);border:1px solid rgba(255,79,110,.25);border-radius:8px;padding:12px 16px;font-size:13px;margin-bottom:16px">
      ⚠️ <strong>Zonaprop bloquea el servidor</strong> con Cloudflare. La solución es usar tu propio browser:
      el bookmarklet (pestaña al lado) corre JavaScript <em>en tu browser</em> mientras visitás Zonaprop, extrae los precios y los manda a la BD.
    </div>

    <div style="font-size:14px;font-weight:600;margin-bottom:12px">Links directos a Zonaprop para las zonas de Santa Fe:</div>

    <?php foreach ($zonapropUrls as $cityKey => $cityZones):
      $cityLabel = $zones[$cityKey]['label'] ?? $cityKey;
    ?>
    <div style="margin-bottom:16px">
      <div style="font-size:13px;color:var(--gold);font-weight:600;margin-bottom:8px">🏙 <?=htmlspecialchars($cityLabel)?></div>
      <?php foreach ($cityZones as $zKey => $url):
        $zLabel = $zones[$cityKey]['zones'][$zKey]['label'] ?? $zKey;
        $cfgAvg = $zones[$cityKey]['zones'][$zKey]['price_m2']['avg'] ?? 0;
      ?>
      <div class="zp-zone-row">
        <div>
          <div style="font-weight:500"><?=htmlspecialchars($zLabel)?></div>
          <div style="font-size:11px;color:var(--muted)">Config: $<?=number_format($cfgAvg,0,',','.')?>/m²</div>
        </div>
        <div style="display:flex;gap:8px">
          <a href="<?=htmlspecialchars($url)?>" target="_blank" class="btn btn-sm btn-outline" title="Abrir en Zonaprop">🔍 Abrir Zonaprop</a>
          <button class="btn btn-sm" style="background:rgba(74,143,247,.15);color:var(--blue);border:1px solid rgba(74,143,247,.4)" onclick="scrapeZP('<?=$cityKey?>','<?=$zKey?>','<?=htmlspecialchars($url)?>',this)">⚡ Extraer con bookmarklet</button>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <?php endforeach;?>

    <div id="zp-log" style="display:none;margin-top:12px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:12px;font-size:12px;font-family:monospace;color:#aaa;max-height:200px;overflow-y:auto"></div>
  </div>
</div>

<!-- ── COMPARAR ───────────────────────────────────────────────────────────── -->
<div class="panel" id="tab-comparar">
  <div class="card">
    <div style="font-size:15px;font-weight:600;margin-bottom:4px">Configurado vs Datos reales en BD</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:16px">
      Los datos reales son los que importaste (Litoral Propiedades + lo que vayas agregando de Zonaprop).<br>
      <strong style="color:var(--gold)">El motor usa: 60% datos reales (si hay ≥5) + 40% config</strong>
    </div>

    <?php
    $zoneIdx = [];
    foreach ($zoneStats as $row) {
      $k = strtolower($row['zone'] ?? $row['city']);
      $zoneIdx[$k] = $row;
    }

    foreach ($zones as $cityKey => $city):
    ?>
    <div style="font-size:14px;font-weight:600;color:var(--gold);margin:16px 0 10px">🏙 <?=htmlspecialchars($city['label'])?></div>
    <div class="compare-row" style="">
      <div>Zona</div><div>Cfg min</div><div>Cfg avg ★</div><div>Cfg max</div>
      <div>Real BD avg</div><div>N</div><div>Dif %</div>
    </div>
    <?php foreach ($city['zones'] as $zk => $zone):
      $cMin=(int)$zone['price_m2']['min']; $cAvg=(int)$zone['price_m2']['avg']; $cMax=(int)$zone['price_m2']['max'];

      $real = null;
      $zLow = strtolower($zone['label']);
      foreach ($zoneIdx as $k => $r) {
        if (str_contains($zLow, $k) || str_contains($k, explode(' ',$zLow)[0])) { $real=$r; break; }
      }
      if (!$real) foreach ($zone['keywords'] as $kw) foreach ($zoneIdx as $k=>$r) if (str_contains($k, strtolower($kw))) { $real=$r; break 2; }

      $rAvg = $real ? (int)$real['avg'] : null;
      $rCnt = $real ? (int)$real['c'] : 0;

      if ($rAvg && $cAvg) {
        $pct = round(($rAvg-$cAvg)/$cAvg*100);
        if (abs($pct)<=10) $diffHtml='<span class="diff-ok">✓ ±'.abs($pct).'%</span>';
        elseif ($pct>0) $diffHtml='<span class="diff-hi">▲ +'.$pct.'%</span>';
        else $diffHtml='<span class="diff-lo">▼ '.$pct.'%</span>';
      } else { $diffHtml='<span style="color:var(--muted)">—</span>'; }

      $motorPpm2 = ($rAvg && $rCnt >= 5) ? round($rAvg*.6 + $cAvg*.4) : $cAvg;
    ?>
    <div class="compare-row">
      <div style="font-weight:500"><?=htmlspecialchars($zone['label'])?><div style="font-size:11px;color:var(--blue)">Motor: $<?=number_format($motorPpm2,0,',','.')?>/m²</div></div>
      <div style="color:var(--red)">$<?=number_format($cMin,0,',','.')?></div>
      <div style="color:var(--gold);font-weight:700">$<?=number_format($cAvg,0,',','.')?></div>
      <div style="color:var(--green)">$<?=number_format($cMax,0,',','.')?></div>
      <div><?=$rAvg?'<strong>$'.number_format($rAvg,0,',','.').'</strong>':'<span style="color:var(--muted)">—</span>'?></div>
      <div style="color:var(--muted)"><?=$rCnt?></div>
      <div><?=$diffHtml?></div>
    </div>
    <?php endforeach; endforeach; ?>
    <div style="margin-top:16px;font-size:12px;color:var(--muted);padding:10px;background:var(--bg2);border-radius:6px">
      💡 <strong>Precio "Motor"</strong> = lo que usa el algoritmo ahora mismo para calcular tasaciones.<br>
      Si hay ≥5 datos en BD para esa zona → 60% real + 40% config. Si hay menos → 100% config.<br>
      Para actualizar el config con los datos reales → ir a <a href="admin.php#precios">Admin → Precios y Zonas → Aplicar sugerencia</a>
    </div>
  </div>
</div>

<!-- ── BD ─────────────────────────────────────────────────────────────────── -->
<div class="panel" id="tab-bd">
  <div class="card">
    <div style="font-size:15px;font-weight:600;margin-bottom:16px">🗃 Datos importados en BD</div>
    <table class="tbl">
      <thead><tr><th>Tipo</th><th>Cantidad</th><th>Min USD/m²</th><th>Avg USD/m²</th><th>Max USD/m²</th><th>Última actualización</th></tr></thead>
      <tbody>
      <?php foreach ($marketStats as $s): ?>
      <tr><td><?=htmlspecialchars(ucfirst($s['property_type']))?></td><td><strong><?=number_format($s['c'])?></strong></td><td style="color:var(--red)">$<?=number_format($s['mn'],0,',','.')?></td><td style="color:var(--gold);font-weight:700">$<?=number_format($s['avg'],0,',','.')?></td><td style="color:var(--green)">$<?=number_format($s['mx'],0,',','.')?></td><td style="color:var(--muted);font-size:12px"><?=substr($s['last'],0,10)?></td></tr>
      <?php endforeach;?>
      </tbody>
    </table>

    <div style="font-size:14px;font-weight:600;margin:20px 0 10px">Por zona (departamentos)</div>
    <table class="tbl">
      <thead><tr><th>Ciudad</th><th>Zona en BD</th><th>N</th><th>Min</th><th>Avg ★</th><th>Max</th></tr></thead>
      <tbody>
      <?php foreach ($zoneStats as $row): ?>
      <tr><td style="color:var(--muted)"><?=htmlspecialchars($row['city']??'—')?></td><td style="font-weight:500"><?=htmlspecialchars($row['zone']??'—')?></td><td><strong><?=$row['c']?></strong></td><td style="color:var(--red)">$<?=number_format($row['mn'],0,',','.')?></td><td style="color:var(--gold);font-weight:700">$<?=number_format($row['avg'],0,',','.')?></td><td style="color:var(--green)">$<?=number_format($row['mx'],0,',','.')?></td></tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── BOOKMARKLET ────────────────────────────────────────────────────────── -->
<div class="panel" id="tab-bookmarklet">
  <div class="bookmarklet-box">
    <div style="font-size:16px;font-weight:700;color:var(--gold);margin-bottom:6px">🔖 Zonaprop → BD en un clic</div>
    <div style="font-size:13px;color:var(--muted);margin-bottom:20px">
      Funciona en TU browser (no en el servidor), por eso Cloudflare no lo bloquea.<br>
      Extraés precios de cualquier búsqueda de Zonaprop y los mandás directo a la BD del tasador.
    </div>

    <div class="bm-step">
      <div class="bm-num">1</div>
      <div>
        <div style="font-size:14px;font-weight:600;margin-bottom:6px">Arrastrá este botón a tu barra de favoritos:</div>
        <a class="bm-link" href="javascript:(function(){var s=document.createElement('script');s.src='<?=($cfg['app_url']??'https://anperprimo.com/tasador')?>/zp_extractor.js?key=<?=$adminKey?>&t='+Date.now();document.head.appendChild(s);})();" onclick="alert('¡No hacer clic! Arrastrarlo a la barra de favoritos del browser');return false;">
          📊 Extraer → TasadorIA
        </a>
        <div style="font-size:12px;color:var(--muted);margin-top:8px">⬆ Arrastrá este botón dorado a tu barra de favoritos (bookmarks bar)</div>
      </div>
    </div>

    <div class="bm-step">
      <div class="bm-num">2</div>
      <div>
        <div style="font-size:14px;font-weight:600;margin-bottom:4px">Ir a Zonaprop y hacer una búsqueda:</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
          <?php foreach ($zonapropUrls['santa_fe_capital'] as $zk => $url): ?>
          <a href="<?=$url?>" target="_blank" class="btn btn-sm btn-outline"><?=htmlspecialchars($zones['santa_fe_capital']['zones'][$zk]['label'] ?? $zk)?> ↗</a>
          <?php endforeach;?>
        </div>
      </div>
    </div>

    <div class="bm-step">
      <div class="bm-num">3</div>
      <div>
        <div style="font-size:14px;font-weight:600">Clic en el bookmarklet "📊 Extraer → TasadorIA"</div>
        <div style="font-size:13px;color:var(--muted);margin-top:4px">Aparece un popup confirmando cuántos listings se extrajeron y enviaron a la BD.</div>
      </div>
    </div>

    <div style="background:rgba(0,200,150,.07);border:1px solid rgba(0,200,150,.25);border-radius:8px;padding:12px 16px;font-size:13px;margin-top:10px">
      ✓ Después del clic, volvé a la pestaña <strong>Comparar</strong> para ver los nuevos datos vs config.
    </div>
  </div>

  <!-- Código del extractor JS -->
  <div class="card">
    <div style="font-size:13px;font-weight:600;margin-bottom:10px">⚙️ Código del extractor (se sirve como zp_extractor.js)</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:10px">Crear el archivo <code>tasador/zp_extractor.js</code> en el servidor con este contenido:</div>
    <pre id="extractorCode" style="background:#0a0c10;border:1px solid var(--border);border-radius:8px;padding:14px;font-size:12px;color:#aaa;overflow-x:auto;white-space:pre-wrap;max-height:400px;overflow-y:auto"></pre>
    <button class="btn btn-outline btn-sm" style="margin-top:10px" onclick="copyCode()">📋 Copiar código</button>
    <a href="?download_extractor=1" class="btn btn-gold btn-sm" style="margin-left:8px">⬇ Descargar zp_extractor.js</a>
  </div>
</div>

<!-- ── APIFY ──────────────────────────────────────────────────────────────── -->
<div class="panel" id="tab-apify">
  <div class="apify-box" style="margin-bottom:16px">
    <div style="font-size:15px;font-weight:700;color:var(--blue);margin-bottom:6px">🤖 Apify — Scraping automático de Zonaprop</div>
    <div style="font-size:13px;color:var(--muted);margin-bottom:12px">
      Apify tiene un scraper específico para Zonaprop.com.ar que funciona sin ser bloqueado (usa proxies argentinos).<br>
      Precio: <strong style="color:var(--gold)">$2.50 por 1.000 resultados</strong> — para 1.500 dptos SF = $3.75 USD.
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="https://apify.com/ecomscrape/zonaprop-property-listings-scraper" target="_blank" class="btn btn-blue btn-sm">Ver scraper en Apify ↗</a>
      <a href="https://apify.com/ocrad/zonaprop-property-scraper" target="_blank" class="btn btn-outline btn-sm">Alternativa (sin proxy) ↗</a>
    </div>
  </div>

  <div class="card">
    <div style="font-size:14px;font-weight:600;margin-bottom:12px">Integración con Apify API</div>
    <div style="font-size:13px;color:var(--muted);margin-bottom:16px">Una vez que tenés un API token de Apify, el tasador puede disparar el scraping y recibir los resultados automáticamente.</div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">
      <div>
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Apify API Token</div>
        <input type="text" id="apify-token" placeholder="apify_api_XXXXXXXXX" style="width:100%;padding:9px 12px;background:var(--bg3);border:1.5px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;outline:none">
      </div>
      <div>
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">URL de búsqueda Zonaprop</div>
        <input type="text" id="apify-url" value="https://www.zonaprop.com.ar/departamentos-venta-ciudad-de-santa-fe-sf.html" style="width:100%;padding:9px 12px;background:var(--bg3);border:1.5px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;outline:none">
      </div>
    </div>
    <button class="btn btn-blue" onclick="runApify()">🤖 Ejecutar scraping</button>
    <div id="apify-status" style="display:none;margin-top:12px;font-size:13px;padding:10px 14px;background:var(--bg2);border-radius:8px;color:var(--muted)"></div>
  </div>

  <div class="card">
    <div style="font-size:13px;font-weight:600;margin-bottom:8px">⚡ Flujo recomendado (mensual, 5 min)</div>
    <div style="font-size:13px;color:var(--muted);line-height:2">
      1. Ir a Apify → ejecutar scraper de Zonaprop con las URLs de SF<br>
      2. Descargar resultado como CSV o JSON<br>
      3. Admin → Importar XML → subir el JSON<br>
      4. El motor del tasador se actualiza automáticamente<br>
      <br>
      O configurar un <strong>webhook de Apify</strong> que mande los resultados directo a <code>api/import_market.php</code>
    </div>
  </div>
</div>

<script>
function showT(id, btn) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  if (btn) btn.classList.add('active');
}

// ── Código del extractor ──────────────────────────────────────────────────────
const IMPORT_URL = '<?=$importUrl?>';
const ADMIN_KEY  = '<?=$adminKey?>';

const extractorCode = `// TasadorIA — Zonaprop Extractor
// Archivo: tasador/zp_extractor.js
// Uso: bookmarklet que corre en el browser cuando estás en zonaprop.com.ar

(function() {
  var IMPORT_URL = '${IMPORT_URL}';
  var ADMIN_KEY  = '${ADMIN_KEY}';

  // Detectar que estamos en Zonaprop
  if (!window.location.hostname.includes('zonaprop')) {
    alert('Este bookmarklet solo funciona en zonaprop.com.ar');
    return;
  }

  var listings = [];
  var arsRate = 1450;

  // MÉTODO 1: Extraer de __PRELOADED_STATE__ (Next.js)
  try {
    var scripts = document.querySelectorAll('script[type="application/json"], script#__NEXT_DATA__');
    for (var sc of scripts) {
      try {
        var json = JSON.parse(sc.textContent);
        // Buscar listado de propiedades en el JSON
        var items = null;
        if (json?.props?.pageProps?.listPostings) items = json.props.pageProps.listPostings;
        else if (json?.props?.initialProps?.listPostings) items = json.props.initialProps.listPostings;

        if (items && items.length > 0) {
          items.forEach(function(p) {
            var price = 0, currency = 'USD';
            if (p.price) {
              if (typeof p.price === 'object') { price = parseFloat(p.price.amount || p.price.value || 0); currency = p.price.currency || 'USD'; }
              else price = parseFloat(p.price);
            }
            var area = parseFloat(p.totalArea || p.roofedArea || p.surface || 0);
            if (price > 0 && area > 0) {
              var priceUSD = currency === 'ARS' ? price / arsRate : price;
              listings.push({
                source: 'zonaprop_bm',
                title: p.title || p.address || '',
                price: price,
                currency: currency,
                covered_area: area,
                bedrooms: p.bedrooms || p.rooms || null,
                address: p.address || p.title || '',
                city: p.postingLocation?.city?.name || 'Santa Fe',
                zone: p.postingLocation?.neighborhood?.name || '',
                lat: parseFloat(p.geo?.lat || 0) || null,
                lng: parseFloat(p.geo?.lon || 0) || null,
                property_type: (p.propertyType || 'departamento').toLowerCase(),
                operation: (p.operationType || 'venta').toLowerCase() === 'sale' ? 'venta' : 'alquiler',
                url: 'https://www.zonaprop.com.ar' + (p.url || ''),
                scraped_at: new Date().toISOString().slice(0,19).replace('T',' ')
              });
            }
          });
          break;
        }
      } catch(e) {}
    }
  } catch(e) {}

  // MÉTODO 2: Extraer del HTML si método 1 no funciona
  if (listings.length === 0) {
    var priceEls = document.querySelectorAll('[data-price],[class*="price"],[class*="Price"],[class*="precio"]');
    var prices = [];
    priceEls.forEach(function(el) {
      var txt = el.textContent.trim();
      var match = txt.match(/(?:USD|U\\$D|\\$)?\\s*([\\d\\.]+)/);
      if (match) {
        var p = parseFloat(match[1].replace(/\\./g,''));
        if (p > 5000 && p < 10000000) prices.push(p);
      }
    });
    prices.forEach(function(p) {
      listings.push({ source:'zonaprop_bm', price:p, currency:'USD', covered_area:null, city:'Santa Fe', zone:'', property_type:'departamento', operation:'venta', scraped_at:new Date().toISOString().slice(0,19).replace('T',' ') });
    });
  }

  if (listings.length === 0) {
    alert('No se encontraron datos en esta página. Probá navegar a una búsqueda de departamentos.');
    return;
  }

  // Confirmar antes de enviar
  if (!confirm('Se encontraron ' + listings.length + ' propiedades.\\n¿Enviar a TasadorIA?')) return;

  // Enviar al servidor
  fetch(IMPORT_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Admin-Key': ADMIN_KEY },
    body: JSON.stringify({ source: 'zonaprop_bookmarklet', ars_usd_rate: arsRate, listings: listings })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    alert('✅ Importados: ' + (d.inserted || 0) + ' nuevos, ' + (d.updated || 0) + ' actualizados, ' + (d.errors || 0) + ' errores.');
  })
  .catch(function(e) {
    alert('Error al enviar: ' + e.message);
  });
})();`;

document.getElementById('extractorCode').textContent = extractorCode;

function copyCode() {
  navigator.clipboard.writeText(document.getElementById('extractorCode').textContent)
    .then(() => alert('✅ Código copiado al portapapeles'));
}

// ── Botón extraer en línea ────────────────────────────────────────────────────
function scrapeZP(cityKey, zoneKey, url, btn) {
  btn.textContent = '⏳ Abrí el link y usá el bookmarklet...';
  window.open(url, '_blank');
  setTimeout(() => { btn.textContent = '⚡ Extraer con bookmarklet'; }, 3000);
  var log = document.getElementById('zp-log');
  log.style.display = 'block';
  log.innerHTML += '<p>→ Abriendo Zonaprop para ' + zoneKey + '... Usá el bookmarklet en esa pestaña.</p>';
}

// ── Apify ─────────────────────────────────────────────────────────────────────
async function runApify() {
  const token = document.getElementById('apify-token').value.trim();
  const url   = document.getElementById('apify-url').value.trim();
  const status = document.getElementById('apify-status');

  if (!token) { alert('Ingresá el API token de Apify primero'); return; }

  status.style.display = 'block';
  status.textContent = '⏳ Iniciando scraping en Apify...';

  try {
    // Llamar a Apify API para ejecutar el scraper
    const run = await fetch('https://api.apify.com/v2/acts/ecomscrape~zonaprop-property-listings-scraper/runs?token=' + token, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ urls: [url], maxItems: 500 })
    });
    const runData = await run.json();
    if (!runData.data?.id) throw new Error(runData.error?.message || 'No se pudo iniciar');

    const runId = runData.data.id;
    status.textContent = '⏳ Scraping iniciado (ID: ' + runId + '). Esperando resultados...';

    // Polling cada 15 segundos
    const poll = async () => {
      const statusR = await fetch('https://api.apify.com/v2/actor-runs/' + runId + '?token=' + token);
      const sd = await statusR.json();
      const st = sd.data?.status;
      status.textContent = '⏳ Estado: ' + st + '...';

      if (st === 'SUCCEEDED') {
        // Obtener resultados
        const resultsR = await fetch('https://api.apify.com/v2/actor-runs/' + runId + '/dataset/items?token=' + token + '&format=json');
        const results = await resultsR.json();

        // Convertir al formato de importación
        const listings = results.map(p => ({
          source: 'apify_zonaprop',
          title: p.Title || p.title || '',
          price: parseFloat(p.Price || p.price || 0),
          currency: p.Currency || p.currency || 'USD',
          covered_area: parseFloat(p['Total area'] || p.totalArea || p.size || 0) || null,
          bedrooms: parseInt(p.Rooms || p.rooms || 0) || null,
          address: p.Address || p.address || '',
          city: p.City || 'Santa Fe',
          zone: p.Neighborhood || p.zone || '',
          lat: parseFloat(p.Latitude || 0) || null,
          lng: parseFloat(p.Longitude || 0) || null,
          property_type: 'departamento',
          operation: 'venta',
          url: p.URL || p.url || '',
          scraped_at: new Date().toISOString().slice(0,19).replace('T',' ')
        })).filter(p => p.price > 0);

        // Importar
        const importR = await fetch('<?=$importUrl?>', {
          method: 'POST',
          headers: {'Content-Type':'application/json','X-Admin-Key':'<?=$adminKey?>'},
          body: JSON.stringify({source:'apify_zonaprop', listings})
        });
        const importD = await importR.json();
        status.textContent = '✅ Importados ' + (importD.inserted||0) + ' nuevos de Zonaprop via Apify (' + results.length + ' scrapeados)';
        status.style.color = 'var(--green)';
      } else if (st === 'FAILED' || st === 'ABORTED') {
        status.textContent = '❌ Scraping falló: ' + st;
      } else {
        setTimeout(poll, 15000);
      }
    };
    setTimeout(poll, 10000);
  } catch(e) {
    status.textContent = '❌ Error: ' + e.message;
  }
}
</script>

<?php endif;?>
</div>
</body>
</html>
