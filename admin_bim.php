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
.main{display:grid;grid-template-columns:1fr 360px;flex:1;min-height:0;height:calc(100vh - 54px)}
#map{width:100%;height:100%;z-index:0}

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
       href="javascript:void(s=document.createElement('script'),s.src='https://anperprimo.com/tasador/ml_materials_extractor.js?v='+Date.now(),document.head.appendChild(s))"
       style="text-decoration:none"
       title="⭐ BOOKMARKLET — Arrastrá a favoritos → andá a mercadolibre.com.ar → clic → extrae precios automáticamente">
      <button class="btn" style="cursor:grab;border-color:#c9a84c;color:#c9a84c">
        🏗 Materiales ML ⭐
      </button>
    </a>
    <button class="btn" onclick="openMlModal()" title="Actualizar precios por búsqueda de productos (servidor — experimental)">🔍 Precios srv</button>
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
async function openIccModal() {
  const modal  = document.getElementById('icc-modal');
  const body   = document.getElementById('icc-body');
  const footer = document.getElementById('icc-footer');
  modal.style.display = 'flex';
  footer.style.display = 'none';
  body.innerHTML = '<div style="color:var(--muted);font-size:13px">⏳ Consultando INDEC (datos.gob.ar)…</div>';

  try {
    const r = await fetch('api/indec_icc.php?preview=1');
    const j = await r.json();

    if (!j.success) {
      body.innerHTML = `<div style="color:#f44336">⚠ ${j.error || 'Error al conectar con INDEC'}</div>
        <div style="color:var(--muted);font-size:11px;margin-top:8px">${j.tip || ''}</div>`;
      return;
    }

    if (j.action === 'baseline_set') {
      body.innerHTML = `
        <div style="color:var(--green);font-weight:600;margin-bottom:10px">✓ ICC base registrado</div>
        <div style="font-size:12px;color:var(--muted)">ICC Materiales INDEC: <strong style="color:var(--text)">${j.icc.value}</strong> (${j.icc.date})</div>
        <div style="font-size:11px;color:var(--muted);margin-top:8px">Los precios actuales quedan como referencia. La próxima vez que abras este panel, se calculará el ajuste desde este punto.</div>`;
      return;
    }

    const sign  = j.ratio_pct >= 0 ? '+' : '';
    const color = j.ratio_pct > 0 ? '#f44336' : j.ratio_pct < 0 ? 'var(--green)' : 'var(--muted)';

    body.innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div style="background:var(--bg);border-radius:8px;padding:12px">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">ICC Base</div>
          <div style="font-size:20px;font-weight:700;margin-top:4px">${j.icc_base.value}</div>
          <div style="font-size:10px;color:var(--muted)">${j.icc_base.date || 'fecha anterior'}</div>
        </div>
        <div style="background:var(--bg);border-radius:8px;padding:12px">
          <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">ICC Actual</div>
          <div style="font-size:20px;font-weight:700;margin-top:4px">${j.icc_current.value}</div>
          <div style="font-size:10px;color:var(--muted)">${j.icc_current.date}</div>
        </div>
      </div>
      <div style="background:var(--bg);border-radius:8px;padding:14px;text-align:center;margin-bottom:14px">
        <div style="font-size:11px;color:var(--muted)">Ajuste a aplicar a todos los materiales</div>
        <div style="font-size:32px;font-weight:800;color:${color};margin:6px 0">${sign}${j.ratio_pct}%</div>
        <div style="font-size:11px;color:var(--muted)">× ${j.ratio.toFixed(4)} · ${j.materials_count} materiales</div>
      </div>
      ${j.preview.length ? `
      <div style="font-size:10px;color:var(--muted);margin-bottom:6px">Vista previa (primeros 10):</div>
      <div style="max-height:140px;overflow-y:auto;font-size:11px;font-family:monospace">
        ${j.preview.map(p=>`<div style="display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid var(--border)">
          <span style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.name}</span>
          <span style="color:var(--muted)">$${p.old_ars.toLocaleString('es-AR')}</span>
          <span style="font-weight:600">$${p.new_ars.toLocaleString('es-AR')}</span>
          <span style="color:${p.delta_pct>0?'#f44336':'var(--green)'}">${p.delta_pct>0?'+':''}${p.delta_pct}%</span>
        </div>`).join('')}
      </div>` : ''}`;

    footer.style.display = 'flex';

  } catch(e) {
    body.innerHTML = `<div style="color:#f44336">⚠ Error: ${e.message}</div>`;
  }
}

function closeIccModal() {
  document.getElementById('icc-modal').style.display = 'none';
}

async function applyIcc() {
  const btn  = document.getElementById('icc-apply-btn');
  const body = document.getElementById('icc-body');
  btn.disabled = true; btn.textContent = '⏳ Aplicando…';

  try {
    const r = await fetch('api/indec_icc.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({preview: false})
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
document.addEventListener('DOMContentLoaded', ()=>{
  map = L.map('map', {zoomControl:true, attributionControl:false}).setView([-32, -63], 5);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 19, opacity: 1,
  }).addTo(map);

  L.control.attribution({position:'bottomleft'}).addTo(map);
  map.attributionControl.addAttribution('© <a href="https://www.openstreetmap.org/copyright" style="color:#666">OSM</a> · <a href="https://carto.com" style="color:#666">CARTO</a>');

  refreshMap();
});
</script>
</body>
</html>
