<!DOCTYPE html>
<?php
// tasador/wp_import.php
// Importador web de WordPress XML — sin SSH, desde el browser
// 1. Subir este archivo a: tasador/wp_import.php
// 2. Abrir: https://anperprimo.com/tasador/wp_import.php
// 3. Subir el XML y hacer clic en Importar
// BORRAR este archivo después de importar

define('IMPORT_PASS', 'anper2025');   // ← misma que admin.php
define('BATCH',       200);
define('MAX_EXEC',    300);           // segundos — CloudPanel permite hasta 2min

@ini_set('max_execution_time', MAX_EXEC);
@ini_set('memory_limit', '512M');
@set_time_limit(MAX_EXEC);

session_start();
$cfg = require __DIR__ . '/config/settings.php';
$logged = ($_SESSION['wp_import_auth'] ?? false);

// ── Auth ─────────────────────────────────────────────────────────────────────
if (isset($_POST['pass'])) {
    if ($_POST['pass'] === IMPORT_PASS) {
        $_SESSION['wp_import_auth'] = true;
        $logged = true;
    } else {
        $authError = 'Contraseña incorrecta';
    }
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: wp_import.php'); exit; }

// ── Funciones de conversión ──────────────────────────────────────────────────
function cleanPrice(string $val): float {
    $val = preg_replace('/[^\d.,]/', '', $val);
    if (empty($val)) return 0.0;
    if (str_contains($val, '.') && str_contains($val, ',')) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    } elseif (str_contains($val, '.')) {
        $parts = explode('.', $val);
        if (strlen(end($parts)) === 3) $val = str_replace('.', '', $val);
    } elseif (str_contains($val, ',')) {
        $val = str_replace(',', '.', $val);
    }
    return (float)$val;
}

function detectCurrency(string $rawPrice, string $fCur): string {
    $cur = strtoupper(str_replace('U$D', 'USD', $fCur));
    if (str_contains($cur, 'USD')) return 'USD';
    if (str_contains($cur, 'ARS')) return 'ARS';
    $low = strtolower($rawPrice);
    if (str_contains($low, 'usd') || str_contains($low, 'u$d') || str_contains($low, 'u$s')) return 'USD';
    if (str_contains($rawPrice, '$') && !str_contains($low, 'usd')) return 'ARS';
    return 'USD';
}

function cleanSurface(string $raw): ?float {
    if (empty($raw) || trim($raw) === '-' || trim($raw) === '') return null;
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*m[²2]?/i', $raw, $m)) return cleanPrice($m[1]);
    if (preg_match('/(\d+(?:[.,]\d+)?)/', $raw, $m)) {
        $n = cleanPrice($m[1]); return $n > 0 ? $n : null;
    }
    return null;
}

function getMeta(\SimpleXMLElement $item, string $key, array $ns): string {
    $wp = $item->children($ns['wp']);
    foreach ($wp->postmeta as $m) {
        if ((string)$m->meta_key === $key) return trim((string)$m->meta_value);
    }
    return '';
}

function getTax(\SimpleXMLElement $item, string $domain): string {
    foreach ($item->category as $c) {
        if ((string)$c['domain'] === $domain) return trim((string)$c);
    }
    return '';
}

function mapType(string $t): string {
    $t = strtolower($t);
    if (str_contains($t,'depto')||str_contains($t,'departa')||str_contains($t,'monoamb')) return 'departamento';
    if (str_contains($t,'casa')) return 'casa';
    if (str_contains($t,'terreno')||str_contains($t,'lote')) return 'terreno';
    if (str_contains($t,'local')||str_contains($t,'comercial')) return 'local-comercial';
    if (str_contains($t,'oficina')) return 'oficina';
    if (str_contains($t,'cochera')||str_contains($t,'garage')) return 'cochera';
    if (str_contains($t,'galp')) return 'galpon';
    if (str_contains($t,'ph')) return 'ph';
    return 'departamento';
}

function mapOp(string $s): string {
    return (str_contains(strtolower($s),'alquil')||str_contains(strtolower($s),'rent')) ? 'alquiler' : 'venta';
}

// ── AJAX: procesar lote ──────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'process_batch' && $logged) {
    header('Content-Type: application/json');

    $tmpFile  = $_SESSION['import_tmp'] ?? '';
    $offset   = (int)($_POST['offset'] ?? 0);
    $arsRate  = (float)($cfg['ars_usd_rate'] ?? 1450);
    $onlyArg  = true;

    if (!$tmpFile || !file_exists($tmpFile)) {
        echo json_encode(['error' => 'Archivo temporal no encontrado. Volver a subir el XML.']);
        exit;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
            $cfg['db']['user'], $cfg['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (\Throwable $e) {
        echo json_encode(['error' => 'BD: ' . $e->getMessage()]); exit;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($tmpFile);
    if (!$xml) { echo json_encode(['error' => 'XML inválido']); exit; }

    $NS = ['wp' => 'http://wordpress.org/export/1.2/'];
    $items = $xml->channel->item;

    // Filtrar solo propiedades de Argentina
    $properties = [];
    foreach ($items as $item) {
        $wp = $item->children($NS['wp']);
        if ((string)$wp->post_type !== 'property') continue;
        if ((string)$wp->status   !== 'publish')   continue;
        if ($onlyArg && getMeta($item, 'fave_property_country', $NS) !== 'Argentina') continue;
        $properties[] = $item;
    }

    $total  = count($properties);
    $batch  = array_slice($properties, $offset, BATCH);
    $done   = 0;
    $errors = 0;
    $noPrice= 0;

    $stmt = $pdo->prepare("
        INSERT INTO market_listings
            (source, external_id, url, title, address, city, province,
             zone, lat, lng, property_type, operation,
             covered_area, total_area, bedrooms, bathrooms, garages,
             price, currency, price_usd, price_per_m2, active, scraped_at, created_at)
        VALUES
            (:source,:external_id,:url,:title,:address,:city,:province,
             :zone,:lat,:lng,:property_type,:operation,
             :covered_area,:total_area,:bedrooms,:bathrooms,:garages,
             :price,:currency,:price_usd,:price_per_m2,1,:scraped_at,NOW())
        ON DUPLICATE KEY UPDATE
            price=VALUES(price),currency=VALUES(currency),price_usd=VALUES(price_usd),
            price_per_m2=VALUES(price_per_m2),active=1,scraped_at=VALUES(scraped_at)
    ");

    foreach ($batch as $item) {
        try {
            $wp = $item->children($NS['wp']);

            $rawPrice  = getMeta($item, 'fave_property_price', $NS);
            $rawCur    = getMeta($item, 'fave_currency', $NS) ?: getMeta($item, 'fave_currency_info', $NS);
            $currency  = detectCurrency($rawPrice, $rawCur);
            $price     = cleanPrice($rawPrice);
            if ($price <= 0) $noPrice++;
            $priceUSD  = $currency === 'ARS' ? round($price / $arsRate, 2) : $price;

            $rawSize   = getMeta($item, 'fave_property_size', $NS);
            $szPrefix  = getMeta($item, 'fave_property_size_prefix', $NS);
            $covArea   = cleanSurface($rawSize);
            $rawLand   = getMeta($item, 'fave_property_land', $NS);
            $landArea  = cleanSurface($rawLand);
            if ($covArea && str_contains(strtolower($szPrefix), 'sq')) $covArea = round($covArea * 0.0929, 1);
            if ($landArea && str_contains(strtolower($szPrefix), 'sq')) $landArea = round($landArea * 0.0929, 1);
            $totalArea = $landArea ?: $covArea;

            $ppm2 = ($covArea && $priceUSD > 0) ? round($priceUSD / $covArea, 2) : null;
            if ($ppm2 && ($ppm2 < 100 || $ppm2 > 20000)) $ppm2 = null;

            $lat = getMeta($item, 'houzez_geolocation_lat', $NS);
            $lng = getMeta($item, 'houzez_geolocation_long', $NS);
            if (!$lat) {
                $loc = getMeta($item, 'fave_property_location', $NS);
                if ($loc && str_contains($loc, ',')) {
                    [$lat, $rest] = explode(',', $loc, 2);
                    $lng = explode(',', $rest)[0];
                }
            }
            $lat = $lat ? (float)$lat : null;
            $lng = $lng ? (float)$lng : null;
            if ($lat && ($lat < -55 || $lat > -21)) { $lat = null; $lng = null; }

            $statusT = getTax($item, 'property_status');
            $typeT   = getTax($item, 'property_type');
            $cityT   = getTax($item, 'property_city');
            $addr    = getMeta($item,'fave_property_map_address',$NS) ?: getMeta($item,'fave_property_address',$NS) ?: (string)$item->title;
            $beds    = getMeta($item,'fave_property_bedrooms',$NS);
            $baths   = getMeta($item,'fave_property_bathrooms',$NS);
            $garages = getMeta($item,'fave_property_garage',$NS);
            $postId  = (int)(string)$wp->post_id;
            $pubDate = (string)$item->pubDate;

            $stmt->execute([
                ':source'       => 'wp_litoral',
                ':external_id'  => 'wp_litoral_' . $postId,
                ':url'          => (string)$item->link,
                ':title'        => (string)$item->title,
                ':address'      => $addr,
                ':city'         => $cityT ?: 'Santa Fe',
                ':province'     => 'Santa Fe',
        ':zone'         => $cityT ?: 'Santa Fe',
                ':lat'          => $lat,
                ':lng'          => $lng,
                ':property_type'=> mapType($typeT ?: 'departamento'),
                ':operation'    => mapOp($statusT),
                ':covered_area' => $covArea,
                ':total_area'   => $totalArea,
                ':bedrooms'     => $beds !== '' ? (int)$beds : null,
                ':bathrooms'    => $baths !== '' ? (int)$baths : null,
                ':garages'      => $garages !== '' ? (int)$garages : null,
                ':price'        => $price ?: null,
                ':currency'     => $currency,
                ':price_usd'    => $priceUSD ?: null,
                ':price_per_m2' => $ppm2,
                ':scraped_at'   => date('Y-m-d H:i:s', strtotime($pubDate) ?: time()),
            ]);
            $done++;
        } catch (\Throwable $e) {
            $errors++;
        }
    }

    $nextOffset = $offset + BATCH;
    $finished   = $nextOffset >= $total;

    // Stats finales
    $stats = [];
    if ($finished) {
        $rows = $pdo->query("SELECT property_type, COUNT(*) as c, ROUND(AVG(price_per_m2),0) as avg FROM market_listings WHERE source='wp_litoral' AND active=1 GROUP BY property_type ORDER BY c DESC")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $r) $stats[] = "{$r['property_type']}: {$r['c']} props" . ($r['avg'] ? " · USD {$r['avg']}/m²" : '');
        // Limpiar tmp
        @unlink($tmpFile);
        unset($_SESSION['import_tmp']);
    }

    echo json_encode([
        'done'        => $done,
        'errors'      => $errors,
        'no_price'    => $noPrice,
        'total'       => $total,
        'offset'      => $offset,
        'next_offset' => $nextOffset,
        'finished'    => $finished,
        'progress'    => min(100, round($nextOffset / max(1,$total) * 100)),
        'stats'       => $stats,
    ]);
    exit;
}

// ── Upload XML ──────────────────────────────────────────────────────────────
$uploadMsg = '';
if (isset($_FILES['xml_file']) && $logged) {
    $f = $_FILES['xml_file'];
    if ($f['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xml'])) {
            $uploadMsg = 'error:Solo se aceptan archivos .xml';
        } else {
            $tmp = sys_get_temp_dir() . '/wp_import_' . session_id() . '.xml';
            if (move_uploaded_file($f['tmp_name'], $tmp)) {
                $_SESSION['import_tmp'] = $tmp;
                // Contar propiedades de Argentina para preview
                libxml_use_internal_errors(true);
                $x = simplexml_load_file($tmp);
                $count = 0;
                if ($x) {
                    $ns = ['wp' => 'http://wordpress.org/export/1.2/'];
                    foreach ($x->channel->item as $it) {
                        $wp = $it->children('http://wordpress.org/export/1.2/');
                        if ((string)$wp->post_type !== 'property') continue;
                        if ((string)$wp->status   !== 'publish')   continue;
                        foreach ($it->children('http://wordpress.org/export/1.2/')->postmeta as $m) {
                            if ((string)$m->meta_key === 'fave_property_country' && (string)$m->meta_value === 'Argentina') { $count++; break; }
                        }
                    }
                }
                $uploadMsg = "ok:$count";
            } else {
                $uploadMsg = 'error:No se pudo guardar el archivo temporal';
            }
        }
    } else {
        $msg = ['1'=>'Archivo muy grande','2'=>'Archivo parcial','3'=>'Sin archivo','4'=>'Sin carpeta tmp','6'=>'Disco lleno'];
        $uploadMsg = 'error:Upload error: ' . ($msg[$f['error']] ?? $f['error']);
    }
}

$hasTmp    = !empty($_SESSION['import_tmp']) && file_exists($_SESSION['import_tmp'] ?? '');
$tmpCount  = 0;
if (isset($uploadMsg) && str_starts_with($uploadMsg, 'ok:')) {
    $tmpCount = (int)explode(':', $uploadMsg)[1];
}
?>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TasadorIA — Importar WordPress XML</title>
<style>
:root{--bg:#0d0f14;--card:#1e2235;--border:#2a2f45;--gold:#c9a84c;--text:#e8e8f0;--muted:#7a7a9a;--green:#00c896;--red:#ff4f6e}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:32px;max-width:620px;width:100%}
h1{color:var(--gold);font-size:22px;margin-bottom:4px}
p.sub{font-size:13px;color:var(--muted);margin-bottom:24px}
label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:6px}
input[type=password],input[type=file]{width:100%;padding:11px 14px;background:#1c2030;border:1.5px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;outline:none;margin-bottom:14px}
input:focus{border-color:var(--gold)}
.btn{width:100%;padding:13px;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;background:var(--gold);color:#0d0f14;margin-top:4px}
.btn:hover{background:#f0cc7a}
.btn:disabled{opacity:.5;cursor:not-allowed}
.msg{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:16px}
.ok{background:rgba(0,200,150,.1);border:1px solid rgba(0,200,150,.4);color:var(--green)}
.err{background:rgba(255,79,110,.1);border:1px solid rgba(255,79,110,.4);color:var(--red)}
.warn{background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.3);color:var(--gold)}

/* Progress */
.progress-wrap{margin:20px 0;display:none}
.progress-bar{height:8px;background:#1c2030;border-radius:4px;overflow:hidden;margin:8px 0}
.progress-fill{height:100%;background:var(--gold);border-radius:4px;transition:width .4s;width:0%}
.progress-txt{font-size:13px;color:var(--muted)}
.log{background:#0d0f14;border:1px solid var(--border);border-radius:8px;padding:12px;font-size:12px;font-family:monospace;color:#aaa;max-height:200px;overflow-y:auto;margin-top:12px;display:none}
.log p{margin:2px 0}
.log .ok-line{color:var(--green)}
.log .err-line{color:var(--red)}

/* Upload zone */
.drop{border:2px dashed var(--border);border-radius:10px;padding:24px;text-align:center;cursor:pointer;position:relative;margin-bottom:14px;transition:all .2s}
.drop:hover,.drop.drag{border-color:var(--gold);background:rgba(201,168,76,.05)}
.drop input{position:absolute;inset:0;opacity:0;cursor:pointer}
.drop-icon{font-size:32px;margin-bottom:8px}
.drop-txt{font-size:13px;color:var(--muted)}
.file-chosen{font-size:12px;color:var(--gold);margin-top:6px}

.step{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px}
.step:last-child{border:none}
.step-num{width:24px;height:24px;border-radius:50%;background:var(--gold);color:#0d0f14;font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
a.logout{float:right;font-size:12px;color:var(--muted);text-decoration:none}
a.logout:hover{color:var(--gold)}
</style>
</head>
<body>
<div class="box">

<?php if (!$logged): ?>
<!-- LOGIN -->
<h1>🏠 TasadorIA</h1>
<p class="sub">Importador de WordPress XML — Houzez / Litoralpropiedades</p>
<?php if (isset($authError)): ?><div class="msg err"><?= htmlspecialchars($authError) ?></div><?php endif; ?>
<form method="POST">
  <label>Contraseña de admin</label>
  <input type="password" name="pass" autofocus placeholder="••••••••">
  <button type="submit" class="btn">Ingresar</button>
</form>

<?php else: ?>
<!-- PANEL IMPORT -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
  <h1>📥 Importar WordPress XML</h1>
  <a href="?logout=1" class="logout">Salir</a>
</div>
<p class="sub">Convierte el export de Houzez a datos de mercado del tasador.</p>

<!-- Pasos -->
<div style="margin-bottom:20px">
  <div class="step"><div class="step-num">1</div><span>Subí el archivo XML de WordPress (<strong>litoralpropiedades_WordPress_*.xml</strong>)</span></div>
  <div class="step"><div class="step-num">2</div><span>Hacé clic en <strong>Importar</strong> — procesa todo automáticamente</span></div>
  <div class="step"><div class="step-num">3</div><span>El motor del tasador usa los datos como referencia de precios reales</span></div>
</div>

<?php if (isset($uploadMsg)): ?>
  <?php if (str_starts_with($uploadMsg, 'ok:')): ?>
    <div class="msg ok">✓ XML cargado — <strong><?= $tmpCount ?> propiedades de Argentina</strong> listas para importar</div>
  <?php else: ?>
    <div class="msg err">✗ <?= htmlspecialchars(substr($uploadMsg, 6)) ?></div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($hasTmp && !isset($uploadMsg)): ?>
  <div class="msg warn">📂 Hay un archivo XML cargado listo. Hacé clic en Importar o subí otro.</div>
<?php endif; ?>

<!-- Upload -->
<form method="POST" enctype="multipart/form-data" id="upload-form">
  <label>Archivo XML de WordPress</label>
  <div class="drop" id="drop-zone">
    <input type="file" name="xml_file" accept=".xml" onchange="showFile(this)" id="file-input">
    <div class="drop-icon">📂</div>
    <div class="drop-txt">Arrastrá el XML acá o hacé clic para seleccionar</div>
    <div class="file-chosen" id="file-chosen"></div>
  </div>
  <button type="submit" class="btn" style="background:#2a2f45;color:var(--text);margin-bottom:12px">📤 Subir XML</button>
</form>

<!-- Importar -->
<?php if ($hasTmp || $tmpCount > 0): ?>
<button class="btn" id="btn-import" onclick="startImport()">✦ Importar <?= $tmpCount ?: '...' ?> propiedades</button>
<?php else: ?>
<button class="btn" id="btn-import" onclick="startImport()" disabled>✦ Importar — subí el XML primero</button>
<?php endif; ?>

<!-- Progress -->
<div class="progress-wrap" id="progress-wrap">
  <div class="progress-txt" id="progress-txt">Iniciando...</div>
  <div class="progress-bar"><div class="progress-fill" id="progress-fill"></div></div>
  <div class="log" id="log"></div>
</div>

<!-- Links -->
<div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap">
  <a href="admin_market.php" style="font-size:12px;color:var(--muted);text-decoration:none">📊 Ver estadísticas</a>
  <a href="admin.php" style="font-size:12px;color:var(--muted);text-decoration:none">⚙️ Panel admin</a>
  <a href="api/import_market.php?key=<?= IMPORT_PASS ?>" target="_blank" style="font-size:12px;color:var(--muted);text-decoration:none">🔍 Verificar BD</a>
</div>

<div style="margin-top:16px;padding:10px 14px;background:rgba(255,79,110,.07);border:1px solid rgba(255,79,110,.2);border-radius:8px;font-size:11px;color:var(--muted)">
  ⚠️ Borrar este archivo del servidor después de usarlo:<br>
  <code>public_html/tasador/wp_import.php</code>
</div>

<script>
function showFile(input) {
  const name = input.files[0]?.name || '';
  document.getElementById('file-chosen').textContent = name ? '✓ ' + name : '';
}

// Drag & drop visual
const dz = document.getElementById('drop-zone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag'); });
dz.addEventListener('dragleave', () => dz.classList.remove('drag'));
dz.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('drag'); showFile(e.dataTransfer.files[0] ? {files: e.dataTransfer.files} : {}); });

// Importar en lotes via AJAX
let totalImported = 0, totalErrors = 0;

async function startImport() {
  const btn = document.getElementById('btn-import');
  btn.disabled = true;
  btn.textContent = 'Importando...';

  document.getElementById('progress-wrap').style.display = 'block';
  document.getElementById('log').style.display = 'block';
  totalImported = 0; totalErrors = 0;

  await processBatch(0);
}

async function processBatch(offset) {
  const fd = new FormData();
  fd.append('action', 'process_batch');
  fd.append('offset', offset);

  try {
    const res  = await fetch('wp_import.php', { method: 'POST', body: fd });
    const text = await res.text();
    const start = text.indexOf('{');
    const data = start >= 0 ? JSON.parse(text.slice(start)) : (() => { throw new Error(text.slice(0,200)); })();

    if (data.error) {
      addLog('✗ Error: ' + data.error, 'err');
      document.getElementById('btn-import').textContent = 'Error — ver log';
      return;
    }

    totalImported += data.done;
    totalErrors   += data.errors;

    // Update progress
    const pct = data.progress;
    document.getElementById('progress-fill').style.width = pct + '%';
    document.getElementById('progress-txt').textContent =
      `Procesando... ${Math.min(data.next_offset, data.total)} de ${data.total} (${pct}%)`;

    addLog(`Lote ${offset}–${data.next_offset}: ${data.done} importadas, ${data.errors} errores`, data.errors > 0 ? 'err' : 'ok');

    if (!data.finished) {
      // Siguiente lote con pequeña pausa para no sobrecargar
      await new Promise(r => setTimeout(r, 200));
      await processBatch(data.next_offset);
    } else {
      // Terminó
      document.getElementById('progress-fill').style.width = '100%';
      document.getElementById('progress-txt').textContent =
        `✅ Importación completada: ${totalImported} propiedades, ${totalErrors} errores`;
      document.getElementById('btn-import').textContent = '✓ Importación completada';
      document.getElementById('btn-import').style.background = 'var(--green)';
      document.getElementById('btn-import').style.color = '#0d0f14';

      if (data.stats?.length) {
        addLog('─── Resultado en BD ───', 'ok');
        data.stats.forEach(s => addLog(s, 'ok'));
      }
      addLog('→ Ver estadísticas en admin_market.php', 'ok');
    }
  } catch(e) {
    addLog('✗ Error de red: ' + e.message, 'err');
  }
}

function addLog(msg, type='') {
  const log = document.getElementById('log');
  const p   = document.createElement('p');
  p.className = type === 'ok' ? 'ok-line' : type === 'err' ? 'err-line' : '';
  p.textContent = msg;
  log.appendChild(p);
  log.scrollTop = log.scrollHeight;
}
</script>
<?php endif; ?>
</div>
</body>
</html>