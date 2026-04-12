<?php
/**
 * TasadorIA — admin_bim.php
 * Panel BIM: Mapa de calor de costos de construcción por zona
 * Herramienta para desarrolladores e inmobiliarias
 */
session_start();
$cfg = is_file(__DIR__.'/config/settings.php') ? require __DIR__.'/config/settings.php' : [];
$zones_all = is_file(__DIR__.'/config/zones.php') ? require __DIR__.'/config/zones.php' : [];

// Auth simple — acepta la sesión de admin.php (ta_admin) o login propio (bim_ok)
if (!defined('ADMIN_PASS')) define('ADMIN_PASS', $cfg['admin_password'] ?? 'anper2025');
$pass = ADMIN_PASS;
if (!isset($_SESSION['bim_ok']) && !isset($_SESSION['ta_admin'])) {
    if (($_POST['p'] ?? '') === $pass) { $_SESSION['bim_ok'] = true; }
    else {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>BIM Login</title>
        <style>*{box-sizing:border-box}body{font-family:system-ui;background:#0f0f0f;color:#e0e0e0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
        form{background:#1a1a1a;padding:32px;border-radius:14px;border:1px solid #2a2a2a;min-width:280px;text-align:center}
        h2{margin:0 0 20px;color:#c9a84c;font-size:18px}
        input{width:100%;padding:10px 14px;background:#111;border:1px solid #333;border-radius:8px;color:#fff;font-size:14px;margin-bottom:12px}
        button{width:100%;padding:10px;background:#c9a84c;color:#000;font-weight:700;border:none;border-radius:8px;cursor:pointer;font-size:14px}
        </style></head><body>
        <form method="post"><h2>🏗 TasadorIA BIM</h2>
        <input type="password" name="p" placeholder="Contraseña admin" autofocus>
        <button>Ingresar</button></form></body></html>';
        exit;
    }
}

// ── Handler AJAX: guardar costos inline ──────────────────────────────────────
if (($_GET['action'] ?? '') === 'save_costs') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $city  = strtolower(trim($body['city'] ?? ''));
    $zone  = strtolower(trim($body['zone'] ?? ''));
    $costs = is_array($body['costs'] ?? null) ? $body['costs'] : [];
    if (!$city || empty($costs)) {
        echo json_encode(['success'=>false,'error'=>'Datos incompletos']); exit;
    }
    $updated = 0;
    try {
        $pdoSave = new PDO("mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
            $cfg['db']['user'], $cfg['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach ($costs as $quality => $val) {
            $val = floatval($val);
            if ($val <= 0) continue;
            $valid = ['economica','estandar','calidad','premium'];
            if (!in_array($quality, $valid)) continue;
            // UPSERT
            $st = $pdoSave->prepare("
                INSERT INTO construction_zone_costs (city, zone, quality, cost_usd_m2)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE cost_usd_m2 = VALUES(cost_usd_m2)
            ");
            $st->execute([$city, $zone, $quality, $val]);
            $updated++;
        }
        echo json_encode(['success'=>true,'updated'=>$updated]);
    } catch (\Throwable $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Cargar costos de construcción desde BD
$pdo = null;
$dbCosts = [];
if (!empty($cfg['db']['host'])) {
    try {
        $pdo = new PDO("mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
            $cfg['db']['user'], $cfg['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $rows = $pdo->query("SELECT city, zone, quality, cost_usd_m2, labor_pct FROM construction_zone_costs ORDER BY city, zone, quality")->fetchAll();
        foreach ($rows as $r) {
            $dbCosts[$r['city']][$r['zone']][$r['quality']] = ['cost' => (float)$r['cost_usd_m2'], 'labor' => (float)$r['labor_pct']];
        }
    } catch (\Throwable $e) { $pdo = null; }
}

// Fallback defaults si no hay BD
$qualityDefaults = ['economica' => 550, 'estandar' => 950, 'calidad' => 1350, 'premium' => 2100];

function getCost(array $dbCosts, string $city, string $zone, string $quality, array $defaults): float {
    return $dbCosts[$city][$zone][$quality]['cost']
        ?? $dbCosts[$city][''][$quality]['cost']
        ?? $dbCosts[''][''][$quality]['cost']
        ?? $defaults[$quality]
        ?? 950.0;
}

// Construir dataset para el mapa: todas las zonas con sus coords y costos
$mapData = [];
foreach ($zones_all as $cityKey => $cityData) {
    if (empty($cityData['zones'])) continue;
    $cityLabel = $cityData['label'] ?? $cityKey;
    $bounds = $cityData['bounds'] ?? null;
    foreach ($cityData['zones'] as $zoneKey => $zoneData) {
        if ($zoneKey === 'general') continue; // saltar zona genérica
        $coords = $zoneData['coords'] ?? null;
        if (!$coords) continue;
        $costs = [];
        foreach (['economica','estandar','calidad','premium'] as $q) {
            $costs[$q] = getCost($dbCosts, $cityKey, $zoneKey, $q, $qualityDefaults);
        }
        $priceMin = $zoneData['price_m2']['min'] ?? 0;
        $priceAvg = $zoneData['price_m2']['avg'] ?? 0;
        $priceMax = $zoneData['price_m2']['max'] ?? 0;
        // Ratio comprar vs construir (estandar)
        $ratio = $priceAvg > 0 && $costs['estandar'] > 0 ? round($priceAvg / $costs['estandar'], 2) : null;
        $mapData[] = [
            'city'       => $cityKey,
            'cityLabel'  => $cityLabel,
            'zone'       => $zoneKey,
            'zoneLabel'  => $zoneData['label'] ?? $zoneKey,
            'lat'        => (float)$coords['lat'],
            'lng'        => (float)$coords['lng'],
            'costs'      => $costs,
            'price_min'  => $priceMin,
            'price_avg'  => $priceAvg,
            'price_max'  => $priceMax,
            'ratio'      => $ratio,
            'desc'       => $zoneData['description'] ?? '',
            'hasBdData'  => isset($dbCosts[$cityKey][$zoneKey]),
        ];
    }
}

$usdRate = (float)($cfg['ars_usd_rate'] ?? 1450);
$mapDataJson = json_encode($mapData, JSON_UNESCAPED_UNICODE);
$dbStatus = $pdo ? 'Conectada' : 'Sin BD — usando valores por defecto';

// Lista de materiales para scraping client-side
$materialsForScraping = [];
if ($pdo) {
    try {
        $rows = $pdo->query("SELECT id, material, price_ars FROM construction_materials WHERE active=1 ORDER BY id")->fetchAll();
        foreach ($rows as $r) {
            $materialsForScraping[] = ['id'=>(int)$r['id'],'name'=>$r['material'],'old_ars'=>(float)$r['price_ars']];
        }
    } catch (\Throwable $e) {}
}
$materialsJson = json_encode($materialsForScraping, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>BIM · Mapa de Calor de Construcción — TasadorIA</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
:root{
  --bg:#0f0f0f;--bg2:#181818;--bg3:#222;--surface:#1e1e1e;--border:#2a2a2a;
  --gold:#c9a84c;--text:#e0e0e0;--muted:#888;
  --green:#4caf50;--red:#f44336;--blue:#4a8ff7;
  --font:system-ui,-apple-system,sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}

/* TOP BAR */
.topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.topbar h1{font-size:15px;font-weight:700;color:var(--gold);white-space:nowrap}
.topbar .meta{font-size:11px;color:var(--muted)}
.ctrl-group{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.ctrl-group label{font-size:11px;color:var(--muted);white-space:nowrap}
select,button.btn{padding:6px 12px;border-radius:7px;border:1px solid var(--border);background:var(--bg3);color:var(--text);font-size:12px;cursor:pointer;font-family:var(--font)}
button.btn{font-weight:600}
button.btn.active,button.btn:hover{border-color:var(--gold);color:var(--gold)}
button.btn-primary{background:var(--gold);color:#000;border-color:var(--gold)}
button.btn-primary:hover{opacity:.85}
.badge-db{font-size:10px;padding:2px 8px;border-radius:10px;background:rgba(76,175,80,.15);color:#4caf50;border:1px solid rgba(76,175,80,.3)}
.badge-db.nobd{background:rgba(244,67,54,.1);color:#f44336;border-color:rgba(244,67,54,.3)}

/* MAIN LAYOUT */
.main{display:grid;grid-template-columns:1fr 360px}
#map{width:100%;background:#111;z-index:0}
/* altura se setea por JS para garantizar que Leaflet lo reciba correctamente */

/* SIDEBAR */
.sidebar{display:flex;flex-direction:column;border-left:1px solid var(--border);overflow:hidden;background:var(--bg2)}
.sidebar-header{padding:14px 16px;border-bottom:1px solid var(--border);font-size:12px;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:.5px}
.sidebar-scroll{flex:1;overflow-y:auto;padding:14px 16px}

/* ZONA CARD (sidebar) */
.zone-card{background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:12px}
.zone-card h3{font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px}
.zone-card .city-tag{font-size:10px;color:var(--muted);margin-bottom:10px}
.cost-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px}
.cost-cell{background:var(--bg);border-radius:6px;padding:7px 10px;text-align:center}
.cost-cell .qlabel{font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
.cost-cell .qval{font-size:14px;font-weight:700;color:var(--gold)}
.cost-cell .qval.eco{color:#4caf50}
.cost-cell .qval.std{color:var(--gold)}
.cost-cell .qval.cal{color:#ff9800}
.cost-cell .qval.prm{color:#f44336}
.ratio-bar{margin-top:8px}
.ratio-label{font-size:10px;color:var(--muted);margin-bottom:4px}
.ratio-track{background:var(--bg);border-radius:4px;height:8px;overflow:hidden}
.ratio-fill{height:100%;border-radius:4px;transition:width .3s}
.ratio-txt{font-size:11px;font-weight:600;margin-top:3px}

/* LEYENDA */
.legend-box{background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:10px 14px;margin-bottom:12px}
.legend-box h4{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:8px}
.legend-scale{display:flex;border-radius:4px;overflow:hidden;height:14px;margin-bottom:5px}
.legend-scale div{flex:1}
.legend-labels{display:flex;justify-content:space-between;font-size:10px;color:var(--muted)}

/* RESUMEN TABLA */
.summary-section{padding:14px 16px;border-top:1px solid var(--border)}
.summary-section h4{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:10px}
table.summary{width:100%;border-collapse:collapse;font-size:11px}
table.summary th{color:var(--muted);font-weight:600;padding:4px 6px;text-align:left;border-bottom:1px solid var(--border);font-size:10px;text-transform:uppercase;letter-spacing:.4px}
table.summary td{padding:5px 6px;border-bottom:1px solid rgba(255,255,255,.04)}
table.summary tr:hover td{background:rgba(255,255,255,.03)}
.color-dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:5px;vertical-align:middle}

/* LEAFLET OVERRIDES */
.leaflet-popup-content-wrapper{background:var(--bg3)!important;border:1px solid var(--border)!important;color:var(--text)!important;border-radius:10px!important;box-shadow:0 8px 32px rgba(0,0,0,.6)!important}
.leaflet-popup-tip{background:var(--bg3)!important}
.leaflet-popup-content{font-family:var(--font)!important;font-size:12px!important;margin:14px 16px!important;line-height:1.5}
.leaflet-container{background:#111!important}
.leaflet-tile{filter:brightness(.5) saturate(.6)}

/* RESPONSIVE */
@media(max-width:900px){.main{grid-template-columns:1fr;grid-template-rows:55vh 1fr}}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <h1>🏗 BIM · Mapa de Calor de Construcción</h1>
  <span class="badge-db <?=$pdo?'':'nobd'?>"><?=htmlspecialchars($dbStatus)?></span>

  <div class="ctrl-group">
    <label>Ciudad:</label>
    <select id="sel-city" onchange="filterCity()">
      <option value="">Todas</option>
      <?php foreach($zones_all as $ck=>$cd): ?>
      <option value="<?=htmlspecialchars($ck)?>"><?=htmlspecialchars($cd['label']??$ck)?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="ctrl-group">
    <label>Calidad:</label>
    <select id="sel-quality" onchange="refreshMap()">
      <option value="economica">Económica</option>
      <option value="estandar" selected>Estándar</option>
      <option value="calidad">Calidad</option>
      <option value="premium">Premium</option>
    </select>
  </div>

  <div class="ctrl-group">
    <label>Mapa de calor:</label>
    <button class="btn active" id="mode-cost" onclick="setMode('cost')">USD/m² Construcción</button>
    <button class="btn" id="mode-price" onclick="setMode('price')">USD/m² Mercado</button>
    <button class="btn" id="mode-ratio" onclick="setMode('ratio')">Comprar vs Construir</button>
  </div>

  <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
    <a href="admin.php" style="text-decoration:none"><button class="btn">← Admin</button></a>
    <button class="btn" onclick="document.getElementById('help-panel').style.display='flex'" title="Ver instrucciones de actualización de precios" style="font-weight:700">? Ayuda</button>
    <button class="btn" onclick="openIccModal()" title="Actualizar precios por ICC INDEC (inflación oficial)">📈 ICC INDEC</button>
    <a id="bim-bookmarklet"
       href="javascript:%21async%20function%28%29%7B%22use%20strict%22%3Bconst%20e%3D%22https%3A%2F%2Flistado.mercadolibre.com.ar%22%3Bif%28%21location.hostname.includes%28%22mercadolibre.com.ar%22%29%29%7Bconst%20e%3Ddocument.createElement%28%22div%22%29%3Breturn%20e.style.cssText%3D%22%5Cn%20%20%20%20%20%20position%3Afixed%3Btop%3A0%3Bleft%3A0%3Bright%3A0%3Bbottom%3A0%3Bbackground%3Argba%280%2C0%2C0%2C.85%29%3B%5Cn%20%20%20%20%20%20z-index%3A9999999%3Bdisplay%3Aflex%3Balign-items%3Acenter%3Bjustify-content%3Acenter%3B%5Cn%20%20%20%20%20%20font-family%3Asystem-ui%2Csans-serif%3B%5Cn%20%20%20%20%22%2Ce.innerHTML%3D%27%5Cn%20%20%20%20%20%20%3Cdiv%20style%3D%22background%3A%231a1a1a%3Bborder%3A2px%20solid%20%23c9a84c%3Bborder-radius%3A14px%3Bpadding%3A32px%3Bmax-width%3A420px%3Btext-align%3Acenter%22%3E%5Cn%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22font-size%3A40px%3Bmargin-bottom%3A12px%22%3E%F0%9F%8F%97%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22font-size%3A16px%3Bfont-weight%3A700%3Bcolor%3A%23c9a84c%3Bmargin-bottom%3A8px%22%3ETasadorIA%20%E2%80%94%20Materiales%20BIM%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22color%3A%23aaa%3Bfont-size%3A13px%3Bline-height%3A1.6%3Bmargin-bottom%3A20px%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20Este%20bookmarklet%20debe%20ejecutarse%20%3Cstrong%20style%3D%22color%3A%23fff%22%3Edesde%20MercadoLibre%3C%2Fstrong%3E.%3Cbr%3E%5Cn%20%20%20%20%20%20%20%20%20%20Hac%C3%A9%20clic%20en%20el%20bot%C3%B3n%20para%20ir%20a%20ML%20y%20ejecutarlo%20autom%C3%A1ticamente.%5Cn%20%20%20%20%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%3Ca%20href%3D%22https%3A%2F%2Fwww.mercadolibre.com.ar%2F%3Fta_bim%3D1%22%5Cn%20%20%20%20%20%20%20%20%20%20%20style%3D%22display%3Ainline-block%3Bbackground%3A%23c9a84c%3Bcolor%3A%23000%3Bfont-weight%3A700%3Bpadding%3A10px%2024px%3Bborder-radius%3A8px%3Btext-decoration%3Anone%3Bfont-size%3A14px%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20Ir%20a%20MercadoLibre%20y%20ejecutar%20%E2%86%92%5Cn%20%20%20%20%20%20%20%20%3C%2Fa%3E%5Cn%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22margin-top%3A12px%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20%3Cbutton%20onclick%3D%22this.closest%28%5C%27div%5C%27%29.parentElement.remove%28%29%22%5Cn%20%20%20%20%20%20%20%20%20%20%20%20style%3D%22background%3Anone%3Bborder%3Anone%3Bcolor%3A%23666%3Bcursor%3Apointer%3Bfont-size%3A12px%22%3ECerrar%3C%2Fbutton%3E%5Cn%20%20%20%20%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%3C%2Fdiv%3E%27%2Cvoid%20document.body.appendChild%28e%29%7Dconst%20a%3D%5B%7Bid%3A1%2Cname%3A%22Cemento%20Portland%20Normal%2050kg%22%2Cq%3A%22cemento%20portland%2050kg%22%7D%2C%7Bid%3A2%2Cname%3A%22Hierro%20en%20barra%20%C3%988mm%20%C3%97%2012m%22%2Cq%3A%22hierro%20redondo%208mm%20barra%2012m%22%7D%2C%7Bid%3A3%2Cname%3A%22Hierro%20en%20barra%20%C3%9810mm%20%C3%97%2012m%22%2Cq%3A%22hierro%20redondo%2010mm%20barra%2012m%22%7D%2C%7Bid%3A4%2Cname%3A%22Hierro%20en%20barra%20%C3%9812mm%20%C3%97%2012m%22%2Cq%3A%22hierro%20redondo%2012mm%20barra%2012m%22%7D%2C%7Bid%3A5%2Cname%3A%22Hierro%20en%20barra%20%C3%9816mm%20%C3%97%2012m%22%2Cq%3A%22hierro%20redondo%2016mm%20barra%2012m%22%7D%2C%7Bid%3A6%2Cname%3A%22Malla%20sima%2015%C3%9715%20panel%202.1%C3%974.3m%22%2Cq%3A%22malla%20sima%20electrosoldada%20panel%22%7D%2C%7Bid%3A7%2Cname%3A%22Arena%20gruesa%2030kg%22%2Cq%3A%22arena%20gruesa%20construccion%20bolsa%22%7D%2C%7Bid%3A8%2Cname%3A%22Arena%20fina%2025kg%22%2Cq%3A%22arena%20fina%20revoque%20bolsa%22%7D%2C%7Bid%3A9%2Cname%3A%22Piedra%20partida%206%2F20%2030kg%22%2Cq%3A%22piedra%20partida%20triturada%20bolsa%22%7D%2C%7Bid%3A10%2Cname%3A%22Block%20hormig%C3%B3n%2020%C3%9720%C3%9740cm%22%2Cq%3A%22block%20hormigon%2020x20x40%22%7D%2C%7Bid%3A11%2Cname%3A%22Ladrillo%20cer%C3%A1mico%20macizo%2025%C3%9712%C3%978cm%22%2Cq%3A%22ladrillo%20macizo%20ceramico%22%7D%2C%7Bid%3A12%2Cname%3A%22Ladrillo%20cer%C3%A1mico%20hueco%2018%C3%9718%C3%9733cm%22%2Cq%3A%22ladrillo%20ceramico%20hueco%2018x18x33%22%7D%2C%7Bid%3A13%2Cname%3A%22Cal%20hidratada%2030kg%22%2Cq%3A%22cal%20hidratada%20bolsa%2030kg%22%7D%2C%7Bid%3A14%2Cname%3A%22Yeso%20fino%2040kg%22%2Cq%3A%22yeso%20fino%20bolsa%20construccion%22%7D%2C%7Bid%3A15%2Cname%3A%22Adhesivo%20cer%C3%A1mico%2030kg%22%2Cq%3A%22adhesivo%20ceramico%20flexible%2030kg%22%7D%2C%7Bid%3A16%2Cname%3A%22Membrana%20asf%C3%A1ltica%204mm%22%2Cq%3A%22membrana%20asfaltica%204mm%20aluminio%22%7D%2C%7Bid%3A17%2Cname%3A%22Lana%20de%20vidrio%2050mm%20rollo%22%2Cq%3A%22lana%20de%20vidrio%20aislante%20rollo%22%7D%2C%7Bid%3A18%2Cname%3A%22Chapa%20galvanizada%20acanalada%203m%22%2Cq%3A%22chapa%20galvanizada%20acanalada%203m%22%7D%2C%7Bid%3A19%2Cname%3A%22Teja%20colonial%20cer%C3%A1mica%22%2Cq%3A%22teja%20colonial%20ceramica%22%7D%2C%7Bid%3A20%2Cname%3A%22Perfil%20C%20galvanizado%20100%C3%9750%22%2Cq%3A%22perfil%20c%20galvanizado%20100x50%22%7D%2C%7Bid%3A21%2Cname%3A%22Cable%20unipolar%202.5mm%20100m%22%2Cq%3A%22cable%20unipolar%202.5mm%20rollo%20100m%22%7D%2C%7Bid%3A22%2Cname%3A%22Cable%20unipolar%204mm%20100m%22%2Cq%3A%22cable%20unipolar%204mm%20rollo%20100m%22%7D%2C%7Bid%3A23%2Cname%3A%22Ca%C3%B1er%C3%ADa%20corrugada%2020mm%2025m%22%2Cq%3A%22ca%C3%B1eria%20corrugada%2020mm%20rollo%22%7D%2C%7Bid%3A24%2Cname%3A%22Tablero%20el%C3%A9ctrico%20domiciliario%22%2Cq%3A%22tablero%20electrico%20domiciliario%20llaves%22%7D%2C%7Bid%3A25%2Cname%3A%22Llave%20t%C3%A9rmica%2010A%22%2Cq%3A%22llave%20termica%2010%20amperes%22%7D%2C%7Bid%3A26%2Cname%3A%22Tomacorriente%20IRAM%2010A%22%2Cq%3A%22tomacorriente%20iram%2010%20amperes%22%7D%2C%7Bid%3A27%2Cname%3A%22Ca%C3%B1o%20PVC%20110mm%20desag%C3%BCe%203m%22%2Cq%3A%22ca%C3%B1o%20pvc%20110mm%20desague%203m%22%7D%2C%7Bid%3A28%2Cname%3A%22Ca%C3%B1o%20PVC%20presi%C3%B3n%2032mm%206m%22%2Cq%3A%22ca%C3%B1o%20pvc%20presion%2032mm%206m%22%7D%2C%7Bid%3A29%2Cname%3A%22Inodoro%20cer%C3%A1mica%20mochila%22%2Cq%3A%22inodoro%20ceramica%20mochila%22%7D%2C%7Bid%3A30%2Cname%3A%22Lavatorio%20ba%C3%B1o%20cer%C3%A1mica%22%2Cq%3A%22lavatorio%20ceramica%20ba%C3%B1o%22%7D%2C%7Bid%3A31%2Cname%3A%22Grifer%C3%ADa%20monocomando%22%2Cq%3A%22griferia%20monocomando%20ba%C3%B1o%22%7D%2C%7Bid%3A32%2Cname%3A%22Termotanque%20el%C3%A9ctrico%2080L%22%2Cq%3A%22termotanque%20electrico%2080%20litros%22%7D%2C%7Bid%3A33%2Cname%3A%22Porcelanato%20rectificado%2060%C3%9760%22%2Cq%3A%22porcelanato%20rectificado%2060x60%22%7D%2C%7Bid%3A34%2Cname%3A%22Cer%C3%A1mica%20piso%2035%C3%9735%22%2Cq%3A%22ceramica%20piso%20antideslizante%2035x35%20caja%22%7D%2C%7Bid%3A35%2Cname%3A%22Azulejo%2020%C3%9730%20caja%22%2Cq%3A%22azulejo%2020x30%20caja%20primera%22%7D%2C%7Bid%3A36%2Cname%3A%22Pintura%20l%C3%A1tex%20lavable%2010L%22%2Cq%3A%22pintura%20latex%20lavable%20interior%2010%20litros%22%7D%2C%7Bid%3A37%2Cname%3A%22Pintura%20exterior%20frente%204L%22%2Cq%3A%22pintura%20exterior%20frente%204%20litros%22%7D%2C%7Bid%3A38%2Cname%3A%22Puerta%20placa%20interior%2080%C3%97200%22%2Cq%3A%22puerta%20placa%20interior%2080x200%20marco%22%7D%2C%7Bid%3A39%2Cname%3A%22Ventana%20aluminio%20corrediza%22%2Cq%3A%22ventana%20aluminio%20corrediza%20vidrio%22%7D%2C%7Bid%3A40%2Cname%3A%22Ventana%20aluminio%20DVH%22%2Cq%3A%22ventana%20aluminio%20dvh%20doble%20vidrio%22%7D%2C%7Bid%3A41%2Cname%3A%22Masilla%20pl%C3%A1stica%205kg%22%2Cq%3A%22masilla%20plastica%20interior%20balde%205kg%22%7D%2C%7Bid%3A42%2Cname%3A%22Pastina%20cer%C3%A1mica%202kg%22%2Cq%3A%22pastina%20ceramica%20juntas%202kg%22%7D%2C%7Bid%3A43%2Cname%3A%27Ca%C3%B1o%20de%20cobre%20%C2%BD%22%203m%27%2Cq%3A%22ca%C3%B1o%20cobre%201%2F2%20pulgada%203m%22%7D%2C%7Bid%3A44%2Cname%3A%22Ducha%20el%C3%A9ctrica%22%2Cq%3A%22ducha%20electrica%22%7D%2C%7Bid%3A45%2Cname%3A%22Calefactor%20tiro%20balanceado%22%2Cq%3A%22calefactor%20tiro%20balanceado%20gas%22%7D%2C%7Bid%3A46%2Cname%3A%22Cocina%20gas%204%20hornallas%22%2Cq%3A%22cocina%20gas%204%20hornallas%22%7D%2C%7Bid%3A47%2Cname%3A%22Disyuntor%20termomagnetico%2020A%22%2Cq%3A%22disyuntor%20termomagnetico%2020%20amperes%22%7D%2C%7Bid%3A48%2Cname%3A%22Revestimiento%20cer%C3%A1mico%2030%C3%9760%22%2Cq%3A%22revestimiento%20ceramico%20pared%2030x60%22%7D%2C%7Bid%3A49%2Cname%3A%22Parquet%20flotante%20laminado%22%2Cq%3A%22parquet%20flotante%20laminado%20piso%22%7D%2C%7Bid%3A50%2Cname%3A%22Porcelanato%20s%C3%ADmil%20madera%22%2Cq%3A%22porcelanato%20simil%20madera%20piso%22%7D%5D%3Bfunction%20t%28e%29%7Breturn%60https%3A%2F%2Fwww.mercadolibre.com.ar%2Fjm%2Fsearch%3Fas_word%3D%24%7BencodeURIComponent%28e%29%7D%60%7Dfunction%20n%28e%29%7Bconst%20a%3D%5B...e%5D.sort%28%28e%2Ca%29%3D%3Ee-a%29%2Ct%3Da.length%3Breturn%20t%252%3D%3D0%3F%28a%5Bt%2F2-1%5D%2Ba%5Bt%2F2%5D%29%2F2%3Aa%5BMath.floor%28t%2F2%29%5D%7Dconst%20o%3De%3D%3EMath.round%28e%29.toLocaleString%28%22es-AR%22%29%3Bdocument.getElementById%28%22__ta_bim_overlay%22%29%3F.remove%28%29%3Bconst%20i%3Ddocument.createElement%28%22div%22%29%3Bi.id%3D%22__ta_bim_overlay%22%2Ci.style.cssText%3D%22%5Cn%20%20%20%20position%3Afixed%3Btop%3A0%3Bright%3A0%3Bwidth%3A420px%3Bheight%3A100vh%3Bbackground%3A%231a1a1a%3B%5Cn%20%20%20%20color%3A%23eee%3Bfont-family%3Asystem-ui%2Csans-serif%3Bfont-size%3A12px%3Bz-index%3A999999%3B%5Cn%20%20%20%20box-shadow%3A-4px%200%2024px%20rgba%280%2C0%2C0%2C.7%29%3Bdisplay%3Aflex%3Bflex-direction%3Acolumn%3B%5Cn%20%20%20%20border-left%3A3px%20solid%20%23c9a84c%3B%5Cn%20%20%22%2Ci.innerHTML%3D%60%5Cn%20%20%20%20%3Cdiv%20style%3D%22padding%3A12px%2016px%3Bbackground%3A%23252525%3Bborder-bottom%3A1px%20solid%20%23333%3Bdisplay%3Aflex%3Balign-items%3Acenter%3Bgap%3A8px%3Bflex-shrink%3A0%22%3E%5Cn%20%20%20%20%20%20%3Cspan%20style%3D%22font-size%3A16px%22%3E%F0%9F%8F%97%3C%2Fspan%3E%5Cn%20%20%20%20%20%20%3Cdiv%20style%3D%22flex%3A1%22%3E%5Cn%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22font-weight%3A700%3Bcolor%3A%23c9a84c%3Bfont-size%3A13px%22%3ETasadorIA%20%E2%80%94%20Materiales%20BIM%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22color%3A%23666%3Bfont-size%3A10px%22%3EMercadoLibre%20%C2%B7%20%24%7Ba.length%7D%20materiales%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%3Cbutton%20id%3D%22__ta_close%22%20style%3D%22background%3Anone%3Bborder%3Anone%3Bcolor%3A%23666%3Bfont-size%3A16px%3Bcursor%3Apointer%3Bpadding%3A2px%206px%22%3E%E2%9C%95%3C%2Fbutton%3E%5Cn%20%20%20%20%3C%2Fdiv%3E%5Cn%5Cn%20%20%20%20%5Cx3c%21--%20Tabs%20--%5Cx3e%5Cn%20%20%20%20%3Cdiv%20style%3D%22display%3Aflex%3Bborder-bottom%3A1px%20solid%20%23333%3Bflex-shrink%3A0%22%3E%5Cn%20%20%20%20%20%20%3Cbutton%20id%3D%22__ta_tab_auto%22%20onclick%3D%22__ta_switchTab%28%27auto%27%29%22%5Cn%20%20%20%20%20%20%20%20style%3D%22flex%3A1%3Bpadding%3A8px%3Bbackground%3A%231a1a1a%3Bborder%3Anone%3Bborder-bottom%3A2px%20solid%20%23c9a84c%3Bcolor%3A%23c9a84c%3Bfont-size%3A11px%3Bfont-weight%3A700%3Bcursor%3Apointer%22%3E%5Cn%20%20%20%20%20%20%20%20%E2%96%B6%20Auto%20%28%24%7Ba.length%7D%29%5Cn%20%20%20%20%20%20%3C%2Fbutton%3E%5Cn%20%20%20%20%20%20%3Cbutton%20id%3D%22__ta_tab_links%22%20onclick%3D%22__ta_switchTab%28%27links%27%29%22%5Cn%20%20%20%20%20%20%20%20style%3D%22flex%3A1%3Bpadding%3A8px%3Bbackground%3A%23111%3Bborder%3Anone%3Bborder-bottom%3A2px%20solid%20transparent%3Bcolor%3A%23666%3Bfont-size%3A11px%3Bcursor%3Apointer%22%3E%5Cn%20%20%20%20%20%20%20%20%F0%9F%94%97%20Links%20por%20material%5Cn%20%20%20%20%20%20%3C%2Fbutton%3E%5Cn%20%20%20%20%3C%2Fdiv%3E%5Cn%5Cn%20%20%20%20%5Cx3c%21--%20Panel%20Auto%20--%5Cx3e%5Cn%20%20%20%20%3Cdiv%20id%3D%22__ta_panel_auto%22%20style%3D%22display%3Aflex%3Bflex-direction%3Acolumn%3Bflex%3A1%3Boverflow%3Ahidden%22%3E%5Cn%20%20%20%20%20%20%3Cdiv%20style%3D%22padding%3A10px%2014px%3Bbackground%3A%231e1e1e%3Bborder-bottom%3A1px%20solid%20%23333%3Bflex-shrink%3A0%22%3E%5Cn%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22display%3Aflex%3Bgap%3A8px%3Bmargin-bottom%3A8px%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22flex%3A1%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22font-size%3A9px%3Bcolor%3A%23666%3Bmargin-bottom%3A3px%3Btext-transform%3Auppercase%22%3EUSD%2FARS%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%20%20%20%20%3Cinput%20id%3D%22__ta_usd%22%20type%3D%22number%22%20value%3D%221400%22%5Cn%20%20%20%20%20%20%20%20%20%20%20%20%20%20style%3D%22width%3A100%25%3Bbackground%3A%232a2a2a%3Bborder%3A1px%20solid%20%23444%3Bcolor%3A%23eee%3Bpadding%3A5px%208px%3Bborder-radius%3A5px%3Bfont-size%3A12px%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22flex%3A2%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20%20%20%3Cdiv%20style%3D%22font-size%3A9px%3Bcolor%3A%23666%3Bmargin-bottom%3A3px%3Btext-transform%3Auppercase%22%3EServidor%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%20%20%20%20%3Cinput%20id%3D%22__ta_srv%22%20type%3D%22text%22%20value%3D%22https%3A%2F%2Fanperprimo.com%2Ftasador%22%5Cn%20%20%20%20%20%20%20%20%20%20%20%20%20%20style%3D%22width%3A100%25%3Bbackground%3A%232a2a2a%3Bborder%3A1px%20solid%20%23444%3Bcolor%3A%23eee%3Bpadding%3A5px%208px%3Bborder-radius%3A5px%3Bfont-size%3A11px%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%3Cbutton%20id%3D%22__ta_run%22%5Cn%20%20%20%20%20%20%20%20%20%20style%3D%22width%3A100%25%3Bpadding%3A8px%3Bbackground%3A%23c9a84c%3Bcolor%3A%23000%3Bfont-weight%3A700%3Bborder%3Anone%3Bborder-radius%3A7px%3Bcursor%3Apointer%3Bfont-size%3A12px%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20%E2%96%B6%20Buscar%20todos%20los%20materiales%20%28%24%7Ba.length%7D%29%5Cn%20%20%20%20%20%20%20%20%3C%2Fbutton%3E%5Cn%20%20%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%3Cdiv%20id%3D%22__ta_progress%22%20style%3D%22padding%3A6px%2014px%3Bfont-size%3A10px%3Bcolor%3A%23c9a84c%3Bdisplay%3Anone%3Bflex-shrink%3A0%22%3E%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%3Cdiv%20id%3D%22__ta_log%22%20style%3D%22flex%3A1%3Boverflow-y%3Aauto%3Bpadding%3A6px%2014px%22%3E%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%3Cdiv%20id%3D%22__ta_footer%22%20style%3D%22display%3Anone%3Bpadding%3A10px%2014px%3Bbackground%3A%231e1e1e%3Bborder-top%3A1px%20solid%20%23333%3Bflex-shrink%3A0%22%3E%5Cn%20%20%20%20%20%20%20%20%3Cdiv%20id%3D%22__ta_summary%22%20style%3D%22font-size%3A11px%3Bcolor%3A%23888%3Bmargin-bottom%3A8px%22%3E%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%20%20%3Cbutton%20id%3D%22__ta_save%22%5Cn%20%20%20%20%20%20%20%20%20%20style%3D%22width%3A100%25%3Bpadding%3A8px%3Bbackground%3A%234caf50%3Bcolor%3A%23fff%3Bfont-weight%3A700%3Bborder%3Anone%3Bborder-radius%3A7px%3Bcursor%3Apointer%3Bfont-size%3A12px%22%3E%5Cn%20%20%20%20%20%20%20%20%20%20%F0%9F%92%BE%20Guardar%20en%20BIM%5Cn%20%20%20%20%20%20%20%20%3C%2Fbutton%3E%5Cn%20%20%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%20%20%3C%2Fdiv%3E%5Cn%5Cn%20%20%20%20%5Cx3c%21--%20Panel%20Links%20--%5Cx3e%5Cn%20%20%20%20%3Cdiv%20id%3D%22__ta_panel_links%22%20style%3D%22display%3Anone%3Bflex%3A1%3Boverflow%3Ahidden%3Bflex-direction%3Acolumn%22%3E%5Cn%20%20%20%20%20%20%3Cdiv%20style%3D%22padding%3A8px%2014px%3Bbackground%3A%231e1e1e%3Bborder-bottom%3A1px%20solid%20%23333%3Bflex-shrink%3A0%3Bfont-size%3A10px%3Bcolor%3A%23888%22%3E%5Cn%20%20%20%20%20%20%20%20Hac%C3%A9%20clic%20en%20%F0%9F%94%8D%20para%20ver%20los%20resultados%20en%20ML%20%C2%B7%20Los%20precios%20se%20actualizan%20al%20buscar%20en%20modo%20Auto%5Cn%20%20%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%20%20%20%20%3Cdiv%20id%3D%22__ta_links_list%22%20style%3D%22flex%3A1%3Boverflow-y%3Aauto%3Bpadding%3A6px%2014px%22%3E%3C%2Fdiv%3E%5Cn%20%20%20%20%3C%2Fdiv%3E%5Cn%20%20%60%2Cdocument.body.appendChild%28i%29%2Cwindow.__ta_switchTab%3Dfunction%28e%29%7Bconst%20a%3D%22auto%22%3D%3D%3De%3Bdocument.getElementById%28%22__ta_panel_auto%22%29.style.display%3Da%3F%22flex%22%3A%22none%22%2Cdocument.getElementById%28%22__ta_panel_auto%22%29.style.flexDirection%3D%22column%22%2Cdocument.getElementById%28%22__ta_panel_links%22%29.style.display%3Da%3F%22none%22%3A%22flex%22%2Cdocument.getElementById%28%22__ta_panel_links%22%29.style.flexDirection%3D%22column%22%2Cdocument.getElementById%28%22__ta_tab_auto%22%29.style.cssText%3D%60flex%3A1%3Bpadding%3A8px%3Bbackground%3A%231a1a1a%3Bborder%3Anone%3Bborder-bottom%3A2px%20solid%20%24%7Ba%3F%22%23c9a84c%22%3A%22transparent%22%7D%3Bcolor%3A%24%7Ba%3F%22%23c9a84c%22%3A%22%23666%22%7D%3Bfont-size%3A11px%3Bfont-weight%3A%24%7Ba%3F%22700%22%3A%22400%22%7D%3Bcursor%3Apointer%60%2Cdocument.getElementById%28%22__ta_tab_links%22%29.style.cssText%3D%60flex%3A1%3Bpadding%3A8px%3Bbackground%3A%23111%3Bborder%3Anone%3Bborder-bottom%3A2px%20solid%20%24%7Ba%3F%22transparent%22%3A%22%23c9a84c%22%7D%3Bcolor%3A%24%7Ba%3F%22%23666%22%3A%22%23c9a84c%22%7D%3Bfont-size%3A11px%3Bfont-weight%3A%24%7Ba%3F%22400%22%3A%22700%22%7D%3Bcursor%3Apointer%60%7D%3Bconst%20r%3Ddocument.getElementById%28%22__ta_links_list%22%29%3Basync%20function%20s%28e%29%7Bconst%20a%3Dfunction%28%29%7Btry%7Bconst%20e%3Dwindow.__INITIAL_STATE__%7C%7Cwindow.__PRELOADED_STATE__%7C%7Cwindow.__STORE__%3Bif%28e%3F.auth%3F.accessToken%29return%20e.auth.accessToken%3Bif%28e%3F.components%3F.header%3F.token%29return%20e.components.header.token%3Bconst%20a%3Ddocument.querySelectorAll%28%22script%3Anot%28%5Bsrc%5D%29%22%29%3Bfor%28const%20e%20of%20a%29%7Bconst%20a%3De.textContent.match%28%2F%22access_token%22%5Cs%2A%3A%5Cs%2A%22%28APP_USR-%5B%5E%22%5D%2B%29%22%2F%29%3Bif%28a%29return%20a%5B1%5D%7D%7Dcatch%28e%29%7B%7Dreturn%20null%7D%28%29%2Ct%3D%7BAccept%3A%22application%2Fjson%22%7D%3Ba%26%26%28t.Authorization%3D%60Bearer%20%24%7Ba%7D%60%29%3Bconst%20n%3D%60https%3A%2F%2Fapi.mercadolibre.com%2Fsites%2FMLA%2Fsearch%3Fq%3D%24%7BencodeURIComponent%28e%29%7D%26limit%3D12%26sort%3Drelevance%60%2Co%3Dawait%20fetch%28n%2C%7Bcredentials%3A%22include%22%2Cheaders%3At%7D%29%3Bif%28%21o.ok%29throw%20new%20Error%28%60API%20HTTP%20%24%7Bo.status%7D%60%29%3Breturn%28await%20o.json%28%29%29.results%7C%7C%5B%5D%7Dasync%20function%20l%28a%29%7Bconst%20t%3D%60%24%7Be%7D%2F%24%7BencodeURIComponent%28a%29.replace%28%2F%2520%2Fg%2C%22-%22%29%7D_NoIndex_True%60%2Cn%3Dawait%20fetch%28t%2C%7Bcredentials%3A%22include%22%7D%29%3Bif%28%21n.ok%29throw%20new%20Error%28%60Page%20HTTP%20%24%7Bn.status%7D%60%29%3Bconst%20o%3Dawait%20n.text%28%29%2Ci%3Do.match%28%2F%3Cscript%5B%5E%3E%5D%2Aid%3D%22__NEXT_DATA__%22%5B%5E%3E%5D%2A%3E%28%5B%5Cs%5CS%5D%2B%3F%29%3C%5C%2Fscript%3E%2F%29%3Bif%28i%29try%7Bconst%20e%3DJSON.parse%28i%5B1%5D%29%2Ca%3De%3F.props%3F.pageProps%3F.initialSearchData%3F.results%7C%7Ce%3F.props%3F.pageProps%3F.searchResult%3F.results%7C%7Ce%3F.props%3F.pageProps%3F.results%7C%7Ce%3F.props%3F.pageProps%3F.dehydratedState%3F.queries%3F.%5B0%5D%3F.state%3F.data%3F.results%7C%7C%5B%5D%3Bif%28a.length%29return%20a.map%28e%3D%3E%28%7Bprice%3Ae.price%7C%7C0%2Cseller_address%3Ae.seller_address%7C%7C%7Bstate%3A%7Bid%3A%22%22%7D%7D%7D%29%29%7Dcatch%28e%29%7B%7Dconst%20r%3D%5B...o.matchAll%28%2F%22price%22%5Cs%2A%3A%5Cs%2A%28%5Cd%7B3%2C8%7D%29%28%3F%3A%5C.%5Cd%2B%29%3F%2Fg%29%5D.map%28e%3D%3EparseInt%28e%5B1%5D%29%29.filter%28e%3D%3Ee%3E500%26%26e%3C5e7%29%3Bif%28r.length%3E%3D3%29return%20r.slice%280%2C20%29.map%28e%3D%3E%28%7Bprice%3Ae%2Cseller_address%3A%7Bstate%3A%7Bid%3A%22%22%7D%7D%7D%29%29%3Bthrow%20new%20Error%28%22Sin%20datos%22%29%7Da.forEach%28e%3D%3E%7Bconst%20a%3Ddocument.createElement%28%22div%22%29%3Ba.id%3D%60__ta_link_%24%7Be.id%7D%60%2Ca.style.cssText%3D%22padding%3A5px%200%3Bborder-bottom%3A1px%20solid%20%232a2a2a%3Bdisplay%3Aflex%3Balign-items%3Acenter%3Bgap%3A6px%22%2Ca.innerHTML%3D%60%5Cn%20%20%20%20%20%20%3Cspan%20style%3D%22font-size%3A10px%3Bcolor%3A%23555%3Bwidth%3A18px%3Btext-align%3Aright%3Bflex-shrink%3A0%22%3E%24%7Be.id%7D%3C%2Fspan%3E%5Cn%20%20%20%20%20%20%3Cspan%20style%3D%22flex%3A1%3Bfont-size%3A11px%3Boverflow%3Ahidden%3Btext-overflow%3Aellipsis%3Bwhite-space%3Anowrap%22%20title%3D%22%24%7Be.name%7D%22%3E%24%7Be.name%7D%3C%2Fspan%3E%5Cn%20%20%20%20%20%20%3Cspan%20id%3D%22__ta_lp_%24%7Be.id%7D%22%20style%3D%22font-size%3A10px%3Bcolor%3A%23555%3Bwhite-space%3Anowrap%22%3E%E2%80%94%3C%2Fspan%3E%5Cn%20%20%20%20%20%20%3Ca%20href%3D%22%24%7Bt%28e.q%29%7D%22%20target%3D%22_blank%22%20rel%3D%22noopener%22%5Cn%20%20%20%20%20%20%20%20style%3D%22background%3A%23fff1%3Bborder%3A1px%20solid%20%23444%3Bcolor%3A%23c9a84c%3Bpadding%3A2px%207px%3Bborder-radius%3A4px%3Bfont-size%3A10px%3Btext-decoration%3Anone%3Bflex-shrink%3A0%3Bwhite-space%3Anowrap%22%3E%5Cn%20%20%20%20%20%20%20%20%F0%9F%94%8D%20ML%5Cn%20%20%20%20%20%20%3C%2Fa%3E%5Cn%20%20%20%20%60%2Cr.appendChild%28a%29%7D%29%2Cdocument.getElementById%28%22__ta_close%22%29.onclick%3D%28%29%3D%3Ei.remove%28%29%3Bconst%20d%3D%5B%5D%2Cc%3Ddocument.getElementById%28%22__ta_log%22%29%2Cp%3Ddocument.getElementById%28%22__ta_progress%22%29%2Cm%3Ddocument.getElementById%28%22__ta_footer%22%29%2Cu%3Ddocument.getElementById%28%22__ta_summary%22%29%3Bfunction%20x%28e%2Ca%2Cn%2Ci%2Cr%29%7Bconst%20s%3Ddocument.createElement%28%22div%22%29%3Bs.style.cssText%3D%22display%3Agrid%3Bgrid-template-columns%3A14px%201fr%2070px%2070px%2040px%3Bgap%3A4px%3Bpadding%3A3px%200%3Bborder-bottom%3A1px%20solid%20%232a2a2a%3Balign-items%3Acenter%22%2Cs.innerHTML%3D%60%5Cn%20%20%20%20%20%20%3Ca%20href%3D%22%24%7Bt%28a.q%29%7D%22%20target%3D%22_blank%22%20title%3D%22Ver%20en%20ML%22%20style%3D%22color%3A%23c9a84c%3Btext-decoration%3Anone%3Bfont-size%3A11px%22%3E%24%7Be%7D%3C%2Fa%3E%5Cn%20%20%20%20%20%20%3Cspan%20style%3D%22overflow%3Ahidden%3Btext-overflow%3Aellipsis%3Bwhite-space%3Anowrap%3Bfont-size%3A10px%22%20title%3D%22%24%7Ba.name%7D%22%3E%24%7Ba.name%7D%3C%2Fspan%3E%5Cn%20%20%20%20%20%20%3Cspan%20style%3D%22text-align%3Aright%3Bcolor%3A%23555%3Bfont-size%3A10px%22%3E%E2%80%94%3C%2Fspan%3E%5Cn%20%20%20%20%20%20%3Cspan%20style%3D%22text-align%3Aright%3Bfont-weight%3A600%3Bfont-size%3A10px%3Bcolor%3A%24%7Br%7D%22%3E%24%7Bn%3F%22%24%22%2Bo%28n%29%3A%27%3Cspan%20style%3D%22color%3A%23f44336%22%3Eerror%3C%2Fspan%3E%27%7D%3C%2Fspan%3E%5Cn%20%20%20%20%20%20%3Cspan%20style%3D%22text-align%3Aright%3Bfont-size%3A10px%3Bcolor%3A%24%7Br%7D%22%3E%24%7Bi%7D%3C%2Fspan%3E%5Cn%20%20%20%20%60%3Bconst%20l%3Ddocument.getElementById%28%60__ta_lp_%24%7Ba.id%7D%60%29%3Bl%26%26n%26%26%28l.textContent%3D%22%24%22%2Bo%28n%29%29%2Cc.appendChild%28s%29%2Cc.scrollTop%3Dc.scrollHeight%7Dfunction%20g%28e%2Ca%29%7Bconst%20n%3Ddocument.createElement%28%22div%22%29%3Bn.style.cssText%3D%22display%3Agrid%3Bgrid-template-columns%3A14px%201fr%20auto%3Bgap%3A4px%3Bpadding%3A3px%200%3Bborder-bottom%3A1px%20solid%20%232a2a2a%3Balign-items%3Acenter%22%2Cn.innerHTML%3D%60%5Cn%20%20%20%20%20%20%3Ca%20href%3D%22%24%7Bt%28e.q%29%7D%22%20target%3D%22_blank%22%20title%3D%22Buscar%20manualmente%20en%20ML%22%20style%3D%22color%3A%23f44336%3Btext-decoration%3Anone%3Bfont-size%3A11px%22%3E%E2%9C%97%3C%2Fa%3E%5Cn%20%20%20%20%20%20%3Cspan%20style%3D%22overflow%3Ahidden%3Btext-overflow%3Aellipsis%3Bwhite-space%3Anowrap%3Bfont-size%3A10px%3Bcolor%3A%23f44336%22%20title%3D%22%24%7Be.name%7D%22%3E%24%7Be.name%7D%3C%2Fspan%3E%5Cn%20%20%20%20%20%20%3Cspan%20style%3D%22font-size%3A9px%3Bcolor%3A%23f44336%22%3E%24%7Ba%7D%3C%2Fspan%3E%5Cn%20%20%20%20%60%2Cc.appendChild%28n%29%2Cc.scrollTop%3Dc.scrollHeight%7Ddocument.getElementById%28%22__ta_run%22%29.onclick%3Dasync%20function%28%29%7Bthis.disabled%3D%210%2Cthis.textContent%3D%22%E2%8F%B3%20Buscando%E2%80%A6%22%2Cp.style.display%3D%22block%22%2Cc.innerHTML%3D%27%5Cn%20%20%20%20%20%20%3Cdiv%20style%3D%22display%3Agrid%3Bgrid-template-columns%3A14px%201fr%2070px%2070px%2040px%3Bgap%3A4px%3Bcolor%3A%23555%3Bpadding%3A3px%200%3Bborder-bottom%3A1px%20solid%20%23333%3Bfont-size%3A9px%3Btext-transform%3Auppercase%3Bmargin-bottom%3A2px%22%3E%5Cn%20%20%20%20%20%20%20%20%3Cspan%3E%3C%2Fspan%3E%3Cspan%3EMaterial%3C%2Fspan%3E%3Cspan%20style%3D%22text-align%3Aright%22%3EAnt.%3C%2Fspan%3E%3Cspan%20style%3D%22text-align%3Aright%22%3ENuevo%3C%2Fspan%3E%3Cspan%20style%3D%22text-align%3Aright%22%3E%CE%94%3C%2Fspan%3E%5Cn%20%20%20%20%20%20%3C%2Fdiv%3E%27%2Cd.length%3D0%3Blet%20e%3D0%2Ct%3D0%2Co%3D0%3Bfor%28let%20i%3D0%3Bi%3Ca.length%3Bi%2B%2B%29%7Bconst%20r%3Da%5Bi%5D%3Bp.innerHTML%3D%60%5B%24%7Bi%2B1%7D%2F%24%7Ba.length%7D%5D%20%3Cstrong%3E%24%7Br.name%7D%3C%2Fstrong%3E%60%3Blet%20c%3Dnull%3Btry%7Bc%3Dawait%20s%28r.q%29%7Dcatch%28e%29%7Btry%7Bc%3Dawait%20l%28r.q%29%7Dcatch%28e%29%7Bt%2B%2B%2Cg%28r%2Ce.message%29%2Cawait%20new%20Promise%28e%3D%3EsetTimeout%28e%2C200%29%29%3Bcontinue%7D%7Dconst%20m%3Dc.map%28e%3D%3Ee.price%7C%7C0%29.filter%28e%3D%3Ee%3E100%29%3Bif%28%21m.length%29%7Bo%2B%2B%2Cg%28r%2C%22sin%20resultados%22%29%2Cawait%20new%20Promise%28e%3D%3EsetTimeout%28e%2C200%29%29%3Bcontinue%7Dconst%20u%3DMath.round%28n%28m%29%29%2Cf%3Dc.map%28e%3D%3E%5B%22AR-C%22%2C%22AR-B%22%5D.includes%28e.seller_address%3F.state%3F.id%7C%7C%22%22%29%3F9%3A0%29%2C_%3DMath.round%28f.reduce%28%28e%2Ca%29%3D%3Ee%2Ba%2C0%29%2Ff.length%2A10%29%2F10%3Be%2B%2B%2Cd.push%28%7Bid%3Ar.id%2Cmaterial%3Ar.name%2Cprice_ars%3Au%2Cflete_pct%3A_%2Ccount%3Am.length%2Cquery%3Ar.q%7D%29%2Cx%28%22%E2%9C%93%22%2Cr%2Cu%2C_%3E0%3F%60%2B%24%7B_%7D%25%60%3A%22%E2%80%94%22%2C%22%234caf50%22%29%2Cawait%20new%20Promise%28e%3D%3EsetTimeout%28e%2C350%29%29%7Dp.innerHTML%3D%60%3Cstrong%20style%3D%22color%3A%234caf50%22%3E%E2%9C%93%20Completado%3C%2Fstrong%3E%20%E2%80%94%20%24%7Be%7D%20encontrados%20%C2%B7%20%24%7Bt%7D%20errores%20%C2%B7%20%24%7Bo%7D%20sin%20datos%60%2Cd.length%3E0%26%26%28u.textContent%3D%60%24%7Bd.length%7D%20materiales%20listos%20para%20guardar%60%2Cm.style.display%3D%22block%22%29%2Cthis.disabled%3D%211%2Cthis.textContent%3D%22%F0%9F%94%84%20Buscar%20de%20nuevo%22%7D%2Cdocument.getElementById%28%22__ta_save%22%29.onclick%3Dasync%20function%28%29%7Bthis.disabled%3D%210%2Cthis.textContent%3D%22%E2%8F%B3%20Guardando%E2%80%A6%22%3Bconst%20e%3Ddocument.getElementById%28%22__ta_srv%22%29.value.replace%28%2F%5C%2F%24%2F%2C%22%22%29%2Ca%3DparseFloat%28document.getElementById%28%22__ta_usd%22%29.value%29%7C%7C1400%3Btry%7Bconst%20t%3Dawait%20fetch%28%60%24%7Be%7D%2Fapi%2Fsave_material_prices.php%60%2C%7Bmethod%3A%22POST%22%2Cheaders%3A%7B%22Content-Type%22%3A%22application%2Fjson%22%7D%2Cbody%3AJSON.stringify%28%7Bprices%3Ad%2Cusd_rate%3Aa%7D%29%2Ccredentials%3A%22omit%22%7D%29%2Cn%3Dawait%20t.json%28%29%3Bif%28%21n.success%29throw%20new%20Error%28n.error%7C%7C%22Error%22%29%3Bu.innerHTML%3D%60%3Cspan%20style%3D%22color%3A%234caf50%22%3E%E2%9C%93%20%24%7Bn.saved%7D%20materiales%20%C2%B7%20%24%7Bn.zones_updated%7D%20zonas%20actualizadas%3C%2Fspan%3E%60%2Cthis.textContent%3D%22%E2%9C%93%20Guardado%22%2Cthis.style.background%3D%22%23388e3c%22%7Dcatch%28e%29%7Bu.innerHTML%3D%60%3Cspan%20style%3D%22color%3A%23f44336%22%3E%E2%9A%A0%20%24%7Be.message%7D%3C%2Fspan%3E%60%2Cthis.disabled%3D%211%2Cthis.textContent%3D%22%F0%9F%92%BE%20Reintentar%22%7D%7D%2Clocation.search.includes%28%22ta_bim%3D1%22%29%26%26setTimeout%28%28%29%3D%3Edocument.getElementById%28%22__ta_run%22%29%3F.click%28%29%2C800%29%7D%28%29%3B"
       style="text-decoration:none"
       title="⭐ BOOKMARKLET — Arrastrá a favoritos → andá a mercadolibre.com.ar → clic → extrae precios automáticamente">
      <button class="btn" style="cursor:grab;border-color:#c9a84c;color:#c9a84c">
        🏗 Materiales ML ⭐
      </button>
    </a>
    <button class="btn" onclick="openMlModal()" title="Actualizar precios por búsqueda de productos (servidor — experimental)">🔍 Precios srv</button>
    <button class="btn" onclick="openPriceListModal()" title="Ver lista completa de precios de materiales con comparación y links a ML">📋 Lista de precios</button>
    <button class="btn btn-primary" onclick="exportCSV()">⬇ CSV</button>
  </div>
</div>

<!-- PANEL: Instrucciones de actualización de precios -->
<div id="help-panel" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9000;align-items:flex-start;justify-content:center;padding-top:40px;overflow-y:auto">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:660px;max-width:95vw;margin-bottom:40px">

    <div style="padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:15px;font-weight:700;color:var(--gold)">📖 Guía — Actualización de precios BIM</div>
      <button onclick="document.getElementById('help-panel').style.display='none'" style="background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer">✕</button>
    </div>

    <!-- MÉTODO 1: Bookmarklet ML -->
    <div style="padding:18px 22px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <span style="background:#c9a84c22;border:1px solid var(--gold);color:var(--gold);font-weight:700;font-size:11px;padding:3px 8px;border-radius:20px">RECOMENDADO</span>
        <div style="font-size:14px;font-weight:700">🏗 Bookmarklet — Precios desde MercadoLibre</div>
      </div>
      <p style="color:var(--muted);font-size:12px;line-height:1.7;margin:0 0 10px">
        El bookmarklet corre <strong style="color:var(--text)">dentro del dominio de mercadolibre.com.ar</strong> desde tu browser,
        por eso no tiene bloqueos — el browser envía tus cookies de ML automáticamente.
        Busca los 50 materiales, calcula la mediana de precios, aplica el flete según provincia de origen del vendedor
        y guarda todo en la base de datos.
      </p>

      <div style="background:var(--bg);border-radius:8px;padding:14px 16px;margin-bottom:12px">
        <div style="font-size:11px;font-weight:700;color:var(--gold);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">⭐ Setup — una sola vez</div>
        <div style="display:flex;flex-direction:column;gap:7px">
          <div style="display:flex;gap:10px;align-items:flex-start">
            <span style="background:#c9a84c;color:#000;font-weight:700;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0">1</span>
            <span style="color:var(--muted);font-size:12px">Asegurate de tener visible la <strong style="color:var(--text)">barra de favoritos</strong> del browser (Ctrl+Shift+B en Chrome/Firefox)</span>
          </div>
          <div style="display:flex;gap:10px;align-items:flex-start">
            <span style="background:#c9a84c;color:#000;font-weight:700;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0">2</span>
            <span style="color:var(--muted);font-size:12px"><strong style="color:var(--text)">Arrastrá</strong> el botón
              <span style="background:#c9a84c22;border:1px solid #c9a84c55;color:#c9a84c;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:600">🏗 Materiales ML ⭐</span>
              desde la barra superior hacia tu barra de favoritos
            </span>
          </div>
          <div style="display:flex;gap:10px;align-items:flex-start">
            <span style="background:#c9a84c;color:#000;font-weight:700;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0">3</span>
            <span style="color:var(--muted);font-size:12px">Listo — el favorito queda guardado para siempre</span>
          </div>
        </div>
      </div>

      <div style="background:var(--bg);border-radius:8px;padding:14px 16px">
        <div style="font-size:11px;font-weight:700;color:#4caf50;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px">🔄 Uso mensual</div>
        <div style="display:flex;flex-direction:column;gap:7px">
          <?php foreach([
            ['Ir a <strong style="color:var(--text)">mercadolibre.com.ar</strong> (cualquier página)',''],
            ['Hacer clic en el favorito <strong style="color:var(--text)">"🏗 Materiales ML ⭐"</strong>','→ aparece el panel flotante en la esquina derecha'],
            ['Verificar el tipo de cambio USD/ARS y la URL del servidor','→ ajustar si cambió'],
            ['Clic en <strong style="color:var(--text)">▶ Buscar todos los materiales</strong>','→ ~3 min · busca 50 materiales'],
            ['Clic en <strong style="color:var(--text)">💾 Guardar en BIM</strong>','→ actualiza precios + recalcula todas las zonas'],
          ] as $i => [$step, $note]): ?>
          <div style="display:flex;gap:10px;align-items:flex-start">
            <span style="background:#4caf5033;color:#4caf50;font-weight:700;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0"><?= $i+1 ?></span>
            <span style="color:var(--muted);font-size:12px;line-height:1.5"><?= $step ?>
              <?php if($note): ?><br><span style="color:#666;font-size:11px"><?= $note ?></span><?php endif ?>
            </span>
          </div>
          <?php endforeach ?>
        </div>
      </div>
    </div>

    <!-- MÉTODO 2: ICC INDEC -->
    <div style="padding:18px 22px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
        <span style="background:#2196f322;border:1px solid #2196f3;color:#2196f3;font-weight:700;font-size:11px;padding:3px 8px;border-radius:20px">AUTOMÁTICO</span>
        <div style="font-size:14px;font-weight:700">📈 ICC INDEC — Ajuste por inflación oficial</div>
      </div>
      <p style="color:var(--muted);font-size:12px;line-height:1.7;margin:0 0 10px">
        Consulta el <strong style="color:var(--text)">Índice del Costo de la Construcción (ICC)</strong> publicado mensualmente por INDEC
        vía la API oficial de <code style="background:var(--bg);padding:1px 5px;border-radius:3px">datos.gob.ar</code>.
        Calcula el ratio de inflación acumulado desde la última actualización y aplica ese porcentaje a todos los precios.
        No requiere el browser — funciona directo desde el servidor.
      </p>
      <div style="background:var(--bg);border-radius:8px;padding:12px 16px">
        <div style="display:flex;flex-direction:column;gap:6px">
          <?php foreach([
            ['Clic en <strong style="color:var(--text)">📈 ICC INDEC</strong> en la barra superior',''],
            ['El sistema consulta INDEC y muestra el % de variación acumulado','ej: +18.3% desde la última actualización'],
            ['Revisá la vista previa de precios ajustados','los primeros 10 materiales'],
            ['Clic en <strong style="color:var(--text)">✓ Aplicar ajuste</strong>','recalcula automáticamente todas las zonas'],
          ] as $i => [$step, $note]): ?>
          <div style="display:flex;gap:10px;align-items:flex-start">
            <span style="background:#2196f333;color:#2196f3;font-weight:700;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:10px;flex-shrink:0"><?= $i+1 ?></span>
            <span style="color:var(--muted);font-size:12px;line-height:1.5"><?= $step ?>
              <?php if($note): ?><br><span style="color:#666;font-size:11px"><?= $note ?></span><?php endif ?>
            </span>
          </div>
          <?php endforeach ?>
        </div>
      </div>
    </div>

    <!-- MÉTODO 3: Editor inline -->
    <div style="padding:18px 22px;border-bottom:1px solid var(--border)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <span style="background:#9c27b022;border:1px solid #9c27b0;color:#9c27b0;font-weight:700;font-size:11px;padding:3px 8px;border-radius:20px">MANUAL</span>
        <div style="font-size:14px;font-weight:700">✏️ Editor inline — Por zona</div>
      </div>
      <p style="color:var(--muted);font-size:12px;line-height:1.7;margin:0 0 8px">
        Hacer clic en cualquier círculo del mapa → botón <strong style="color:var(--text)">Editar</strong> → modificar los costos en USD/m² directamente para esa zona.
        Útil para ajustar zonas específicas o corregir valores que difieren del promedio nacional.
      </p>
    </div>

    <!-- Tabla de factores -->
    <div style="padding:18px 22px">
      <div style="font-size:13px;font-weight:700;margin-bottom:12px">⚙️ Factores de calidad aplicados</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:8px">
        <?php foreach(['Económica'=>['×0.72','−28%','#888'],'Estándar'=>['×1.00','base','#c9a84c'],'Calidad'=>['×1.40','+40%','#4caf50'],'Premium'=>['×2.05','+105%','#2196f3']] as $q=>[$m,$p,$c]): ?>
        <div style="background:var(--bg);border-radius:8px;padding:10px 12px;text-align:center">
          <div style="font-size:11px;color:var(--muted)"><?= $q ?></div>
          <div style="font-size:18px;font-weight:800;color:<?= $c ?>;margin:4px 0"><?= $m ?></div>
          <div style="font-size:10px;color:var(--muted)"><?= $p ?> del base</div>
        </div>
        <?php endforeach ?>
      </div>
      <p style="color:#666;font-size:11px;margin-top:12px;line-height:1.6">
        El costo base (Estándar) se calcula como promedio ponderado de materiales nacionales.
        Las zonas con materiales regionales cargados usan un blend 70% nacional + 30% regional.
        Los valores son en USD/m² de construcción y <strong>no incluyen mano de obra ni gastos generales</strong> — solo materiales.
      </p>
    </div>

  </div>
</div>

<!-- MODAL: Actualizar precios ML -->
<div id="ml-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9000;align-items:center;justify-content:center">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:580px;max-width:95vw;max-height:85vh;display:flex;flex-direction:column">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-size:15px;font-weight:700;color:var(--gold)">🔍 Actualizar precios por búsqueda de productos</div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px">Busca precios en Easy / Blaisten / Sodimac / ML · Aplica flete por provincia · Recalcula costos/m²</div>
      </div>
      <button onclick="closeMlModal()" style="background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer">✕</button>
    </div>
    <div id="ml-token-status" style="padding:12px 20px;background:rgba(201,168,76,.06);border-bottom:1px solid var(--border);font-size:11px;color:var(--muted);line-height:1.6">
      <span id="ml-token-label">🔑 Verificando credenciales ML…</span>
    </div>
    <div style="padding:14px 20px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:4px">Ciudad destino (flete)</label>
        <select id="ml-city" style="background:var(--bg);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:7px;font-size:12px">
          <?php foreach($zones_all as $ck=>$cd): ?>
          <option value="<?=htmlspecialchars($ck)?>" <?=$ck==='santa_fe_capital'?'selected':''?>><?=htmlspecialchars($cd['label']??$ck)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button id="ml-run-btn" onclick="runMlUpdate()" style="padding:8px 20px;background:var(--gold);color:#000;font-weight:700;border:none;border-radius:7px;cursor:pointer;font-size:13px">
        ▶ Ejecutar
      </button>
    </div>
    <div id="ml-spinner" style="display:none;padding:0 20px 8px;color:var(--muted);font-size:11px">⏳ Consultando ML…</div>
    <div id="ml-log" style="flex:1;overflow-y:auto;padding:0 20px 16px;font-size:11px;font-family:monospace"></div>
  </div>
</div>

<!-- MODAL: ICC INDEC -->
<div id="icc-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9000;align-items:center;justify-content:center">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:520px;max-width:95vw">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-size:15px;font-weight:700;color:var(--gold)">📈 Actualizar por ICC INDEC</div>
        <div style="font-size:11px;color:var(--muted);margin-top:3px">Ajuste oficial de inflación de materiales de construcción · Fuente: datos.gob.ar</div>
      </div>
      <button onclick="closeIccModal()" style="background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer">✕</button>
    </div>
    <div id="icc-body" style="padding:20px">
      <div id="icc-status" style="color:var(--muted);font-size:13px">⏳ Consultando INDEC…</div>
    </div>
    <div id="icc-footer" style="display:none;padding:12px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end">
      <button onclick="closeIccModal()" style="padding:7px 18px;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:7px;cursor:pointer;font-size:12px">Cancelar</button>
      <button id="icc-apply-btn" onclick="applyIcc()" style="padding:7px 22px;background:var(--gold);border:none;color:#000;font-weight:700;border-radius:7px;cursor:pointer;font-size:13px">✓ Aplicar ajuste</button>
    </div>
  </div>
</div>

<!-- MODAL: Lista de precios de materiales -->
<div id="price-list-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:9000;align-items:flex-start;justify-content:center;padding:24px 16px;overflow-y:auto">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:900px;max-width:100%;margin:auto">

    <!-- Header -->
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <div style="flex:1">
        <div style="font-size:15px;font-weight:700;color:var(--gold)">📋 Lista de precios de materiales</div>
        <div id="pl-meta" style="font-size:11px;color:var(--muted);margin-top:2px">Cargando…</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input id="pl-search" type="search" placeholder="Buscar material…"
          oninput="plFilter()"
          style="padding:6px 10px;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:6px;font-size:12px;width:180px">
        <select id="pl-cat" onchange="plFilter()"
          style="padding:6px 10px;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:6px;font-size:12px">
          <option value="">Todas las categorías</option>
        </select>
        <select id="pl-sort" onchange="plFilter()"
          style="padding:6px 10px;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:6px;font-size:12px">
          <option value="category">Ordenar: Categoría</option>
          <option value="name">Ordenar: Nombre</option>
          <option value="ars_desc">Ordenar: Mayor precio</option>
          <option value="ars_asc">Ordenar: Menor precio</option>
          <option value="delta_desc">Ordenar: Mayor variación</option>
          <option value="updated">Ordenar: Más reciente</option>
        </select>
        <button onclick="closePriceListModal()" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1">✕</button>
      </div>
    </div>

    <!-- Stats bar -->
    <div id="pl-stats" style="display:flex;gap:0;border-bottom:1px solid var(--border);font-size:11px"></div>

    <!-- Table -->
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead>
          <tr style="background:var(--bg);border-bottom:1px solid var(--border)">
            <th style="padding:8px 12px;text-align:left;color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;white-space:nowrap">Categoría / Material</th>
            <th style="padding:8px 10px;text-align:right;color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;white-space:nowrap">Unidad</th>
            <th style="padding:8px 10px;text-align:right;color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;white-space:nowrap">Precio ARS</th>
            <th style="padding:8px 10px;text-align:right;color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;white-space:nowrap">Precio USD</th>
            <th style="padding:8px 10px;text-align:right;color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;white-space:nowrap">Ant. USD</th>
            <th style="padding:8px 10px;text-align:right;color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;white-space:nowrap">Δ%</th>
            <th style="padding:8px 10px;text-align:center;color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;white-space:nowrap">Fuente</th>
            <th style="padding:8px 10px;text-align:center;color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;white-space:nowrap">Actualiz.</th>
            <th style="padding:8px 10px;text-align:center;color:var(--muted);font-weight:600;font-size:10px;text-transform:uppercase;white-space:nowrap">ML</th>
          </tr>
        </thead>
        <tbody id="pl-tbody"></tbody>
      </table>
    </div>

    <div style="padding:10px 20px;border-top:1px solid var(--border);font-size:11px;color:var(--muted);text-align:right">
      <span id="pl-count"></span>
    </div>
  </div>
</div>

<!-- MODAL: Editor inline de costos por zona -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9000;align-items:center;justify-content:center">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:440px;max-width:95vw">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-size:14px;font-weight:700;color:var(--gold)" id="edit-modal-title">Editar costos</div>
        <div style="font-size:11px;color:var(--muted)" id="edit-modal-sub"></div>
      </div>
      <button onclick="closeEditModal()" style="background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer">✕</button>
    </div>
    <div style="padding:16px 20px">
      <div style="font-size:11px;color:var(--muted);margin-bottom:12px">Costo de construcción en USD/m² para cada calidad</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px" id="edit-fields"></div>
      <div id="edit-msg" style="font-size:11px;margin-top:10px;color:var(--green);display:none"></div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end">
      <button onclick="closeEditModal()" style="padding:7px 18px;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:7px;cursor:pointer;font-size:12px">Cancelar</button>
      <button onclick="saveZoneCosts()" style="padding:7px 18px;background:var(--gold);border:none;color:#000;font-weight:700;border-radius:7px;cursor:pointer;font-size:12px">💾 Guardar</button>
    </div>
  </div>
</div>

<!-- MODAL: Desglose completo de zona -->
<div id="detail-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:9000;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto" onclick="if(event.target===this)closeDetailModal()">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:560px;max-width:100%;margin:auto">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
      <div>
        <div style="font-size:16px;font-weight:700;color:var(--text)" id="dm-title"></div>
        <div style="font-size:11px;color:var(--muted);margin-top:2px" id="dm-sub"></div>
      </div>
      <button onclick="closeDetailModal()" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;flex-shrink:0;line-height:1">✕</button>
    </div>
    <div id="dm-body" style="padding:16px 20px"></div>
  </div>
</div>

<!-- MAIN SPLIT -->
<div class="main">
  <div id="map"></div>

  <div class="sidebar">
    <div class="sidebar-header" id="sidebar-title">Seleccioná una zona en el mapa</div>
    <div class="sidebar-scroll">

      <!-- Leyenda de color -->
      <div class="legend-box">
        <h4>Escala de calor</h4>
        <div class="legend-scale" id="legend-scale">
          <div style="background:#4caf50"></div>
          <div style="background:#8bc34a"></div>
          <div style="background:#cddc39"></div>
          <div style="background:#ffeb3b"></div>
          <div style="background:#ffc107"></div>
          <div style="background:#ff9800"></div>
          <div style="background:#ff5722"></div>
          <div style="background:#f44336"></div>
        </div>
        <div class="legend-labels">
          <span id="legend-min">Bajo</span>
          <span id="legend-mid">Medio</span>
          <span id="legend-max">Alto</span>
        </div>
      </div>

      <!-- Detalle zona seleccionada -->
      <div id="zone-detail" style="display:none"></div>

      <!-- Resumen todas las zonas -->
      <div class="legend-box" style="margin-top:4px">
        <h4>Ranking de zonas <span id="quality-label-rank">— Estándar</span></h4>
        <div id="zone-ranking"></div>
      </div>
    </div>

    <!-- Tabla resumen -->
    <div class="summary-section">
      <h4>Resumen por ciudad · <span id="quality-label-tbl">Estándar</span></h4>
      <table class="summary" id="summary-table">
        <thead><tr><th>Ciudad</th><th>Mín USD/m²</th><th>Máx USD/m²</th><th>Promedio</th></tr></thead>
        <tbody id="summary-body"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const RAW = <?=$mapDataJson?>;
const USD_RATE = <?=$usdRate?>;
let map, markers = [], mode = 'cost', currentCity = '', currentQuality = 'estandar';

// Paleta de calor (verde → rojo)
function heatColor(t) { // t: 0→1
  const stops = [
    [0,    [76,175,80]],   // verde
    [0.25, [205,220,57]],  // amarillo-verde
    [0.5,  [255,235,59]],  // amarillo
    [0.65, [255,152,0]],   // naranja
    [0.85, [255,87,34]],   // naranja-rojo
    [1,    [244,67,54]],   // rojo
  ];
  for (let i = 0; i < stops.length - 1; i++) {
    const [t0, c0] = stops[i], [t1, c1] = stops[i+1];
    if (t >= t0 && t <= t1) {
      const f = (t - t0)/(t1 - t0);
      const r = Math.round(c0[0]+(c1[0]-c0[0])*f);
      const g = Math.round(c0[1]+(c1[1]-c0[1])*f);
      const b = Math.round(c0[2]+(c1[2]-c0[2])*f);
      return `rgb(${r},${g},${b})`;
    }
  }
  return '#f44336';
}

function getVal(z, q, m) {
  if (m === 'cost')  return z.costs[q] || 0;
  if (m === 'price') return z.price_avg || 0;
  if (m === 'ratio') return z.ratio || 0;
  return 0;
}

function getModeLabel(m) {
  if (m === 'cost')  return 'Costo construcción (USD/m²)';
  if (m === 'price') return 'Precio mercado (USD/m²)';
  if (m === 'ratio') return 'Ratio Mercado / Construcción';
}

function fmt(n) { return Number(n||0).toLocaleString('es-AR'); }

function getFiltered() {
  return currentCity ? RAW.filter(z => z.city === currentCity) : RAW;
}

function refreshMap() {
  currentQuality = document.getElementById('sel-quality').value;
  const qLabels = {economica:'Económica',estandar:'Estándar',calidad:'Calidad',premium:'Premium'};
  document.getElementById('quality-label-rank').textContent = '— ' + qLabels[currentQuality];
  document.getElementById('quality-label-tbl').textContent  = qLabels[currentQuality];

  const data = getFiltered();
  const vals = data.map(z => getVal(z, currentQuality, mode)).filter(v => v > 0);
  const minV = Math.min(...vals), maxV = Math.max(...vals);

  // Actualizar leyenda
  if (mode === 'cost') {
    document.getElementById('legend-min').textContent = 'USD ' + fmt(Math.round(minV));
    document.getElementById('legend-mid').textContent = 'USD ' + fmt(Math.round((minV+maxV)/2));
    document.getElementById('legend-max').textContent = 'USD ' + fmt(Math.round(maxV));
  } else if (mode === 'price') {
    document.getElementById('legend-min').textContent = 'USD ' + fmt(Math.round(minV));
    document.getElementById('legend-mid').textContent = 'USD ' + fmt(Math.round((minV+maxV)/2));
    document.getElementById('legend-max').textContent = 'USD ' + fmt(Math.round(maxV));
  } else {
    document.getElementById('legend-min').textContent = '×' + minV.toFixed(2) + ' (construir)';
    document.getElementById('legend-mid').textContent = '×' + ((minV+maxV)/2).toFixed(2);
    document.getElementById('legend-max').textContent = '×' + maxV.toFixed(2) + ' (comprar)';
  }

  // Limpiar markers
  markers.forEach(m => map.removeLayer(m));
  markers = [];

  data.forEach(z => {
    const v = getVal(z, currentQuality, mode);
    if (!v || !z.lat || !z.lng) return;
    const t = maxV > minV ? (v - minV)/(maxV - minV) : 0.5;
    const color = heatColor(t);

    // Círculo de calor
    const circle = L.circle([z.lat, z.lng], {
      radius: currentCity ? 1200 : 2200,
      color: color,
      fillColor: color,
      fillOpacity: 0.55,
      weight: 2,
      opacity: 0.85,
    });

    // Popup
    const ratioColor = !z.ratio ? '#888' : z.ratio < 0.85 ? '#4caf50' : z.ratio < 1.1 ? '#ffeb3b' : z.ratio < 1.3 ? '#ff9800' : '#f44336';
    const ratioTxt = !z.ratio ? '—' : (z.ratio < 0.85 ? '✅ Conviene construir' : z.ratio < 1.1 ? '⚖️ Similar' : z.ratio < 1.3 ? '🏠 Conviene comprar' : '💰 Muy conveniente comprar');

    circle.bindPopup(`
      <div style="min-width:220px">
        <div style="font-weight:700;font-size:14px;margin-bottom:2px">${z.zoneLabel}</div>
        <div style="font-size:11px;color:#888;margin-bottom:10px">${z.cityLabel}</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px">
          <div style="background:rgba(255,255,255,.06);border-radius:6px;padding:7px;text-align:center">
            <div style="font-size:9px;color:#888;text-transform:uppercase">Costo construcción</div>
            <div style="font-size:15px;font-weight:700;color:#c9a84c">USD ${fmt(z.costs[currentQuality])}/m²</div>
            <div style="font-size:10px;color:#888">Calidad ${currentQuality}</div>
          </div>
          <div style="background:rgba(255,255,255,.06);border-radius:6px;padding:7px;text-align:center">
            <div style="font-size:9px;color:#888;text-transform:uppercase">Precio mercado</div>
            <div style="font-size:15px;font-weight:700;color:#4a8ff7">USD ${fmt(z.price_avg)}/m²</div>
            <div style="font-size:10px;color:#888">${fmt(z.price_min)}–${fmt(z.price_max)}</div>
          </div>
        </div>
        <div style="padding:8px;background:rgba(255,255,255,.04);border-radius:6px;font-size:12px;text-align:center">
          Ratio: <strong style="color:${ratioColor}">×${z.ratio||'—'}</strong> &nbsp;·&nbsp; ${ratioTxt}
        </div>
        ${z.desc?`<div style="font-size:11px;color:#888;margin-top:8px">${z.desc}</div>`:''}
        <div style="margin-top:10px;text-align:center">
          <button onclick="detailFromPopup('${z.city}','${z.zone}')"
            style="padding:5px 14px;background:#c9a84c;color:#000;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer">
            Ver desglose completo
          </button>
        </div>
      </div>
    `);

    circle.on('click', () => showDetail(z.city, z.zone));
    circle.addTo(map);
    markers.push(circle);
  });

  // Fit bounds
  if (data.length > 0 && markers.length > 0) {
    const group = L.featureGroup(markers);
    if (currentCity) map.fitBounds(group.getBounds().pad(0.15));
  }

  updateRanking(data);
  updateSummary(data);
}

function showDetail(city, zone) {
  const z = RAW.find(x => x.city === city && x.zone === zone);
  if (!z) { console.warn('showDetail: zona no encontrada', city, zone); return; }
  document.getElementById('sidebar-title').textContent = z.zoneLabel;
  const q = currentQuality;
  const costC = z.costs[q] || z.costs.estandar || 950;
  const costE = z.costs.estandar || 950;
  const ratioColor = !z.ratio ? '#888' : z.ratio < 0.85 ? '#4caf50' : z.ratio < 1.1 ? '#ffeb3b' : z.ratio < 1.3 ? '#ff9800' : '#f44336';
  const conveniencia = !z.ratio ? '' : z.ratio < 0.85 ? 'Conviene construir desde cero' : z.ratio < 1.1 ? 'Costo similar a construir' : z.ratio < 1.3 ? 'Levemente conveniente comprar' : 'Muy conveniente comprar vs construir';

  // Desglose por calidad
  const qDefs = [
    {k:'economica',l:'Económica',cl:'eco',d:'Sin terminaciones finas, materiales básicos'},
    {k:'estandar', l:'Estándar', cl:'std',d:'Terminaciones medias, materiales estándar'},
    {k:'calidad',  l:'Calidad',  cl:'cal',d:'Materiales de 1ª, buenas terminaciones'},
    {k:'premium',  l:'Premium',  cl:'prm',d:'Alta gama, diseño, domótica'},
  ];

  // Breakdown estándar (aprox)
  const pcts = [['🏗 Estructura',22],['🧱 Mampostería',18],['🏠 Cubierta',11],['⚡ Inst. Eléctricas',8.5],['🚿 Inst. Sanitarias',8],['🔥 Gas',4.5],['🎨 Terminaciones',21],['📐 Honorarios',4.5],['⚠️ Imprevistos',2.5]];
  const breakdownRows = pcts.map(([lbl,pct])=>{
    const usd = Math.round(costC * pct / 100 * 65); // 65m² referencia
    return `<tr><td>${lbl}</td><td style="text-align:right;color:var(--muted)">${pct}%</td><td style="text-align:right;color:var(--gold)">USD ${fmt(usd)}</td></tr>`;
  }).join('');

  document.getElementById('zone-detail').style.display = 'block';
  document.getElementById('zone-detail').innerHTML = `
    <div class="zone-card">
      <h3>${z.zoneLabel}</h3>
      <div class="city-tag">${z.cityLabel} · ${z.desc||''}</div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px">Costo construcción USD/m²</div>
        <button data-city="${z.city}" data-zone="${z.zone}" onclick="openEditFromData(this)"
          style="padding:3px 10px;background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.4);color:var(--gold);border-radius:6px;font-size:10px;cursor:pointer;font-weight:600">✏️ Editar</button>
      </div>
      <div class="cost-grid">
        ${qDefs.map(qd=>`
        <div class="cost-cell">
          <div class="qlabel">${qd.l}</div>
          <div class="qval ${qd.cl}">USD ${fmt(z.costs[qd.k])}</div>
          <div style="font-size:9px;color:var(--muted)">${qd.d.substring(0,30)}…</div>
        </div>`).join('')}
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;margin-bottom:10px">
        <div style="background:var(--bg);border-radius:6px;padding:8px;text-align:center">
          <div style="font-size:9px;color:var(--muted)">PRECIO MERCADO</div>
          <div style="font-size:16px;font-weight:700;color:var(--blue)">USD ${fmt(z.price_avg)}</div>
          <div style="font-size:10px;color:var(--muted)">${fmt(z.price_min)} – ${fmt(z.price_max)}/m²</div>
        </div>
        <div style="background:var(--bg);border-radius:6px;padding:8px;text-align:center">
          <div style="font-size:9px;color:var(--muted)">RATIO COMPRA/OBRA</div>
          <div style="font-size:16px;font-weight:700;color:${ratioColor}">×${z.ratio||'—'}</div>
          <div style="font-size:10px;color:var(--muted)">${conveniencia}</div>
        </div>
      </div>

      ${z.price_avg>0?`
      <div class="ratio-bar">
        <div class="ratio-label">Incidencia estimada del terreno (m² calidad estándar):</div>
        <div class="ratio-track">
          <div class="ratio-fill" style="width:${Math.min(100,Math.max(0,Math.round((Math.max(0,z.price_avg-costE)/z.price_avg)*100)))}%;background:var(--blue)"></div>
        </div>
        <div class="ratio-txt" style="color:var(--blue)">
          ~${Math.round((Math.max(0,z.price_avg-costE)/z.price_avg)*100)}% terreno ·
          ~USD ${fmt(Math.max(0,z.price_avg-costE))}/m² valor suelo
        </div>
      </div>`:''}

      <div style="margin-top:12px;font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px">Desglose por rubro · 65m² estándar</div>
      <table style="width:100%;font-size:11px;border-collapse:collapse">
        <thead><tr style="color:var(--muted)"><th style="text-align:left;padding:3px 4px">Rubro</th><th style="text-align:right;padding:3px 4px">%</th><th style="text-align:right;padding:3px 4px">Costo</th></tr></thead>
        <tbody style="color:var(--text)">${breakdownRows}</tbody>
        <tfoot><tr style="border-top:1px solid var(--border)">
          <td colspan="2" style="padding:5px 4px;font-weight:700">TOTAL 65m²</td>
          <td style="text-align:right;font-weight:700;color:var(--gold);padding:5px 4px">USD ${fmt(Math.round(costC*65))}</td>
        </tr></tfoot>
      </table>
    </div>
  `;
}

function updateRanking(data) {
  const q = currentQuality;
  const sorted = [...data].filter(z=>z.costs[q]).sort((a,b)=>b.costs[q]-a.costs[q]);
  const top = sorted.slice(0, 12);
  const maxC = top[0]?.costs[q]||1;
  document.getElementById('zone-ranking').innerHTML = top.map(z=>{
    const pct = Math.round(z.costs[q]/maxC*100);
    const color = heatColor((z.costs[q]-sorted[sorted.length-1].costs[q])/(maxC-sorted[sorted.length-1].costs[q]+1));
    return `<div style="margin-bottom:7px">
      <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px">
        <span>${z.zoneLabel} <span style="color:var(--muted);font-size:10px">${z.cityLabel}</span></span>
        <strong style="color:${color}">USD ${fmt(z.costs[q])}</strong>
      </div>
      <div style="background:var(--bg);border-radius:3px;height:5px;overflow:hidden">
        <div style="width:${pct}%;height:100%;background:${color};border-radius:3px"></div>
      </div>
    </div>`;
  }).join('');
}

function updateSummary(data) {
  const q = currentQuality;
  const byCityMap = {};
  data.forEach(z=>{
    const v = z.costs[q];
    if (!v) return;
    if (!byCityMap[z.city]) byCityMap[z.city]={label:z.cityLabel,vals:[]};
    byCityMap[z.city].vals.push(v);
  });
  const rows = Object.values(byCityMap).map(c=>{
    const mn = Math.min(...c.vals), mx = Math.max(...c.vals), av = Math.round(c.vals.reduce((a,b)=>a+b,0)/c.vals.length);
    return `<tr><td>${c.label}</td><td>USD ${fmt(mn)}</td><td>USD ${fmt(mx)}</td><td><strong>USD ${fmt(av)}</strong></td></tr>`;
  });
  document.getElementById('summary-body').innerHTML = rows.join('');
}

function setMode(m) {
  mode = m;
  ['cost','price','ratio'].forEach(x => document.getElementById('mode-'+x).classList.toggle('active', x===m));
  refreshMap();
}

function filterCity() {
  currentCity = document.getElementById('sel-city').value;
  const cityData = RAW.find(z=>z.city===currentCity);
  if (cityData) map.setView([cityData.lat, cityData.lng], 13);
  else map.setView([-32, -63], 5);
  refreshMap();
}

function exportCSV() {
  const q = currentQuality;
  const rows = [['Ciudad','Zona','Costo USD/m² '+q,'Precio mercado USD/m²','Ratio','Descripción']];
  getFiltered().forEach(z=>{
    rows.push([z.cityLabel, z.zoneLabel, z.costs[q]||'', z.price_avg||'', z.ratio||'', z.desc||'']);
  });
  const csv = rows.map(r=>r.map(v=>'"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,\uFEFF'+encodeURIComponent(csv);
  a.download = 'bim_costos_'+q+'_'+new Date().toISOString().slice(0,10)+'.csv';
  a.click();
}

// ── HELPERS PARA BOTONES CON DATA ATTRIBUTES ─────────────────
function openEditFromData(btn) {
  const z = RAW.find(x => x.city === btn.dataset.city && x.zone === btn.dataset.zone);
  if (z) openEditModal(z.city, z.zone, z.zoneLabel, z.cityLabel, z.costs);
}
function detailFromPopup(city, zone) {
  map.closePopup();
  const z = RAW.find(x => x.city === city && x.zone === zone);
  if (!z) return;

  const q = currentQuality;
  const qLabels = {economica:'Económica',estandar:'Estándar',calidad:'Calidad',premium:'Premium'};
  const qDefs = [
    {k:'economica',l:'Económica',cl:'#4caf50',d:'Materiales básicos, sin terminaciones finas'},
    {k:'estandar', l:'Estándar', cl:'#c9a84c',d:'Terminaciones medias, materiales estándar'},
    {k:'calidad',  l:'Calidad',  cl:'#ff9800',d:'Materiales de 1ª, buenas terminaciones'},
    {k:'premium',  l:'Premium',  cl:'#f44336',d:'Alta gama, diseño, domótica'},
  ];
  const costC = z.costs[q] || z.costs.estandar || 950;
  const costE = z.costs.estandar || 950;
  const ratioColor = !z.ratio ? '#888' : z.ratio < 0.85 ? '#4caf50' : z.ratio < 1.1 ? '#ffeb3b' : z.ratio < 1.3 ? '#ff9800' : '#f44336';
  const conveniencia = !z.ratio ? '—' : z.ratio < 0.85 ? '✅ Conviene construir' : z.ratio < 1.1 ? '⚖️ Similar' : z.ratio < 1.3 ? '🏠 Conviene comprar' : '💰 Muy conveniente comprar';
  const pcts = [['🏗 Estructura',22],['🧱 Mampostería',18],['🏠 Cubierta',11],['⚡ Inst. Eléctricas',8.5],['🚿 Inst. Sanitarias',8],['🔥 Gas',4.5],['🎨 Terminaciones',21],['📐 Honorarios',4.5],['⚠️ Imprevistos',2.5]];

  document.getElementById('dm-title').textContent = z.zoneLabel;
  document.getElementById('dm-sub').textContent = z.cityLabel + (z.desc ? ' · ' + z.desc : '');

  document.getElementById('dm-body').innerHTML = `
    <!-- Grid de calidades -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
      ${qDefs.map(qd=>`
        <div style="background:var(--bg3);border:1.5px solid ${q===qd.k?qd.cl:'var(--border)'};border-radius:8px;padding:10px;text-align:center">
          <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">${qd.l}</div>
          <div style="font-size:18px;font-weight:800;color:${qd.cl}">USD ${fmt(z.costs[qd.k]||0)}</div>
          <div style="font-size:9px;color:var(--muted)">/m²</div>
          <div style="font-size:9px;color:var(--muted);margin-top:3px">${qd.d}</div>
        </div>`).join('')}
    </div>

    <!-- Precio mercado + ratio -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
      <div style="background:var(--bg3);border-radius:8px;padding:10px;text-align:center">
        <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Precio mercado</div>
        <div style="font-size:18px;font-weight:800;color:var(--blue)">USD ${fmt(z.price_avg)}</div>
        <div style="font-size:10px;color:var(--muted)">${fmt(z.price_min)}–${fmt(z.price_max)}/m²</div>
      </div>
      <div style="background:var(--bg3);border-radius:8px;padding:10px;text-align:center">
        <div style="font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Ratio compra/obra</div>
        <div style="font-size:18px;font-weight:800;color:${ratioColor}">×${z.ratio||'—'}</div>
        <div style="font-size:10px;color:${ratioColor}">${conveniencia}</div>
      </div>
    </div>

    ${z.price_avg>0?`
    <!-- Barra incidencia terreno -->
    <div style="background:var(--bg3);border-radius:8px;padding:10px;margin-bottom:14px">
      <div style="font-size:10px;color:var(--muted);margin-bottom:6px">Incidencia estimada del terreno (calidad estándar)</div>
      <div style="background:var(--bg);border-radius:4px;height:8px;overflow:hidden;margin-bottom:4px">
        <div style="width:${Math.min(100,Math.max(0,Math.round((Math.max(0,z.price_avg-costE)/z.price_avg)*100)))}%;height:100%;background:var(--blue);border-radius:4px"></div>
      </div>
      <div style="font-size:11px;color:var(--blue)">
        ~${Math.round((Math.max(0,z.price_avg-costE)/z.price_avg)*100)}% terreno ·
        ~USD ${fmt(Math.max(0,z.price_avg-costE))}/m² valor suelo
      </div>
    </div>`:''}

    <!-- Desglose por rubro -->
    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:8px">
      Desglose por rubro · 65m² · Calidad ${qLabels[q]||q}
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead><tr style="color:var(--muted);border-bottom:1px solid var(--border)">
        <th style="text-align:left;padding:5px 6px">Rubro</th>
        <th style="text-align:right;padding:5px 6px">%</th>
        <th style="text-align:right;padding:5px 6px">65m²</th>
      </tr></thead>
      <tbody>
        ${pcts.map(([lbl,pct])=>{
          const usd = Math.round(costC * pct / 100 * 65);
          return `<tr style="border-bottom:1px solid rgba(255,255,255,.04)">
            <td style="padding:6px">${lbl}</td>
            <td style="text-align:right;padding:6px;color:var(--muted)">${pct}%</td>
            <td style="text-align:right;padding:6px;font-weight:600;color:var(--gold)">USD ${fmt(usd)}</td>
          </tr>`;
        }).join('')}
      </tbody>
      <tfoot><tr style="border-top:2px solid var(--border)">
        <td colspan="2" style="padding:8px 6px;font-weight:700">TOTAL 65m²</td>
        <td style="text-align:right;padding:8px 6px;font-weight:800;font-size:14px;color:var(--gold)">USD ${fmt(Math.round(costC*65))}</td>
      </tr></tfoot>
    </table>
    <div style="margin-top:10px;font-size:10px;color:var(--muted);text-align:center">
      USD/ARS: ${USD_RATE.toLocaleString('es-AR')} · Los valores son estimativos y varían según proyecto y contratista.
    </div>
  `;

  document.getElementById('detail-modal').style.display = 'flex';
}
function closeDetailModal() {
  document.getElementById('detail-modal').style.display = 'none';
}

// ── EDITOR INLINE DE COSTOS ──────────────────────────────────
let _editCity='', _editZone='';
const qNames = {economica:'Económica',estandar:'Estándar',calidad:'Calidad',premium:'Premium'};

function openEditModal(city, zone, zoneLabel, cityLabel, costs) {
  _editCity = city; _editZone = zone;
  document.getElementById('edit-modal-title').textContent = '✏️ ' + zoneLabel;
  document.getElementById('edit-modal-sub').textContent = cityLabel;
  document.getElementById('edit-msg').style.display = 'none';
  const fields = document.getElementById('edit-fields');
  fields.innerHTML = Object.entries(qNames).map(([k,l]) => `
    <div>
      <label style="font-size:10px;color:var(--muted);display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.4px">${l}</label>
      <input id="edit-${k}" type="number" value="${costs[k]||''}" placeholder="USD/m²"
        style="width:100%;background:var(--bg);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:6px;font-size:13px;font-weight:600">
    </div>`).join('');
  document.getElementById('edit-modal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('edit-modal').style.display = 'none';
}

async function saveZoneCosts() {
  const costs = {};
  for (const k of Object.keys(qNames)) {
    const v = parseFloat(document.getElementById('edit-'+k).value);
    if (!isNaN(v) && v > 0) costs[k] = v;
  }
  if (!Object.keys(costs).length) return;
  const msg = document.getElementById('edit-msg');
  msg.style.display = 'none';
  try {
    const r = await fetch('admin_bim.php?action=save_costs', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({city:_editCity, zone:_editZone, costs})
    });
    const j = await r.json();
    if (j.success) {
      msg.style.color='var(--green)';
      msg.textContent = '✓ Guardado. Los costos se actualizarán en el mapa al cerrar.';
      msg.style.display = 'block';
      // Actualizar RAW local para reflejo inmediato
      const z = RAW.find(d=>d.city===_editCity && d.zone===_editZone);
      if (z) Object.assign(z.costs, costs);
      setTimeout(()=>{ refreshMap(); closeEditModal(); }, 1200);
    } else {
      msg.style.color='#f44336'; msg.textContent = '⚠ Error: '+(j.error||'desconocido'); msg.style.display='block';
    }
  } catch(e) {
    msg.style.color='#f44336'; msg.textContent = '⚠ Error de conexión'; msg.style.display='block';
  }
}

// ── MATERIALES Y MAPEO ML (client-side) ──────────────────────
const MATERIALS = <?= $materialsJson ?>;

// Normaliza texto para matching
function normText(s) {
  return s.toLowerCase()
    .replace(/[áàä]/g,'a').replace(/[éèë]/g,'e').replace(/[íìï]/g,'i')
    .replace(/[óòö]/g,'o').replace(/[úùü]/g,'u').replace(/ñ/g,'n')
    .replace(/[ø×°²³]/g,' ');
}

// Mapeo nombre → query de búsqueda (compatible con Easy.com.ar / Sodimac / ML)
const ML_QUERIES = [
  // ── ESTRUCTURAL ──────────────────────────────────────────────
  ['cemento portland',             'cemento portland 50kg'],
  ['hierro.*ø8|hierro.*8mm|barra.*8mm',  'hierro redondo 8mm barra'],
  ['hierro.*ø10|hierro.*10mm|barra.*10mm','hierro redondo 10mm barra'],
  ['hierro.*ø12|hierro.*12mm|barra.*12mm','hierro redondo 12mm barra'],
  ['hierro.*ø16|hierro.*16mm|barra.*16mm','hierro redondo 16mm barra'],
  ['hierro.*viga|caño.*viga|viga.*hormig', 'viga hierro doble t construccion'],
  ['hierro.*pletina|pletina',              'pletina hierro plana'],
  ['malla.*sima|malla.*hierro|malla.*electros','malla sima electrosoldada panel'],
  ['hierro',                         'hierro barra redondo construccion'],
  // ── ÁRIDOS Y GRANULADOS ──────────────────────────────────────
  ['arena gruesa',       'arena gruesa bolsa construccion'],
  ['arena fina',         'arena fina bolsa revoque'],
  ['arena',              'arena construccion bolsa'],
  ['piedra partida',     'piedra partida triturada bolsa'],
  ['canto rodado|ripio', 'canto rodado ripio bolsa'],
  ['piedra.*laja|laja',  'piedra laja exterior'],
  ['piedra',             'piedra triturada construccion'],
  // ── MAMPOSTERÍA ──────────────────────────────────────────────
  ['block.*hormig|bloque.*hormig',          'block hormigon 20x20x40'],
  ['ladrillo.*macizo',                      'ladrillo macizo ceramico'],
  ['ladrillo.*cerámico|ladrillo.*ceramico|ladrillo.*hueco','ladrillo ceramico hueco 18x18x33'],
  ['ladrillo',                              'ladrillo ceramico construccion'],
  // ── HORMIGÓN ─────────────────────────────────────────────────
  ['hormigon.*premix|premix.*h.?21',        'hormigon premix h21'],
  ['hormigon.*contrapiso|contrapiso.*h.?8', 'hormigon contrapiso'],
  // ── AGLOMERANTES ─────────────────────────────────────────────
  ['cal hidratada',      'cal hidratada bolsa 30kg'],
  ['cal.*aerea|cal.*viva','cal aerea bolsa'],
  ['yeso',               'yeso bolsa fino'],
  ['adhesivo.*cerám|pegamento.*cerám|klaukol','adhesivo ceramico 30kg flexible'],
  ['pastina|junta.*ceram','pastina ceramica juntas'],
  ['masilla',            'masilla plastica interior balde'],
  // ── CUBIERTA / IMPERMEABILIZACIÓN ────────────────────────────
  ['membrana asfált|membrana asf','membrana asfaltica 4mm'],
  ['membrana',           'membrana asfaltica impermeabilizante'],
  ['barrera.*vapor',     'barrera vapor polietileno rollo'],
  ['chapa.*acanalada|chapa.*galvaniz','chapa galvanizada acanalada'],
  ['chapa.*prepint|chapa.*color',    'chapa prepintada color techo'],
  ['teja.*colonial|teja.*española',  'teja colonial ceramica'],
  ['teja.*hormig',                   'teja hormigon'],
  ['teja',                           'teja ceramica construccion'],
  // ── AISLAMIENTO ──────────────────────────────────────────────
  ['lana.*vidrio|lana.*mineral|aislante.*lana','lana de vidrio aislante rollo'],
  ['aislante.*eps|eps.*mm',  'panel aislante eps 50mm'],
  ['aislante',               'aislante termico construccion'],
  // ── ESTRUCTURA MADERA Y METAL ────────────────────────────────
  ['perfil.*c.*galv',               'perfil c galvanizado 100x50'],
  ['viga.*madera|madera.*quebracho|quebracho','viga madera estructural'],
  // ── ELECTRICIDAD ─────────────────────────────────────────────
  ['cable.*2[,.]5|cable.*2\.5',     'cable unipolar 2.5mm rollo'],
  ['cable.*4mm',                    'cable unipolar 4mm rollo'],
  ['cable',                         'cable electrico iram rollo'],
  ['cañería corrugada|caño.*corrugado','cañeria corrugada electrica 20mm'],
  ['caño.*conduit|conduit',         'caño conduit electrico 20mm'],
  ['tablero.*eléctrico|tablero.*electrico|tablero.*circuito','tablero electrico domiciliario'],
  ['disyuntor',                     'disyuntor termomagnetico'],
  ['llave térmica|llave termica',   'llave termica termomagnetica'],
  ['tomacorriente',                 'tomacorriente iram'],
  // ── PLOMERÍA / SANITARIO ─────────────────────────────────────
  ['caño.*pvc.*110|pvc.*sanitario.*110','caño pvc 110mm desague'],
  ['caño.*pvc.*32|pvc.*presión|caño.*pvc.*presion','caño pvc presion 32mm'],
  ['caño.*pvc',                     'caño pvc desague'],
  ['caño.*cobre',                   'caño cobre 1/2 pulgada'],
  ['inodoro',                       'inodoro ceramica'],
  ['pileta.*cocina|pileta.*acero',  'pileta cocina acero inoxidable'],
  ['lavatorio',                     'lavatorio baño ceramica'],
  ['ducha',                         'ducha electrica'],
  ['grifería.*monoc|griferia.*monoc','griferia monocomando'],
  ['grifería|griferia',             'griferia baño cromada'],
  ['termotanque',                   'termotanque electrico 80 litros'],
  // ── GAS / CALEFACCIÓN ────────────────────────────────────────
  ['cocina.*gas|cocina.*horna',     'cocina gas 4 hornallas'],
  ['calefacc',                      'calefactor tiro balanceado gas'],
  // ── REVESTIMIENTOS Y PISOS ───────────────────────────────────
  ['porcelanato.*rectif',           'porcelanato rectificado 60x60'],
  ['porcelanato.*madera',           'porcelanato simil madera'],
  ['porcelanato',                   'porcelanato piso 60x60'],
  ['cerámica.*antidesliz|ceramica.*antidesliz','ceramica antideslizante piso'],
  ['cerámica|ceramica',             'ceramica piso caja'],
  ['azulejo.*blanco|azulejo',       'azulejo 20x30 caja'],
  ['revestimiento.*ceram',          'revestimiento ceramico pared baño'],
  ['mosaico',                       'mosaico calcáreo piso'],
  ['parquet flotante',              'parquet flotante laminado'],
  ['microcemento',                  'microcemento piso'],
  ['piedra.*laja|laja',             'piedra laja exterior'],
  // ── PINTURA ──────────────────────────────────────────────────
  ['pintura.*látex.*lavable|pintura.*latex.*lavable','pintura latex lavable interior 10 litros'],
  ['pintura.*exterior|pintura.*frente','pintura exterior frente'],
  ['pintura.*látex|pintura.*latex', 'pintura latex interior 10 litros'],
  ['pintura',                       'pintura latex construccion'],
  // ── CARPINTERÍA ──────────────────────────────────────────────
  ['puerta.*placa',   'puerta placa interior 80x200'],
  ['puerta.*pvc',     'puerta pvc exterior'],
  ['puerta',          'puerta interior madera'],
  ['ventana.*dvh|dvh','ventana aluminio dvh doble vidrio'],
  ['ventana.*corrediza','ventana aluminio corrediza'],
  ['ventana',         'ventana aluminio vidrio'],
  // ── SANITARIOS VARIOS ────────────────────────────────────────
  ['artefacto.*sanitario','inodoro ceramica'],
];

function findMlQuery(name) {
  const n = normText(name); // normalizado: sin acentos, minúsculas
  for (const [pattern, query] of ML_QUERIES) {
    // También normalizar el patrón para comparación consistente
    if (new RegExp(normText(pattern)).test(n)) return query;
  }
  return null;
}

// Flete por provincia destino (desde CABA/GBA)
const FLETE = {'AR-S':9,'AR-X':7.5,'AR-T':14,'AR-M':13,'AR-N':16,'AR-C':0,'AR-B':2};
const CITY_STATE = {
  santa_fe_capital:'AR-S',rosario:'AR-S',cordoba:'AR-X',
  buenos_aires:'AR-C',puerto_madero:'AR-C',gba_norte:'AR-B'
};
function calcFlete(sellerStateId, destCity) {
  if (['AR-C','AR-B'].includes(sellerStateId)) {
    const dest = CITY_STATE[destCity] || '';
    if (dest && !['AR-C','AR-B'].includes(dest)) return FLETE[dest] || 10;
  }
  return 0;
}

// Búsqueda de precios a través del proxy PHP multi-fuente (Easy / Sodimac / ML)
let _mlToken = '';
let _priceSource = '';
async function mlSearch(query) {
  const proxyUrl = `api/price_proxy.php?q=${encodeURIComponent(query)}&limit=10`;
  const r = await fetch(proxyUrl);
  if (!r.ok) {
    let errMsg = 'http:' + r.status;
    try { const ej = await r.json(); errMsg = ej.error || errMsg; } catch(_) {}
    throw new Error(errMsg);
  }
  const data = await r.json();
  if (data.error) throw new Error(data.error);
  if (data.source && data.source !== _priceSource) {
    _priceSource = data.source;
    const lbl = document.getElementById('ml-token-label');
    if (lbl) {
      const srcLabel = {easy:'Easy.com.ar', sodimac:'Sodimac.com.ar', ml:'MercadoLibre'}[data.source] || data.source;
      lbl.innerHTML = `<strong style="color:var(--green)">✓ Fuente activa: ${srcLabel}</strong> — precios en ARS`;
    }
  }
  return data.results || [];
}

// ── ICC INDEC ─────────────────────────────────────────────────
// Datos ICC pendientes de aplicar (guardados entre preview y apply)
let _iccData = null;

function iccPreviewHtml(j) {
  const sign  = j.ratio_pct >= 0 ? '+' : '';
  const color = j.ratio_pct > 0 ? '#f44336' : j.ratio_pct < 0 ? 'var(--green)' : 'var(--muted)';
  const src   = j.icc_current?.source === 'manual' ? ' (ingresado manualmente)' : ` · serie: ${j.icc_current?.series_id||''}`;
  return `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
      <div style="background:var(--bg);border-radius:8px;padding:12px">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">ICC Base</div>
        <div style="font-size:20px;font-weight:700;margin-top:4px">${j.icc_base.value}</div>
        <div style="font-size:10px;color:var(--muted)">${j.icc_base.date || 'fecha anterior'}</div>
      </div>
      <div style="background:var(--bg);border-radius:8px;padding:12px">
        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">ICC Actual</div>
        <div style="font-size:20px;font-weight:700;margin-top:4px">${j.icc_current.value}</div>
        <div style="font-size:10px;color:var(--muted)">${j.icc_current.date}${src}</div>
      </div>
    </div>
    <div style="background:var(--bg);border-radius:8px;padding:14px;text-align:center;margin-bottom:14px">
      <div style="font-size:11px;color:var(--muted)">Ajuste a aplicar a todos los materiales</div>
      <div style="font-size:32px;font-weight:800;color:${color};margin:6px 0">${sign}${j.ratio_pct}%</div>
      <div style="font-size:11px;color:var(--muted)">× ${j.ratio.toFixed(4)} · ${j.materials_count} materiales</div>
    </div>
    ${j.preview?.length ? `
    <div style="font-size:10px;color:var(--muted);margin-bottom:6px">Vista previa (primeros 10):</div>
    <div style="max-height:140px;overflow-y:auto;font-size:11px;font-family:monospace">
      ${j.preview.map(p=>`<div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid var(--border)">
        <span style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.name}</span>
        <span style="color:var(--muted)">$${p.old_ars.toLocaleString('es-AR')}</span>
        <span style="font-weight:600">$${p.new_ars.toLocaleString('es-AR')}</span>
        <span style="color:${p.delta_pct>0?'#f44336':'var(--green)'}">${p.delta_pct>0?'+':''}${p.delta_pct}%</span>
      </div>`).join('')}
    </div>` : ''}`;
}

function iccManualForm(errorMsg) {
  const now = new Date();
  const defDate = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
  return `
    <div style="background:rgba(244,67,54,.08);border:1px solid rgba(244,67,54,.3);border-radius:8px;padding:12px;margin-bottom:14px;font-size:12px;color:#f44336">
      ⚠ ${errorMsg}
    </div>

    <!-- Opción A: subir CSV -->
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:12px">
      <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:6px">📎 Opción A — Subir CSV del INDEC (recomendado)</div>
      <div style="font-size:11px;color:var(--muted);margin-bottom:10px;line-height:1.6">
        Descargá el CSV desde el
        <a href="https://www.indec.gob.ar/indec/web/Nivel4-Tema-3-5-33" target="_blank"
           style="color:var(--gold)">sitio del INDEC ↗</a>
        → <em>"Serie del Índice del ICC, nivel general y capítulos"</em> → subilo acá.
      </div>
      <label style="display:block;width:100%;padding:9px;background:var(--gold);color:#000;font-weight:700;border-radius:7px;cursor:pointer;font-size:12px;text-align:center;box-sizing:border-box">
        📂 Seleccionar archivo CSV
        <input id="icc-csv-input" type="file" accept=".csv,.CSV" style="display:none" onchange="uploadIccCsv(this)">
      </label>
      <div id="icc-csv-status" style="font-size:11px;color:var(--muted);margin-top:6px;min-height:16px"></div>
    </div>

    <!-- Separador -->
    <div style="text-align:center;font-size:11px;color:var(--muted);margin-bottom:12px">— o bien ingresá el valor a mano —</div>

    <!-- Opción B: manual -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
      <div>
        <label style="font-size:10px;color:var(--muted);display:block;margin-bottom:4px;text-transform:uppercase">ICC Materiales actual</label>
        <input id="icc-manual-val" type="number" step="0.01" placeholder="ej: 285340.71"
          style="width:100%;padding:8px 10px;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:6px;font-size:13px">
      </div>
      <div>
        <label style="font-size:10px;color:var(--muted);display:block;margin-bottom:4px;text-transform:uppercase">Período (año-mes)</label>
        <input id="icc-manual-date" type="month" value="${defDate}"
          style="width:100%;padding:8px 10px;background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:6px;font-size:13px">
      </div>
    </div>
    <button onclick="submitManualIcc()"
      style="width:100%;padding:9px;background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:7px;cursor:pointer;font-size:12px">
      📊 Calcular ajuste con estos valores
    </button>`;
}

async function openIccModal() {
  const modal  = document.getElementById('icc-modal');
  const body   = document.getElementById('icc-body');
  const footer = document.getElementById('icc-footer');
  _iccData = null;
  modal.style.display = 'flex';
  footer.style.display = 'none';
  body.innerHTML = '<div style="color:var(--muted);font-size:13px">⏳ Consultando INDEC (datos.gob.ar)…</div>';

  try {
    const r = await fetch('api/indec_icc.php?preview=1');
    const j = await r.json();

    if (!j.success) {
      body.innerHTML = iccManualForm(j.error || 'No se pudo conectar con datos.gob.ar.');
      return;
    }

    if (j.action === 'baseline_set') {
      body.innerHTML = `
        <div style="color:var(--green);font-weight:600;margin-bottom:10px">✓ ICC base registrado</div>
        <div style="font-size:12px;color:var(--muted)">ICC Materiales INDEC: <strong style="color:var(--text)">${j.icc.value}</strong> (${j.icc.date})</div>
        <div style="font-size:11px;color:var(--muted);margin-top:8px">Los precios actuales quedan como referencia. La próxima vez que abras este panel, se calculará el ajuste desde este punto.</div>`;
      return;
    }

    _iccData = { preview: false };
    body.innerHTML = iccPreviewHtml(j);
    footer.style.display = 'flex';

  } catch(e) {
    body.innerHTML = iccManualForm('Error de red: ' + e.message);
  }
}

async function uploadIccCsv(input) {
  const file = input.files?.[0];
  if (!file) return;
  const status = document.getElementById('icc-csv-status');
  status.textContent = '⏳ Leyendo CSV…';
  status.style.color = 'var(--muted)';

  const fd = new FormData();
  fd.append('file', file);

  try {
    const r = await fetch('api/indec_icc_csv.php', { method:'POST', body: fd });
    const j = await r.json();

    if (!j.success) {
      status.innerHTML = `<span style="color:#f44336">⚠ ${j.error}</span>`;
      return;
    }

    // Llenar los campos del formulario manual con los datos del CSV
    const valInput  = document.getElementById('icc-manual-val');
    const dateInput = document.getElementById('icc-manual-date');
    if (valInput)  valInput.value  = j.value;
    if (dateInput) dateInput.value = j.date;

    status.innerHTML = `<span style="color:var(--green)">
      ✓ ${j.column_name} · Período: ${j.date} · Valor: <strong>${j.value.toLocaleString('es-AR')}</strong>
      ${j.variation_pct != null ? ` · Var. mensual: ${j.variation_pct > 0 ? '+' : ''}${j.variation_pct}%` : ''}
      · ${j.total_rows} períodos leídos
    </span>`;

    // Auto-calcular ajuste con los valores del CSV
    setTimeout(() => submitManualIcc(), 400);

  } catch(e) {
    status.innerHTML = `<span style="color:#f44336">⚠ Error: ${e.message}</span>`;
  }
}

async function submitManualIcc() {
  const val  = parseFloat(document.getElementById('icc-manual-val')?.value);
  const date = document.getElementById('icc-manual-date')?.value;
  if (!val || val <= 0) { alert('Ingresá un valor de ICC válido (ej: 285340.71)'); return; }

  const body   = document.getElementById('icc-body');
  const footer = document.getElementById('icc-footer');
  body.innerHTML = '<div style="color:var(--muted);font-size:13px">⏳ Calculando con valor manual…</div>';

  try {
    const r = await fetch('api/indec_icc.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ preview: true, manual_current: val, manual_date: date })
    });
    const j = await r.json();
    if (!j.success) {
      body.innerHTML = iccManualForm(j.error || 'Error al procesar.');
      return;
    }
    if (j.action === 'baseline_set') {
      body.innerHTML = `
        <div style="color:var(--green);font-weight:600;margin-bottom:10px">✓ ICC base registrado (${val})</div>
        <div style="font-size:12px;color:var(--muted)">Los precios actuales quedan como referencia para futuros ajustes.</div>`;
      return;
    }
    _iccData = { preview: false, manual_current: val, manual_date: date };
    body.innerHTML = iccPreviewHtml(j);
    footer.style.display = 'flex';
  } catch(e) {
    body.innerHTML = iccManualForm('Error: ' + e.message);
  }
}

function closeIccModal() {
  document.getElementById('icc-modal').style.display = 'none';
  _iccData = null;
}

async function applyIcc() {
  const btn  = document.getElementById('icc-apply-btn');
  const body = document.getElementById('icc-body');
  btn.disabled = true; btn.textContent = '⏳ Aplicando…';

  try {
    const r = await fetch('api/indec_icc.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(_iccData || { preview: false })
    });
    const j = await r.json();
    if (j.success) {
      body.innerHTML += `<div style="margin-top:12px;padding:10px 14px;background:rgba(76,175,80,.1);border:1px solid var(--green);border-radius:8px;color:var(--green);font-size:12px">
        ✓ ${j.message}<br>
        <span style="font-size:11px;color:var(--muted)">${j.materials_updated} materiales · ${j.zones_updated} zonas actualizadas</span>
      </div>`;
      document.getElementById('icc-footer').style.display = 'none';
      setTimeout(()=>location.reload(), 2000);
    } else {
      body.innerHTML += `<div style="color:#f44336;margin-top:8px">⚠ ${j.error}</div>`;
      btn.disabled = false; btn.textContent = '✓ Aplicar ajuste';
    }
  } catch(e) {
    body.innerHTML += `<div style="color:#f44336;margin-top:8px">⚠ ${e.message}</div>`;
    btn.disabled = false; btn.textContent = '✓ Aplicar ajuste';
  }
}

// ── LISTA DE PRECIOS ──────────────────────────────────────────
let _plData = [];

const CAT_LABELS = {
  estructura:'Estructura', mamposteria:'Mampostería', morteros:'Morteros',
  impermeabilizacion:'Impermeab.', aislacion:'Aislación', cubiertas:'Cubiertas',
  metalica:'Metalica', electrica:'Eléctrica', electromecanica:'Electromec.',
  sanitaria:'Sanitaria', revestimientos:'Revestim.', pintura:'Pintura',
  carpinteria:'Carpintería', climatizacion:'Climatiz.', varios:'Varios',
};

async function openPriceListModal() {
  const modal = document.getElementById('price-list-modal');
  modal.style.display = 'flex';
  document.getElementById('pl-meta').textContent = 'Cargando precios…';
  document.getElementById('pl-tbody').innerHTML = '<tr><td colspan="9" style="padding:24px;text-align:center;color:var(--muted)">⏳ Consultando base de datos…</td></tr>';
  document.getElementById('pl-stats').innerHTML = '';

  try {
    const r = await fetch('api/get_materials.php');
    const j = await r.json();
    if (!j.success) throw new Error(j.error);

    _plData = j.materials;
    document.getElementById('pl-meta').innerHTML =
      `${j.total} materiales · USD/ARS: $${j.usd_rate.toLocaleString('es-AR')} · Actualizado: ${j.updated_at || '—'}`;

    // Llenar selector de categorías
    const cats = [...new Set(_plData.map(m => m.category))].sort();
    const catSel = document.getElementById('pl-cat');
    catSel.innerHTML = '<option value="">Todas las categorías</option>';
    cats.forEach(c => {
      const o = document.createElement('option');
      o.value = c; o.textContent = CAT_LABELS[c] || c;
      catSel.appendChild(o);
    });

    // Calcular stats
    const withDelta = _plData.filter(m => m.delta_pct !== null);
    const avgDelta  = withDelta.length ? (withDelta.reduce((a,m)=>a+m.delta_pct,0)/withDelta.length).toFixed(1) : null;
    const fromML    = _plData.filter(m => m.source === 'mercadolibre').length;
    const withLink  = _plData.filter(m => m.ml_url).length;

    document.getElementById('pl-stats').innerHTML = [
      ['📦 Materiales', j.total],
      ['🏗 Desde ML', fromML],
      ['🔗 Con link ML', withLink],
      avgDelta !== null ? [`📈 Δ promedio`, `${avgDelta>0?'+':''}${avgDelta}%`] : null,
    ].filter(Boolean).map(([l,v]) => `
      <div style="flex:1;padding:10px 14px;border-right:1px solid var(--border);text-align:center">
        <div style="font-size:10px;color:var(--muted)">${l}</div>
        <div style="font-size:16px;font-weight:700;margin-top:2px">${v}</div>
      </div>`).join('');

    plFilter();

  } catch(e) {
    document.getElementById('pl-tbody').innerHTML =
      `<tr><td colspan="9" style="padding:20px;text-align:center;color:#f44336">⚠ ${e.message}</td></tr>`;
  }
}

function plFilter() {
  const q    = (document.getElementById('pl-search').value || '').toLowerCase();
  const cat  = document.getElementById('pl-cat').value;
  const sort = document.getElementById('pl-sort').value;

  let rows = _plData.filter(m =>
    (!q || m.material.toLowerCase().includes(q) || (m.ml_query||'').toLowerCase().includes(q)) &&
    (!cat || m.category === cat)
  );

  rows = rows.sort((a,b) => {
    if (sort === 'name')       return a.material.localeCompare(b.material);
    if (sort === 'ars_desc')   return b.price_ars - a.price_ars;
    if (sort === 'ars_asc')    return a.price_ars - b.price_ars;
    if (sort === 'delta_desc') return (b.delta_pct??-999) - (a.delta_pct??-999);
    if (sort === 'updated')    return (b.updated_at||'').localeCompare(a.updated_at||'');
    // default: category + material
    return (a.category+a.material).localeCompare(b.category+b.material);
  });

  const fmt   = n => Math.round(n).toLocaleString('es-AR');
  const fmtU  = n => n ? '$'+n.toFixed(2) : '—';
  const tbody = document.getElementById('pl-tbody');

  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="9" style="padding:20px;text-align:center;color:var(--muted)">Sin resultados</td></tr>';
    document.getElementById('pl-count').textContent = '0 materiales';
    return;
  }

  let lastCat = null;
  tbody.innerHTML = rows.map(m => {
    const catHeader = (sort === 'category' && m.category !== lastCat)
      ? (() => { lastCat = m.category; return `<tr style="background:var(--bg)"><td colspan="9" style="padding:5px 12px;font-size:10px;font-weight:700;color:var(--gold);text-transform:uppercase;letter-spacing:.5px">${CAT_LABELS[m.category]||m.category}</td></tr>`; })()
      : '';

    const delta = m.delta_pct !== null
      ? `<span style="color:${m.delta_pct>0?'#f44336':m.delta_pct<0?'var(--green)':'var(--muted)';font-weight:600}">${m.delta_pct>0?'+':''}${m.delta_pct}%</span>`
      : '<span style="color:var(--muted)">—</span>';

    const srcBadge = m.source === 'mercadolibre'
      ? '<span style="background:rgba(201,168,76,.15);color:#c9a84c;padding:2px 6px;border-radius:4px;font-size:9px">ML</span>'
      : '<span style="color:var(--muted);font-size:10px">manual</span>';

    const mlBtn = m.ml_url
      ? `<a href="${m.ml_url}" target="_blank" rel="noopener"
           style="display:inline-block;background:var(--bg);border:1px solid var(--border);color:var(--gold);padding:2px 8px;border-radius:4px;font-size:10px;text-decoration:none;white-space:nowrap"
           title="${m.ml_query||''}">🔍 Ver</a>`
      : `<a href="https://listado.mercadolibre.com.ar/${encodeURIComponent((m.material||'').replace(/ /g,'-'))}_NoIndex_True" target="_blank" rel="noopener"
           style="display:inline-block;background:var(--bg);border:1px solid #444;color:#666;padding:2px 8px;border-radius:4px;font-size:10px;text-decoration:none;white-space:nowrap"
           title="Buscar por nombre">🔍 ML</a>`;

    return catHeader + `<tr style="border-bottom:1px solid var(--border)" onmouseover="this.style.background='rgba(255,255,255,.03)'" onmouseout="this.style.background=''">
      <td style="padding:7px 12px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${m.material}">${m.material}</td>
      <td style="padding:7px 10px;text-align:right;color:var(--muted);font-size:11px">${m.unit}</td>
      <td style="padding:7px 10px;text-align:right;font-weight:600">$${fmt(m.price_ars)}</td>
      <td style="padding:7px 10px;text-align:right;color:var(--gold)">${fmtU(m.price_usd)}</td>
      <td style="padding:7px 10px;text-align:right;color:var(--muted)">${m.price_usd_prev ? fmtU(m.price_usd_prev) : '—'}</td>
      <td style="padding:7px 10px;text-align:right">${delta}</td>
      <td style="padding:7px 10px;text-align:center">${srcBadge}</td>
      <td style="padding:7px 10px;text-align:center;color:var(--muted);font-size:10px">${m.updated_at||'—'}</td>
      <td style="padding:7px 10px;text-align:center">${mlBtn}</td>
    </tr>`;
  }).join('');

  document.getElementById('pl-count').textContent = `${rows.length} de ${_plData.length} materiales`;
}

function closePriceListModal() {
  document.getElementById('price-list-modal').style.display = 'none';
}

// ── ACTUALIZACIÓN ML ──────────────────────────────────────────
function openMlModal() {
  document.getElementById('ml-modal').style.display='flex';
  const lbl = document.getElementById('ml-token-label');
  lbl.innerHTML = '🔍 Fuente de precios: <strong>Easy.com.ar → Sodimac → MercadoLibre</strong> (automático)';
}
function closeMlModal() { document.getElementById('ml-modal').style.display='none'; }

async function fetchMlToken() {
  try {
    const r = await fetch('api/ml_token.php');
    const j = await r.json();
    if (j.token) return j.token;
    throw new Error(j.error || 'sin token');
  } catch(e) {
    throw new Error('No se pudo obtener token ML: ' + e.message);
  }
}

async function runMlUpdate() {
  const btn     = document.getElementById('ml-run-btn');
  const logEl   = document.getElementById('ml-log');
  const spinner = document.getElementById('ml-spinner');
  const city    = document.getElementById('ml-city').value;
  const statusEl = document.getElementById('ml-token-label');

  btn.disabled = true; btn.textContent = '⏳ Buscando precios…';
  statusEl.innerHTML = '🔍 Consultando Easy.com.ar / Sodimac / MercadoLibre…';
  spinner.style.display = 'block';

  const results = [];
  let ok = 0, errors = 0, skipped = 0, withFlete = 0;

  // Tabla de progreso
  logEl.innerHTML = `
    <div id="ml-progress" style="color:var(--muted);margin-bottom:8px">Preparando…</div>
    <table style="width:100%;border-collapse:collapse;font-size:11px">
      <thead><tr style="color:var(--muted)">
        <th style="text-align:left;padding:2px 5px">Material</th>
        <th style="text-align:right;padding:2px 5px">Anterior</th>
        <th style="text-align:right;padding:2px 5px">Nuevo</th>
        <th style="text-align:right;padding:2px 5px">Flete</th>
        <th style="text-align:right;padding:2px 5px">Δ%</th>
      </tr></thead>
      <tbody id="ml-rows"></tbody>
    </table>`;

  const tbody = document.getElementById('ml-rows');
  const prog  = document.getElementById('ml-progress');

  for (let i = 0; i < MATERIALS.length; i++) {
    const mat   = MATERIALS[i];
    const query = findMlQuery(mat.name);
    prog.textContent = `[${i+1}/${MATERIALS.length}] ${mat.name}`;

    if (!query) {
      skipped++;
      tbody.insertAdjacentHTML('beforeend',
        `<tr style="opacity:.4"><td colspan="5" style="padding:2px 5px;color:var(--muted)">— ${mat.name} (sin mapeo)</td></tr>`);
      continue;
    }

    try {
      const items  = await mlSearch(query);
      const prices = items.map(x=>x.price).filter(p=>p>0);
      if (!prices.length) throw new Error('empty');

      prices.sort((a,b)=>a-b);
      const n = prices.length;
      const median = n%2===0 ? (prices[n/2-1]+prices[n/2])/2 : prices[Math.floor(n/2)];

      // Flete promedio según vendedores
      const fletes = items.map(x=>calcFlete(x.seller_address?.state?.id||'', city));
      const avgFlete = fletes.reduce((a,b)=>a+b,0)/fletes.length;
      const newArs = Math.round(median * (1 + avgFlete/100));
      const chg    = mat.old_ars>0 ? ((newArs-mat.old_ars)/mat.old_ars*100).toFixed(1) : 0;
      const chgColor = chg>0?'#f44336':chg<0?'var(--green)':'var(--muted)';

      if (avgFlete > 0) withFlete++;
      ok++;
      results.push({ id:mat.id, material:mat.name, price_ars:median, flete_pct:avgFlete, query });

      tbody.insertAdjacentHTML('beforeend', `
        <tr style="border-top:1px solid var(--border)">
          <td style="padding:2px 5px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${mat.name}">${mat.name}</td>
          <td style="text-align:right;padding:2px 5px;color:var(--muted)">$${Math.round(mat.old_ars).toLocaleString('es-AR')}</td>
          <td style="text-align:right;padding:2px 5px;font-weight:600">$${newArs.toLocaleString('es-AR')}</td>
          <td style="text-align:right;padding:2px 5px;color:${avgFlete>0?'#ffc107':'var(--muted)'}">${avgFlete>0?'+'+avgFlete.toFixed(1)+'%':'—'}</td>
          <td style="text-align:right;padding:2px 5px;font-weight:600;color:${chgColor}">${chg>0?'+':''}${chg}%</td>
        </tr>`);
    } catch(e) {
      errors++;
      tbody.insertAdjacentHTML('beforeend',
        `<tr style="border-top:1px solid var(--border)"><td colspan="5" style="padding:2px 5px;color:#f44336">✗ ${mat.name} — ${e.message}</td></tr>`);
    }

    // Pausa 250ms entre requests para no saturar ML
    await new Promise(r=>setTimeout(r,250));
  }

  spinner.style.display = 'none';
  prog.innerHTML = `<strong style="color:var(--green)">✓ Búsqueda completada</strong> · ${ok} encontrados · ${withFlete} con flete · ${errors} errores · ${skipped} sin mapeo`;

  if (results.length === 0) {
    btn.disabled = false; btn.textContent = '▶ Ejecutar actualización'; return;
  }

  // Guardar en servidor
  btn.textContent = '💾 Guardando en BD…';
  try {
    const sr = await fetch('api/save_material_prices.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ prices: results, usd_rate: USD_RATE })
    });
    const sj = await sr.json();
    if (sj.success) {
      prog.innerHTML += ` · <span style="color:var(--green)">✓ ${sj.saved} guardados · ${sj.zones_updated} zonas actualizadas</span>`;
      setTimeout(()=>location.reload(), 2500);
    } else {
      prog.innerHTML += ` · <span style="color:#f44336">⚠ Error al guardar: ${sj.error}</span>`;
    }
  } catch(e) {
    prog.innerHTML += ` · <span style="color:#f44336">⚠ Error al guardar: ${e.message}</span>`;
  }

  btn.disabled = false; btn.textContent = '▶ Ejecutar actualización';
}

// ── SAVE COSTS (handler PHP inline) ──────────────────────────
// El POST action=save_costs lo maneja el mismo admin_bim.php arriba

// ── INIT MAP ─────────────────────────────────────────────────
function setMapHeight() {
  const topbar = document.querySelector('.topbar');
  const h = window.innerHeight - (topbar ? topbar.offsetHeight : 60);
  document.getElementById('map').style.height = Math.max(h, 300) + 'px';
}

document.addEventListener('DOMContentLoaded', ()=>{
  // 1. Fijar altura ANTES de crear el mapa — requisito de Leaflet
  setMapHeight();
  window.addEventListener('resize', () => { setMapHeight(); if (map) map.invalidateSize(); });

  // 2. Crear mapa
  map = L.map('map', {zoomControl:true, attributionControl:false}).setView([-32, -63], 5);

  // 3. Capa de tiles — CARTO oscuro, fallback a OSM si falla
  const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '© OpenStreetMap'
  });
  const cartoLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 19, attribution: '© CARTO'
  });
  cartoLayer.addTo(map);
  let cartoFailed = false;
  cartoLayer.on('tileerror', () => {
    if (!cartoFailed) { cartoFailed = true; osmLayer.addTo(map); map.removeLayer(cartoLayer); }
  });

  L.control.attribution({position:'bottomleft'}).addTo(map);

  // 4. Renderizar marcadores
  refreshMap();

  // 5. Segundo invalidateSize por si el layout tardó (flex/reflow)
  setTimeout(() => map.invalidateSize(), 250);
});
</script>
</body>
</html>
