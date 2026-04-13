<!DOCTYPE html>
<?php
// tasador/admin.php — Panel unificado TasadorIA
// Pestañas: Dashboard · Precios/Zonas · Importar XML · Leads · Tasaciones · Config
define('ADMIN_PASS', 'anper2025');
define('IMPORT_BATCH', 150);
@ini_set('display_errors', '0');
@error_reporting(0);
session_start();
if (isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === ADMIN_PASS) { $_SESSION['ta_admin'] = true; }
    else $loginErr = 'Contraseña incorrecta';
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }
$logged = ($_SESSION['ta_admin'] ?? false);
$cfg   = require __DIR__ . '/config/settings.php';
$zones = require __DIR__ . '/config/zones.php';
// ── BD ────────────────────────────────────────────────────────────────────────
$pdo = null;
try {
    $pdo = new PDO("mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
} catch (Throwable $e) {}
// ── AJAX: importar lote XML ───────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'import_batch' && $logged) {
    ob_start();
    header('Content-Type: application/json; charset=utf-8');
    @error_reporting(0);
    $tmpFile = $_SESSION['import_tmp'] ?? '';
    $offset  = max(0, (int)($_POST['offset'] ?? 0));
    $arsRate = (float)($cfg['ars_usd_rate'] ?? 1450);
    try {
        if (!$tmpFile || !file_exists($tmpFile)) throw new Exception('Archivo no encontrado. Subí el XML de nuevo.');
        if (!$pdo) throw new Exception('BD no conecta');
        $pdo->query("SELECT 1 FROM market_listings LIMIT 1");
    } catch (Throwable $e) {
        ob_end_clean();
        echo json_encode(['error' => $e->getMessage()]); exit;
    }
    // Helpers
    function xCleanPrice(string $v): float {
        $v = preg_replace('/[^\d.,]/', '', $v);
        if (!$v) return 0;
        if (strpos($v,'.')!==false && strpos($v,',')!==false) { $v=str_replace('.','',$v); $v=str_replace(',','.',$v); }
        elseif (strpos($v,'.')!==false) { $p=explode('.',$v); if (strlen(end($p))===3) $v=str_replace('.','',$v); }
        elseif (strpos($v,',')!==false) $v=str_replace(',','.',$v);
        return (float)$v;
    }
    function xGetMeta($item, string $key): string {
        $wp=$item->children('http://wordpress.org/export/1.2/');
        foreach ($wp->postmeta as $m) if ((string)$m->meta_key===$key) return trim((string)$m->meta_value);
        return '';
    }
    function xGetTax($item, string $domain): string {
        foreach ($item->category as $c) if ((string)$c['domain']===$domain) return trim((string)$c);
        return '';
    }
    function xMapType(string $t): string {
        $t=strtolower($t);
        if (strpos($t,'depto')!==false||strpos($t,'departa')!==false||strpos($t,'monoamb')!==false) return 'departamento';
        if (strpos($t,'casa')!==false) return 'casa';
        if (strpos($t,'terreno')!==false||strpos($t,'lote')!==false) return 'terreno';
        if (strpos($t,'local')!==false||strpos($t,'comercial')!==false) return 'local-comercial';
        if (strpos($t,'oficina')!==false) return 'oficina';
        if (strpos($t,'cochera')!==false) return 'cochera';
        if (strpos($t,'galp')!==false) return 'galpon';
        return 'departamento';
    }
    libxml_use_internal_errors(true);
    $xml = @simplexml_load_file($tmpFile);
    if (!$xml) { ob_end_clean(); echo json_encode(['error' => 'XML inválido']); exit; }
    $props = [];
    foreach ($xml->channel->item as $item) {
        $wp = $item->children('http://wordpress.org/export/1.2/');
        if ((string)$wp->post_type !== 'property' || (string)$wp->status !== 'publish') continue;
        foreach ($wp->postmeta as $m)
            if ((string)$m->meta_key === 'fave_property_country' && (string)$m->meta_value === 'Argentina') { $props[] = $item; break; }
    }
    $total = count($props);
    $batch = array_slice($props, $offset, IMPORT_BATCH);
    $done = $errors = 0;
    $firstErr = null;
    $stmt = $pdo->prepare("INSERT INTO market_listings
        (source,external_id,url,title,address,city,province,zone,lat,lng,
         property_type,operation,covered_area,total_area,bedrooms,bathrooms,garages,
         price,currency,price_usd,price_per_m2,active,scraped_at,created_at)
        VALUES (:src,:eid,:url,:title,:addr,:city,:prov,:zone,:lat,:lng,
         :type,:op,:cov,:tot,:beds,:baths,:gar,
         :price,:cur,:pusd,:ppm2,1,:sdate,NOW())
        ON DUPLICATE KEY UPDATE price=VALUES(price),currency=VALUES(currency),
        price_usd=VALUES(price_usd),price_per_m2=VALUES(price_per_m2),active=1,scraped_at=VALUES(scraped_at)");
    foreach ($batch as $item) {
        try {
            $wp     = $item->children('http://wordpress.org/export/1.2/');
            $rawP   = xGetMeta($item,'fave_property_price');
            $rawC   = xGetMeta($item,'fave_currency')?:xGetMeta($item,'fave_currency_info');
            $cur    = (strpos(strtoupper(str_replace('U$D','USD',$rawC)),'ARS')!==false)?'ARS':'USD';
            $price  = xCleanPrice($rawP);
            $pUSD   = $cur==='ARS' ? round($price/$arsRate,2) : $price;
            $rawSz  = xGetMeta($item,'fave_property_size');
            $cov    = null;
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*m[²2]?/i',$rawSz,$m2)) $cov = xCleanPrice($m2[1]);
            elseif (preg_match('/(\d+(?:[.,]\d+)?)/',$rawSz,$m2)) { $n=xCleanPrice($m2[1]); if ($n>0) $cov=$n; }
            $szPfx  = xGetMeta($item,'fave_property_size_prefix');
            if ($cov && stripos($szPfx,'sq')!==false) $cov = round($cov*0.0929,1);
            $rawL   = xGetMeta($item,'fave_property_land');
            $land   = null;
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*m[²2]?/i',$rawL,$m2)) $land = xCleanPrice($m2[1]);
            $ppm2 = ($cov && $pUSD>0) ? round($pUSD/$cov,2) : null;
            if ($ppm2 && ($ppm2<100||$ppm2>20000)) $ppm2=null;
            $lat = xGetMeta($item,'houzez_geolocation_lat');
            $lng = xGetMeta($item,'houzez_geolocation_long');
            if (!$lat) { $loc=xGetMeta($item,'fave_property_location'); if ($loc && strpos($loc,',')!==false) { $p=explode(',',$loc); $lat=$p[0];$lng=$p[1]; } }
            $lat = $lat?(float)$lat:null; $lng = $lng?(float)$lng:null;
            if ($lat && ($lat<-55||$lat>-21)) { $lat=null;$lng=null; }
            $pid  = (int)(string)($wp->post_id??0);
            $pub  = (string)$item->pubDate;
            $stmt->execute([
                ':src'=>'wp_litoral', ':eid'=>'wp_litoral_'.$pid,
                ':url'=>(string)($item->link??''), ':title'=>(string)($item->title??''),
                ':addr'=>xGetMeta($item,'fave_property_map_address')?:xGetMeta($item,'fave_property_address')?:(string)$item->title,
                ':city'=>xGetTax($item,'property_city')?:'Santa Fe',
                ':prov'=>'Santa Fe', ':zone'=>xGetTax($item,'property_city')?:'Santa Fe',
                ':lat'=>$lat, ':lng'=>$lng,
                ':type'=>xMapType(xGetTax($item,'property_type')?:'departamento'),
                ':op'=>(strpos(strtolower(xGetTax($item,'property_status')),'alquil')!==false)?'alquiler':'venta',
                ':cov'=>$cov, ':tot'=>$land?:$cov,
                ':beds'=>($b=xGetMeta($item,'fave_property_bedrooms'))!==''?(int)$b:null,
                ':baths'=>($b=xGetMeta($item,'fave_property_bathrooms'))!==''?(int)$b:null,
                ':gar'=>($b=xGetMeta($item,'fave_property_garage'))!==''?(int)$b:null,
                ':price'=>$price?:null, ':cur'=>$cur, ':pusd'=>$pUSD?:null, ':ppm2'=>$ppm2,
                ':sdate'=>date('Y-m-d H:i:s',@strtotime($pub)?:time()),
            ]);
            $done++;
        } catch (Throwable $e) { $errors++; if (!$firstErr) $firstErr=$e->getMessage(); }
    }
    $nextOff  = $offset + IMPORT_BATCH;
    $finished = $nextOff >= $total;
    $stats    = [];
    if ($finished) {
        try {
            $rows = $pdo->query("SELECT property_type,COUNT(*) c,ROUND(AVG(price_per_m2),0) avg FROM market_listings WHERE source='wp_litoral' AND active=1 GROUP BY property_type ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) $stats[] = "{$r['property_type']}: {$r['c']}" . ($r['avg']?" · USD {$r['avg']}/m²":'');
        } catch (Throwable $e) {}
        if ($tmpFile && file_exists($tmpFile)) @unlink($tmpFile);
        unset($_SESSION['import_tmp']);
    }
    ob_end_clean();
    echo json_encode(['done'=>$done,'errors'=>$errors,'total'=>$total,'next_offset'=>$nextOff,
        'progress'=>min(100,(int)round($nextOff/max(1,$total)*100)),'finished'=>$finished,
        'stats'=>$stats,'first_error'=>$firstErr]);
    exit;
}
// ── Upload XML ────────────────────────────────────────────────────────────────
$uploadMsg = '';
if (isset($_FILES['xml_file']) && $logged) {
    $f = $_FILES['xml_file'];
    if ($f['error'] === UPLOAD_ERR_OK && strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)) === 'xml') {
        $tmp = sys_get_temp_dir().'/ta_import_'.session_id().'.xml';
        if (@move_uploaded_file($f['tmp_name'],$tmp)) {
            $_SESSION['import_tmp'] = $tmp;
            $cnt = 0; $fh=@fopen($tmp,'r');
            if ($fh) { $buf=''; while (!feof($fh)) { $buf.=fread($fh,65536); $cnt+=substr_count($buf,'fave_property_country'); $buf=substr($buf,-100); } fclose($fh); }
            $uploadMsg = "ok:$cnt";
        } else $uploadMsg = 'error:No se pudo guardar';
    } else $uploadMsg = 'error:Solo archivos .xml — Error '.$f['error'];
}
// ── Guardar config ────────────────────────────────────────────────────────────
$saveMsg = '';
if ($logged && isset($_POST['save_usd'])) {
    $rate = (int)($_POST['usd_rate'] ?? 1450);
    $sc = file_get_contents(__DIR__.'/config/settings.php');
    $sc = preg_replace("/'ars_usd_rate'\s*=>\s*\d+/", "'ars_usd_rate' => $rate", $sc);
    file_put_contents(__DIR__.'/config/settings.php', $sc);
    $cfg = require __DIR__.'/config/settings.php';
    $saveMsg = "✅ Tipo de cambio: $1 USD = $" . number_format($rate,0,',','.') . " ARS";
}
// ── Guardar zona ──────────────────────────────────────────────────────────────
if ($logged && isset($_POST['save_zone'])) {
    $ck=(string)$_POST['city_key']; $zk=(string)$_POST['zone_key'];
    $mn=(int)$_POST['price_min']; $av=(int)$_POST['price_avg']; $mx=(int)$_POST['price_max'];
    if (isset($zones[$ck]['zones'][$zk])) {
        $zones[$ck]['zones'][$zk]['price_m2'] = ['min'=>$mn,'avg'=>$av,'max'=>$mx];
        $out = "<?php\n// config/zones.php — Editado: ".date('Y-m-d H:i:s')."\nreturn [\n\n";
        foreach ($zones as $ckey => $city) {
            $out .= "    '$ckey' => [\n";
            $out .= "        'label'    => ".var_export($city['label'],true).",\n";
            $out .= "        'country'  => ".var_export($city['country']??'AR',true).",\n";
            $out .= "        'currency' => ".var_export($city['currency']??'USD',true).",\n";
            $out .= "        'updated'  => '".date('Y-m')."',\n";
            $out .= "        'bounds'   => ".var_export($city['bounds'],true).",\n";
            $out .= "        'zones' => [\n";
            foreach ($city['zones'] as $zkey => $z) {
                $out .= "            '$zkey' => [\n";
                $out .= "                'label'       => ".var_export($z['label'],true).",\n";
                $out .= "                'price_m2'    => ['min'=>{$z['price_m2']['min']},'max'=>{$z['price_m2']['max']},'avg'=>{$z['price_m2']['avg']}],\n";
                $out .= "                'description' => ".var_export($z['description'],true).",\n";
                $out .= "                'coords'      => ".var_export($z['coords'],true).",\n";
                $out .= "                'keywords'    => ".var_export($z['keywords'],true).",\n";
                $out .= "                'multipliers' => [],\n";
                $out .= "            ],\n";
            }
            $out .= "        ],\n    ],\n\n";
        }
        $out .= "];\n";
        file_put_contents(__DIR__.'/config/zones.php', $out);
        $zones = require __DIR__.'/config/zones.php';
        $saveMsg = "✅ Zona '".htmlspecialchars($zones[$ck]['zones'][$zk]['label'])."': min=$mn avg=$av max=$mx USD/m²";
    }
}
// ── Helpers de datos ──────────────────────────────────────────────────────────
function dbq(PDO $p, string $sql, array $b=[]): array {
    try { $s=$p->prepare($sql); $s->execute($b); return $s->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return []; }
}
function dbv(PDO $p, string $sql, array $b=[]): mixed {
    try { $s=$p->prepare($sql); $s->execute($b); return $s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}
$hasTmp = !empty($_SESSION['import_tmp']) && file_exists($_SESSION['import_tmp'] ?? '');
$uploadCount = isset($uploadMsg) && str_starts_with($uploadMsg,'ok:') ? (int)explode(':',$uploadMsg)[1] : 0;
// Stats para dashboard
$totalTasaciones  = $pdo ? (int)dbv($pdo,"SELECT COUNT(*) FROM tasaciones") : 0;
$totalLeads       = $pdo ? (int)dbv($pdo,"SELECT COUNT(*) FROM tasacion_leads") : 0;
$totalListings    = $pdo ? (int)dbv($pdo,"SELECT COUNT(*) FROM market_listings WHERE active=1") : 0;
$leadsHoy         = $pdo ? (int)dbv($pdo,"SELECT COUNT(*) FROM tasacion_leads WHERE DATE(created_at)=CURDATE()") : 0;
$recentLeads      = $pdo ? dbq($pdo,"SELECT * FROM tasacion_leads ORDER BY created_at DESC LIMIT 50") : [];
$recentTasaciones = $pdo ? dbq($pdo,"SELECT code,zone,city,created_at,name,email,phone,result_json FROM tasaciones ORDER BY created_at DESC LIMIT 30") : [];
$marketStats      = $pdo ? dbq($pdo,"SELECT property_type,COUNT(*) c,ROUND(AVG(price_per_m2),0) avg FROM market_listings WHERE active=1 GROUP BY property_type ORDER BY c DESC") : [];
$marketByZone     = $pdo ? dbq($pdo,"SELECT city,zone,COUNT(*) c,ROUND(AVG(price_per_m2),0) avg,ROUND(MIN(price_per_m2),0) mn,ROUND(MAX(price_per_m2),0) mx FROM market_listings WHERE active=1 AND price_per_m2 BETWEEN 100 AND 20000 AND property_type='departamento' GROUP BY city,zone ORDER BY c DESC") : [];
// ── Config IA y mercado (acceso seguro, compatible con settings.php viejo) ────
$aiCfgBlock       = is_array($cfg['ai'] ?? null) ? $cfg['ai'] : [];
$aiProvsCfg       = is_array($aiCfgBlock['providers'] ?? null) ? $aiCfgBlock['providers'] : [];
$activeProv       = strtolower($aiCfgBlock['provider'] ?? 'anthropic');
$aiProviders      = [
    'anthropic' => ['label'=>'Anthropic (Claude)', 'icon'=>'🟠'],
    'openai'    => ['label'=>'OpenAI (GPT-4o)',    'icon'=>'🟢'],
    'grok'      => ['label'=>'xAI (Grok)',         'icon'=>'🔵'],
    'deepseek'  => ['label'=>'DeepSeek',           'icon'=>'🔷'],
    'gemini'    => ['label'=>'Google (Gemini)',     'icon'=>'🟡'],
];
$marketCorrFactor  = (float)(($cfg['market'] ?? [])['correction_factor'] ?? 0.80);
$marketBlendWeight = (float)(($cfg['market'] ?? [])['blend_weight']      ?? 0.30);
?>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>TasadorIA — Admin</title>
<style>
:root{--bg:#0d0f14;--bg2:#141720;--bg3:#1c2030;--card:#1e2235;--border:#2a2f45;--gold:#c9a84c;--gold2:#f0cc7a;--text:#e8e8f0;--muted:#7a7a9a;--green:#00c896;--red:#ff4f6e;--blue:#4a8ff7;--r:10px}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}
a{color:var(--gold);text-decoration:none}
input,select,button,textarea{font-family:inherit}
/* Layout */
.sidebar{width:190px;flex-shrink:0;background:var(--bg2);border-right:1px solid var(--border);padding:10px 0;display:flex;flex-direction:column;overflow-y:auto}
.logo{padding:0 20px 20px;border-bottom:1px solid var(--border);margin-bottom:16px}
.logo h1{color:var(--gold);font-size:18px;font-family:Georgia,serif}
.logo p{font-size:11px;color:var(--muted)}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 20px;font-size:13px;color:var(--muted);cursor:pointer;transition:all .15s;border:none;background:none;width:100%;text-align:left}
.nav-item:hover{background:rgba(255,255,255,.04);color:var(--text)}
.nav-item.active{background:rgba(201,168,76,.1);color:var(--gold);border-left:3px solid var(--gold)}
.nav-item .icon{width:18px;text-align:center;flex-shrink:0}
.nav-badge{margin-left:auto;background:var(--gold);color:#0d0f14;font-size:10px;font-weight:700;padding:2px 6px;border-radius:10px}
.main{flex:1;padding:24px;overflow-y:auto;min-width:0}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--border)}
.topbar h2{font-size:20px;color:var(--text)}
.panel{display:none}.panel.active{display:block}
/* Login */
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg)}
.login-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:36px;max-width:380px;width:100%}
/* Components */
.btn{padding:9px 18px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s}
.btn-gold{background:var(--gold);color:#0d0f14}.btn-gold:hover{background:var(--gold2)}
.btn-sm{padding:6px 12px;font-size:12px}
.btn-outline{background:transparent;border:1.5px solid var(--border);color:var(--muted)}.btn-outline:hover{border-color:var(--gold);color:var(--gold)}
.btn-green{background:var(--green);color:#0d0f14}
.field label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:5px}
.field input,.field select,.field textarea{width:100%;padding:9px 12px;background:var(--bg3);border:1.5px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;outline:none}
.field input:focus,.field select:focus{border-color:var(--gold)}
.msg{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:18px}
.msg-ok{background:rgba(0,200,150,.1);border:1px solid rgba(0,200,150,.4);color:var(--green)}
.msg-err{background:rgba(255,79,110,.1);border:1px solid rgba(255,79,110,.4);color:var(--red)}
/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.stat-box{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:16px}
.stat-val{font-size:28px;font-weight:700;color:var(--gold)}
.stat-lbl{font-size:11px;color:var(--muted);margin-top:2px;text-transform:uppercase;letter-spacing:.4px}
/* Table */
.tbl{width:100%;border-collapse:collapse;font-size:13px}
.tbl th{padding:8px 12px;background:var(--bg2);color:var(--muted);font-size:11px;text-align:left;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border)}
.tbl td{padding:8px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
.tbl tr:hover td{background:rgba(255,255,255,.02)}
.chip{padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
.chip-gold{background:rgba(201,168,76,.15);color:var(--gold)}
.chip-green{background:rgba(0,200,150,.15);color:var(--green)}
.chip-red{background:rgba(255,79,110,.15);color:var(--red)}
.chip-blue{background:rgba(74,143,247,.15);color:var(--blue)}
.chip-muted{background:rgba(255,255,255,.06);color:var(--muted)}
/* Cards */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-bottom:16px}
.card-title{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:14px}
/* USD card */
.usd-card{background:var(--card);border:2px solid rgba(201,168,76,.35);border-radius:var(--r);padding:18px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.usd-input{width:150px;padding:10px;background:var(--bg3);border:2px solid var(--gold);border-radius:8px;color:var(--gold);font-size:20px;font-weight:700;text-align:center;outline:none}
/* Zone table */
.zone-diff-ok{color:var(--green)}.zone-diff-hi{color:var(--red)}.zone-diff-lo{color:var(--blue)}
.edit-row{display:none}
.inline-edit{background:var(--bg2);border:1px solid rgba(201,168,76,.4);border-radius:8px;padding:14px;margin:4px 0}
.price-inputs{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.pi{flex:1;min-width:90px}
.pi label{font-size:10px;text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:4px}
.pi input{width:100%;padding:8px;background:var(--bg3);border:2px solid var(--border);border-radius:6px;color:var(--text);font-size:15px;font-weight:700;text-align:center;outline:none}
.pi.mn input{border-color:rgba(255,79,110,.5);color:var(--red)}
.pi.av input{border-color:rgba(201,168,76,.6);color:var(--gold)}
.pi.mx input{border-color:rgba(0,200,150,.5);color:var(--green)}
.pi-prev{font-size:10px;color:var(--muted);text-align:center;margin-top:3px}
.suggest-box{background:rgba(74,143,247,.07);border:1px solid rgba(74,143,247,.3);border-radius:6px;padding:8px 12px;font-size:12px;color:var(--blue);margin-bottom:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
/* Import */
.drop-zone{border:2px dashed var(--border);border-radius:10px;padding:30px;text-align:center;cursor:pointer;position:relative;transition:border-color .2s}
.drop-zone:hover,.drop-zone.dragging{border-color:var(--gold);background:rgba(201,168,76,.04)}
.drop-zone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.progress-bar{height:7px;background:var(--border);border-radius:4px;overflow:hidden;margin:8px 0}
.progress-fill{height:100%;background:var(--gold);border-radius:4px;transition:width .4s;width:0}
.log-box{background:#0a0c10;border:1px solid var(--border);border-radius:8px;padding:12px;font-size:12px;font-family:monospace;color:#aaa;max-height:200px;overflow-y:auto;margin-top:10px}
.log-ok{color:var(--green)}.log-err{color:var(--red)}
/* Leads */
.lead-row-detail{display:none;background:var(--bg2);padding:10px 14px;font-size:12px;color:var(--muted);border-top:1px solid var(--border)}
/* Proveedores IA */
.prov-card{border:1px solid var(--border);border-radius:8px;padding:11px 14px;display:flex;align-items:center;gap:10px;margin-bottom:8px}
.prov-card.active{border-color:var(--gold);background:rgba(201,168,76,.05)}
.prov-icon{font-size:18px;flex-shrink:0}
.prov-info{flex:1;min-width:0}
.prov-label{font-size:13px;font-weight:600}
.prov-model{font-size:11px;color:var(--muted);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* Blend bar */
.blend-bar{height:18px;border-radius:6px;overflow:hidden;display:flex;margin:10px 0}
.blend-seg-portal{background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff}
.blend-seg-config{background:var(--gold);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#0d0f14}
@media(max-width:768px){.sidebar{display:none}.stats-row{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<?php if (!$logged): ?>
<div class="login-wrap">
  <div class="login-card">
    <div style="text-align:center;margin-bottom:24px">
      <div style="font-family:Georgia,serif;font-size:28px;color:var(--gold)">TasadorIA</div>
      <div style="font-size:13px;color:var(--muted)">Panel de administración</div>
    </div>
    <?php if (isset($loginErr)): ?><div class="msg msg-err"><?=htmlspecialchars($loginErr)?></div><?php endif;?>
    <form method="POST">
      <div class="field" style="margin-bottom:14px">
        <label>Contraseña</label>
        <input type="password" name="login_pass" autofocus placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-gold" style="width:100%;padding:12px">Ingresar</button>
    </form>
  </div>
</div>
<?php else: ?>
<?php $currentPanel = 'admin'; require __DIR__.'/includes/admin_topnav.php'; ?>
<!-- SIDEBAR + MAIN wrapper -->
<div style="display:flex;flex:1;min-height:0;overflow:hidden">
<!-- SIDEBAR -->
<div class="sidebar">
  <button class="nav-item active" onclick="showTab('dashboard',this)"><span class="icon">📊</span>Dashboard</button>
  <button class="nav-item" onclick="showTab('zonas',this)"><span class="icon">🗺</span>Zonas</button>
  <button class="nav-item" onclick="showTab('importar',this)"><span class="icon">📥</span>Importar XML<?php if ($hasTmp): ?><span class="nav-badge">!</span><?php endif;?></button>
  <button class="nav-item" onclick="showTab('leads',this)"><span class="icon">👥</span>Leads<?php if ($leadsHoy): ?><span class="nav-badge"><?=$leadsHoy?></span><?php endif;?></button>
  <button class="nav-item" onclick="showTab('tasaciones',this)"><span class="icon">📋</span>Tasaciones</button>
  <button class="nav-item" onclick="showTab('buscador',this)"><span class="icon">🔍</span>Buscador</button>
  <button class="nav-item" onclick="showTab('config',this)"><span class="icon">⚙️</span>Config</button>
</div>
<!-- MAIN -->
<div class="main">
<?php if ($saveMsg): ?><div class="msg msg-ok"><?=htmlspecialchars($saveMsg)?></div><?php endif;?>

<!-- ── DASHBOARD ──────────────────────────────────────────────────────────── -->
<div class="panel active" id="tab-dashboard">
  <div class="topbar"><h2>📊 Dashboard</h2></div>
  <div class="stats-row">
    <div class="stat-box"><div class="stat-val"><?=number_format($totalTasaciones)?></div><div class="stat-lbl">Tasaciones</div></div>
    <div class="stat-box"><div class="stat-val"><?=number_format($totalLeads)?></div><div class="stat-lbl">Leads</div><?php if ($leadsHoy): ?><div style="font-size:12px;color:var(--green);margin-top:4px">+<?=$leadsHoy?> hoy</div><?php endif;?></div>
    <div class="stat-box"><div class="stat-val"><?=number_format($totalListings)?></div><div class="stat-lbl">Listings BD</div></div>
    <div class="stat-box"><div class="stat-val">$<?=number_format((int)$cfg['ars_usd_rate'],0,',','.')?></div><div class="stat-lbl">USD/ARS</div></div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <div class="card">
      <div class="card-title">📦 Listings por tipo</div>
      <?php foreach ($marketStats as $s): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px">
        <span><?=htmlspecialchars(ucfirst($s['property_type']))?></span>
        <div style="display:flex;gap:10px;align-items:center">
          <?php if ($s['avg']): ?><span style="color:var(--gold);font-size:12px">$<?=number_format($s['avg'],0,',','.')?>/m²</span><?php endif;?>
          <span style="font-weight:700"><?=number_format($s['c'])?></span>
        </div>
      </div>
      <?php endforeach;?>
    </div>
    <div class="card">
      <div class="card-title">👥 Últimos leads</div>
      <?php foreach (array_slice($recentLeads,0,6) as $l): ?>
      <div style="padding:7px 0;border-bottom:1px solid var(--border);font-size:13px">
        <div style="font-weight:500"><?=htmlspecialchars($l['name'])?></div>
        <div style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($l['email'])?> · <?=substr($l['created_at'],0,10)?></div>
      </div>
      <?php endforeach;?>
      <?php if (empty($recentLeads)): ?><div style="color:var(--muted);font-size:13px">Sin leads aún</div><?php endif;?>
    </div>
  </div>
</div>

<!-- ── PRECIOS Y ZONAS ───────────────────────────────────────────────────── -->
<div class="panel" id="tab-zonas">
  <div class="topbar"><h2>🗺 Precios y Zonas</h2></div>
  <div class="usd-card">
    <div>
      <div style="font-size:13px;font-weight:600;color:var(--gold)">💱 Tipo de cambio</div>
      <div style="font-size:12px;color:var(--muted);margin-top:2px">Afecta todos los precios en ARS</div>
    </div>
    <form method="POST" style="display:flex;align-items:center;gap:10px">
      <span style="color:var(--muted)">1 USD =</span>
      <input type="number" name="usd_rate" class="usd-input" value="<?=(int)$cfg['ars_usd_rate']?>" min="100" max="9999999" step="50">
      <span style="color:var(--muted)">ARS</span>
      <button type="submit" name="save_usd" class="btn btn-gold">Guardar</button>
    </form>
    <div style="margin-left:auto;font-size:13px;color:var(--muted)">
      Actual: <strong style="color:var(--gold)">$<?=number_format((int)$cfg['ars_usd_rate'],0,',','.')?>/USD</strong>
    </div>
  </div>
  <?php
  $mbzIdx = [];
  foreach ($marketByZone as $row) {
    $key = strtolower($row['zone'] ?: $row['city']);
    $mbzIdx[$key] = $row;
  }
  foreach ($zones as $cityKey => $city):
  ?>
  <div class="card">
    <div style="font-size:15px;font-weight:600;color:var(--gold);margin-bottom:14px">🏙 <?=htmlspecialchars($city['label'])?></div>
    <table class="tbl">
      <thead><tr>
        <th style="width:200px">Zona</th>
        <th>Min cfg</th><th>Avg cfg ★</th><th>Max cfg</th>
        <th>Real BD (dptos)</th><th>N</th><th>Diferencia</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($city['zones'] as $zk => $zone):
        $cMin=(int)$zone['price_m2']['min']; $cAvg=(int)$zone['price_m2']['avg']; $cMax=(int)$zone['price_m2']['max'];
        $real = null;
        $zLow = strtolower($zone['label']);
        foreach ($mbzIdx as $k => $row) {
            if (str_contains($zLow,$k)||str_contains($k,explode(' ',$zLow)[0])) { $real=$row; break; }
        }
        if (!$real) foreach ($zone['keywords'] as $kw) foreach ($mbzIdx as $k=>$row) if (str_contains($k,strtolower($kw))) { $real=$row; break 2; }
        $rAvg = $real ? (int)$real['avg'] : null;
        $rCnt = $real ? (int)$real['c'] : 0;
        $fid  = "f-{$cityKey}-{$zk}";
        if ($rAvg && $cAvg) {
            $diff = $rAvg-$cAvg; $pct=round($diff/$cAvg*100);
            if (abs($pct)<=10) $badge='<span class="chip chip-green">✓ OK ±'.abs($pct).'%</span>';
            elseif ($diff>0) $badge='<span class="chip chip-red">▲ +'.abs($pct).'%</span>';
            else $badge='<span class="chip chip-blue">▼ -'.abs($pct).'%</span>';
            $sugAvg=$rAvg; $sugMin=round($rAvg*.80); $sugMax=round($rAvg*1.25);
        } else { $badge='<span class="chip chip-muted">Sin datos</span>'; $sugAvg=null; $sugMin=$cMin; $sugMax=$cMax; }
      ?>
      <tr>
        <td><div style="font-weight:500"><?=htmlspecialchars($zone['label'])?></div></td>
        <td style="color:var(--red)">$<?=number_format($cMin,0,',','.')?></td>
        <td style="color:var(--gold);font-weight:700">$<?=number_format($cAvg,0,',','.')?></td>
        <td style="color:var(--green)">$<?=number_format($cMax,0,',','.')?></td>
        <td><?=$rAvg?'<strong>$'.number_format($rAvg,0,',','.').'</strong>':'<span style="color:var(--muted)">—</span>'?></td>
        <td style="color:var(--muted)"><?=$rCnt?></td>
        <td><?=$badge?></td>
        <td><button class="btn btn-sm btn-outline" onclick="toggleEdit('<?=$fid?>')">✏</button></td>
      </tr>
      <tr class="edit-row" id="<?=$fid?>-row">
        <td colspan="8" style="padding:0 12px 12px">
          <div class="inline-edit">
            <?php if ($sugAvg): ?>
            <div class="suggest-box">
              💡 <strong><?=$rCnt?> datos reales → Sugerido:</strong> Min=$<?=number_format($sugMin,0,',','.')?> · Avg=$<?=number_format($sugAvg,0,',','.')?> · Max=$<?=number_format($sugMax,0,',','.')?>
              <button type="button" class="btn btn-sm" style="background:rgba(74,143,247,.2);color:var(--blue);border:1px solid var(--blue)" onclick="fillSug('<?=$fid?>',<?=$sugMin?>,<?=$sugAvg?>,<?=$sugMax?>)">Aplicar</button>
            </div>
            <?php endif;?>
            <form method="POST">
              <input type="hidden" name="save_zone" value="1">
              <input type="hidden" name="city_key" value="<?=$cityKey?>">
              <input type="hidden" name="zone_key" value="<?=$zk?>">
              <div class="price-inputs">
                <div class="pi mn"><label style="color:var(--red)">Mínimo</label><input type="number" name="price_min" id="<?=$fid?>-mn" value="<?=$cMin?>" min="50" max="20000" step="50" oninput="prevP('<?=$fid?>-mn-p',this.value,65)"><div class="pi-prev" id="<?=$fid?>-mn-p">65m²=$<?=number_format($cMin*65,0,',','.')?></div></div>
                <div class="pi av"><label style="color:var(--gold)">Promedio ★</label><input type="number" name="price_avg" id="<?=$fid?>-av" value="<?=$cAvg?>" min="50" max="20000" step="50" oninput="prevP('<?=$fid?>-av-p',this.value,65)"><div class="pi-prev" id="<?=$fid?>-av-p">65m²=$<?=number_format($cAvg*65,0,',','.')?></div></div>
                <div class="pi mx"><label style="color:var(--green)">Máximo</label><input type="number" name="price_max" id="<?=$fid?>-mx" value="<?=$cMax?>" min="50" max="20000" step="50" oninput="prevP('<?=$fid?>-mx-p',this.value,65)"><div class="pi-prev" id="<?=$fid?>-mx-p">65m²=$<?=number_format($cMax*65,0,',','.')?></div></div>
                <div><label style="color:transparent">.</label><button type="submit" class="btn btn-gold">💾 Guardar</button></div>
              </div>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endforeach;?>
</div>

<!-- ── IMPORTAR XML ──────────────────────────────────────────────────────── -->
<div class="panel" id="tab-importar">
  <div class="topbar"><h2>📥 Importar WordPress XML</h2></div>
  <div class="card">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;text-align:center">
      <div style="padding:12px;background:var(--bg2);border-radius:8px;font-size:13px"><strong style="color:var(--gold);font-size:20px">1</strong><br>Subí el XML de WordPress</div>
      <div style="padding:12px;background:var(--bg2);border-radius:8px;font-size:13px"><strong style="color:var(--gold);font-size:20px">2</strong><br>Clic en Importar</div>
      <div style="padding:12px;background:var(--bg2);border-radius:8px;font-size:13px"><strong style="color:var(--gold);font-size:20px">3</strong><br>El motor usa los precios reales</div>
    </div>
    <?php if (str_starts_with($uploadMsg??'','ok:')): ?>
    <div class="msg msg-ok">✓ XML listo — <strong><?=$uploadCount?> propiedades</strong> para importar</div>
    <?php elseif (str_starts_with($uploadMsg??'','error:')): ?>
    <div class="msg msg-err">✗ <?=htmlspecialchars(substr($uploadMsg,6))?></div>
    <?php elseif ($hasTmp): ?>
    <div class="msg" style="background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.3);color:var(--gold)">📂 XML cargado. Clic en Importar o subí otro.</div>
    <?php endif;?>
    <form method="POST" enctype="multipart/form-data" style="margin-bottom:14px">
      <div class="field" style="margin-bottom:10px">
        <label>Archivo XML de WordPress (Houzez)</label>
        <div class="drop-zone" id="idz">
          <input type="file" name="xml_file" accept=".xml" onchange="showFn(this)">
          <div style="font-size:28px;margin-bottom:6px">📂</div>
          <div style="font-size:13px;color:var(--muted)">Arrastrá el XML o hacé clic</div>
          <div id="ifn" style="font-size:12px;color:var(--gold);margin-top:4px"></div>
        </div>
      </div>
      <button type="submit" class="btn btn-outline btn-sm">📤 Subir XML</button>
    </form>
    <button class="btn btn-gold" id="ibtn" onclick="startImport()" <?=(!$hasTmp&&!$uploadCount)?'disabled':''?>>
      ✦ Importar <?=($uploadCount?$uploadCount:'propiedades')?>
    </button>
    <div id="ipw" style="display:none;margin-top:16px">
      <div id="ipt" style="font-size:13px;color:var(--muted)">Iniciando...</div>
      <div class="progress-bar"><div class="progress-fill" id="ipf"></div></div>
      <div class="log-box" id="ilog"></div>
    </div>
  </div>
  <?php if ($totalListings > 0): ?>
  <div class="card">
    <div class="card-title">📊 Datos en BD (market_listings)</div>
    <table class="tbl">
      <thead><tr><th>Ciudad</th><th>Zona</th><th>N</th><th>Min</th><th>Avg ★</th><th>Max</th></tr></thead>
      <tbody>
      <?php foreach ($marketByZone as $row): ?>
      <tr>
        <td><?=htmlspecialchars($row['city']??'—')?></td>
        <td><?=htmlspecialchars($row['zone']??'—')?></td>
        <td><strong><?=$row['c']?></strong></td>
        <td style="color:var(--red)">$<?=number_format($row['mn'],0,',','.')?></td>
        <td style="color:var(--gold);font-weight:700">$<?=number_format($row['avg'],0,',','.')?></td>
        <td style="color:var(--green)">$<?=number_format($row['mx'],0,',','.')?></td>
      </tr>
      <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif;?>
</div>

<!-- ── LEADS ─────────────────────────────────────────────────────────────── -->
<div class="panel" id="tab-leads">
  <div class="topbar">
    <h2>👥 Registro de leads (<?=number_format($totalLeads)?>)</h2>
    <?php if ($totalLeads > 0): ?>
    <a href="?export_leads=1" class="btn btn-outline btn-sm">⬇ Exportar CSV</a>
    <?php endif;?>
  </div>
  <?php
  if (isset($_GET['export_leads']) && $pdo) {
      $leads = dbq($pdo, "SELECT id,name,email,phone,result_code,property_data,email_sent,contacted,created_at FROM tasacion_leads ORDER BY created_at DESC");
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="leads_tasador_'.date('Y-m-d').'.csv"');
      echo "\xEF\xBB\xBF";
      echo "ID,Nombre,Email,Teléfono,Código tasación,Email enviado,Contactado,Fecha\n";
      foreach ($leads as $l) echo "{$l['id']},".'"'.$l['name'].'"','"'.$l['email'].'"','"'.$l['phone'].'"','"'.$l['result_code'].'"',($l['email_sent']?'Sí':'No'),($l['contacted']?'Sí':'No'),'"'.$l['created_at'].'"'."\n";
      exit;
  }
  ?>
  <?php if (empty($recentLeads)): ?>
  <div style="text-align:center;padding:40px;color:var(--muted);font-size:14px">
    Sin leads aún. Cuando alguien complete el formulario del tasador aparecen acá.
  </div>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Tasación</th><th>Email</th><th>Fecha</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recentLeads as $i => $lead):
      $prop = json_decode($lead['property_data'] ?? '{}', true) ?? [];
    ?>
    <tr onclick="toggleDetail(<?=$i?>)" style="cursor:pointer">
      <td><strong><?=htmlspecialchars($lead['name'])?></strong></td>
      <td><a href="mailto:<?=htmlspecialchars($lead['email'])?>" onclick="event.stopPropagation()" style="color:var(--gold)"><?=htmlspecialchars($lead['email'])?></a></td>
      <td><?=htmlspecialchars($lead['phone'] ?? '—')?></td>
      <td><span class="chip chip-gold"><?=htmlspecialchars($lead['result_code'] ?? '—')?></span></td>
      <td><?=$lead['email_sent']?'<span class="chip chip-green">✓</span>':'<span class="chip chip-muted">No</span>'?></td>
      <td style="color:var(--muted);font-size:12px"><?=substr($lead['created_at'],0,16)?></td>
      <td style="color:var(--muted)">▼</td>
    </tr>
    <tr id="detail-<?=$i?>" style="display:none">
      <td colspan="7" style="padding:0">
        <div class="lead-row-detail" style="display:block;background:var(--bg2);padding:12px 16px;font-size:12px">
          <?php if (!empty($prop)): ?>
          <div style="display:flex;gap:20px;flex-wrap:wrap;color:var(--muted)">
            <?php foreach (['property_type'=>'Tipo','covered_area'=>'m²','age_years'=>'Años','condition'=>'Estado','city'=>'Ciudad','address'=>'Dirección'] as $k=>$label): ?>
              <?php if (!empty($prop[$k])): ?>
              <div><strong style="color:var(--text)"><?=$label?>:</strong> <?=htmlspecialchars((string)$prop[$k])?></div>
              <?php endif;?>
            <?php endforeach;?>
          </div>
          <?php else: ?>
          <span style="color:var(--muted)">Sin datos de propiedad</span>
          <?php endif;?>
        </div>
      </td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table>
  <?php endif;?>
</div>

<!-- ── TASACIONES ─────────────────────────────────────────────────────────── -->
<div class="panel" id="tab-tasaciones">
  <div class="topbar"><h2>📋 Tasaciones (<?=number_format($totalTasaciones)?>)</h2></div>
  <?php if (empty($recentTasaciones)): ?>
  <div style="text-align:center;padding:40px;color:var(--muted)">Sin tasaciones aún.</div>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>Código</th><th>Zona</th><th>Precio USD</th><th>Contacto</th><th>Fecha</th></tr></thead>
    <tbody>
    <?php foreach ($recentTasaciones as $t):
      $res = json_decode($t['result_json'] ?? '{}', true) ?? [];
      $price = isset($res['suggested']) ? '$'.number_format($res['suggested'],0,',','.') : '—';
    ?>
    <tr>
      <td><span class="chip chip-gold"><?=htmlspecialchars($t['code'])?></span></td>
      <td>
        <div style="font-weight:500"><?=htmlspecialchars($t['zone']??'—')?></div>
        <div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($t['city']??'')?></div>
      </td>
      <td style="color:var(--gold);font-weight:700"><?=$price?></td>
      <td>
        <?php if ($t['name']??false): ?>
        <div style="font-size:13px"><?=htmlspecialchars($t['name'])?></div>
        <div style="font-size:11px;color:var(--muted)"><?=htmlspecialchars($t['email']??'')?></div>
        <?php else: ?><span style="color:var(--muted)">—</span><?php endif;?>
      </td>
      <td style="font-size:12px;color:var(--muted)"><?=substr($t['created_at'],0,16)?></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table>
  <?php endif;?>
</div>

<!-- ── BUSCADOR ──────────────────────────────────────────────────────────── -->
<div class="panel" id="tab-buscador">
  <div class="topbar">
    <h2>🔍 Buscador de propiedades</h2>
    <div style="font-size:12px;color:var(--muted)" id="search-total-info"></div>
  </div>
  <div class="card" style="margin-bottom:14px">
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:12px">
      <div class="field"><label>Texto libre</label><input type="text" id="sf-texto" placeholder="dirección, zona, título..." onkeyup="if(event.key==='Enter')doSearch()"></div>
      <div class="field"><label>Ciudad</label><input type="text" id="sf-ciudad" placeholder="Santa Fe, Buenos Aires..."></div>
      <div class="field"><label>Zona / Barrio</label><input type="text" id="sf-zona" placeholder="Candioti, Palermo..."></div>
      <div class="field"><label>Tipo</label>
        <select id="sf-tipo">
          <option value="">Todos</option><option value="departamento">Departamento</option><option value="casa">Casa</option>
          <option value="ph">PH</option><option value="terreno">Terreno</option><option value="local-comercial">Local comercial</option>
          <option value="oficina">Oficina</option><option value="cochera">Cochera</option><option value="galpon">Galpón</option>
        </select>
      </div>
      <div class="field"><label>Operación</label>
        <select id="sf-op"><option value="">Todas</option><option value="venta">Venta</option><option value="alquiler">Alquiler</option></select>
      </div>
      <div class="field"><label>Precio USD mín</label><input type="number" id="sf-pmin" placeholder="0" min="0" step="1000"></div>
      <div class="field"><label>Precio USD máx</label><input type="number" id="sf-pmax" placeholder="999999" min="0" step="1000"></div>
      <div class="field"><label>Superficie m² mín</label><input type="number" id="sf-amin" placeholder="0" min="0" step="5"></div>
      <div class="field"><label>Superficie m² máx</label><input type="number" id="sf-amax" placeholder="999" min="0" step="5"></div>
      <div class="field"><label>Dormitorios mín</label><input type="number" id="sf-dmin" placeholder="0" min="0" max="10"></div>
      <div class="field"><label>Baños mín</label><input type="number" id="sf-banos" placeholder="0" min="0" max="5"></div>
      <div class="field"><label>Cochera</label>
        <select id="sf-cochera"><option value="">Sin filtro</option><option value="1">Con cochera</option></select>
      </div>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <button class="btn btn-gold" onclick="doSearch()">🔍 Buscar</button>
      <button class="btn btn-outline btn-sm" onclick="clearSearch()">✕ Limpiar</button>
      <div style="display:flex;align-items:center;gap:8px;margin-left:auto">
        <label style="font-size:12px;color:var(--muted)">Ordenar por:</label>
        <select id="sf-order" onchange="doSearch()" style="padding:6px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:12px">
          <option value="scraped_at">Fecha</option><option value="price_usd">Precio</option><option value="price_per_m2">USD/m²</option><option value="covered_area">Superficie</option>
        </select>
        <select id="sf-dir" onchange="doSearch()" style="padding:6px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:12px">
          <option value="ASC">↑ Menor</option><option value="DESC" selected>↓ Mayor</option>
        </select>
        <select id="sf-limit" onchange="doSearch()" style="padding:6px 10px;background:var(--bg3);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:12px">
          <option value="20">20</option><option value="50">50</option><option value="100">100</option>
        </select>
      </div>
    </div>
  </div>
  <div id="search-stats" style="display:none;background:var(--card);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:12px;display:grid;grid-template-columns:repeat(5,1fr);gap:12px">
    <div><div id="ss-total" style="font-size:20px;font-weight:700;color:var(--gold)">0</div><div style="font-size:11px;color:var(--muted)">Resultados</div></div>
    <div><div id="ss-avg-p" style="font-size:20px;font-weight:700;color:var(--gold)">—</div><div style="font-size:11px;color:var(--muted)">Precio prom.</div></div>
    <div><div id="ss-avg-m" style="font-size:20px;font-weight:700;color:var(--gold)">—</div><div style="font-size:11px;color:var(--muted)">USD/m² prom.</div></div>
    <div><div id="ss-avg-a" style="font-size:20px;font-weight:700;color:var(--gold)">—</div><div style="font-size:11px;color:var(--muted)">Sup. prom.</div></div>
    <div><div id="ss-range" style="font-size:14px;font-weight:700;color:var(--muted)">—</div><div style="font-size:11px;color:var(--muted)">Rango precios</div></div>
  </div>
  <div id="search-loading" style="display:none;text-align:center;padding:30px;color:var(--muted);font-size:14px">⏳ Buscando...</div>
  <div id="search-empty" style="display:none;text-align:center;padding:40px;color:var(--muted);font-size:14px">Sin resultados para los filtros seleccionados.</div>
  <div id="search-error" style="display:none;padding:14px;background:rgba(255,79,110,.1);border:1px solid rgba(255,79,110,.3);border-radius:8px;color:var(--red);font-size:13px;margin-bottom:12px"></div>
  <div id="search-results-wrap" style="display:none">
    <table class="tbl"><thead><tr><th>Propiedad</th><th>Tipo</th><th>m²</th><th>Dorm.</th><th>Precio</th><th>USD/m²</th><th>Expensas</th><th>Zona</th><th>Fuente</th><th>Fecha</th></tr></thead>
    <tbody id="search-tbody"></tbody></table>
    <div id="search-pagination" style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:16px;padding:12px">
      <button id="btn-prev-page" class="btn btn-outline btn-sm" onclick="changePage(-1)" disabled>← Anterior</button>
      <span id="page-info" style="font-size:13px;color:var(--muted)"></span>
      <button id="btn-next-page" class="btn btn-outline btn-sm" onclick="changePage(1)">Siguiente →</button>
    </div>
  </div>
  <div class="card" style="margin-top:16px">
    <div style="font-size:13px;font-weight:600;color:var(--gold);margin-bottom:12px">🔖 Extractores por portal — Arrastrá a favoritos</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px">
      <a class="bm-btn zonaprop" href="#" onclick="alert('Arrastrá a favoritos');return false;">📊 Zonaprop</a>
      <a class="bm-btn argenprop" href="#" onclick="alert('Arrastrá a favoritos');return false;">📊 Argenprop</a>
      <a class="bm-btn ventafe" href="#" onclick="alert('Arrastrá a favoritos');return false;">📊 Ventafe</a>
      <a class="bm-btn mercado" href="#" onclick="alert('Arrastrá a favoritos');return false;">📊 Mercado Único</a>
      <a class="bm-btn generico" href="#" onclick="alert('Arrastrá a favoritos');return false;">📊 Cualquier portal</a>
    </div>
    <div style="font-size:11px;color:var(--muted)">⬆ Arrastrá cualquier botón a la barra de favoritos. Luego navegá al portal y hacé clic en el favorito para extraer propiedades.</div>
    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
      <a href="https://www.zonaprop.com.ar/departamentos-venta-ciudad-de-santa-fe-sf.html" target="_blank" class="btn btn-sm btn-outline">Zonaprop SF ↗</a>
      <a href="https://www.argenprop.com/departamentos/venta/santa-fe" target="_blank" class="btn btn-sm btn-outline">Argenprop SF ↗</a>
      <a href="https://ventafe.com.ar" target="_blank" class="btn btn-sm btn-outline">Ventafe ↗</a>
      <a href="https://www.mercado-unico.com" target="_blank" class="btn btn-sm btn-outline">Mercado Único ↗</a>
    </div>
  </div>
</div>
<style>
.bm-btn{display:inline-block;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:700;cursor:grab;text-decoration:none;border:2px solid}
.bm-btn.zonaprop{background:rgba(0,160,233,.15);border-color:rgba(0,160,233,.4);color:#00a0e9}
.bm-btn.argenprop{background:rgba(255,107,53,.15);border-color:rgba(255,107,53,.4);color:#ff6b35}
.bm-btn.ventafe{background:rgba(0,200,150,.15);border-color:rgba(0,200,150,.4);color:#00c896}
.bm-btn.mercado{background:rgba(201,168,76,.15);border-color:rgba(201,168,76,.4);color:#c9a84c}
.bm-btn.generico{background:rgba(255,255,255,.06);border-color:var(--border);color:var(--muted)}
.tag-src{padding:2px 7px;border-radius:8px;font-size:10px;font-weight:700}
.tag-zp{background:rgba(0,160,233,.15);color:#00a0e9}
.tag-ap{background:rgba(255,107,53,.15);color:#ff6b35}
.tag-vf{background:rgba(0,200,150,.15);color:#00c896}
.tag-wp{background:rgba(201,168,76,.15);color:#c9a84c}
.tag-mu{background:rgba(150,100,200,.15);color:#9664c8}
</style>
<script>
var searchPage=0,searchLimit=20,lastTotal=0;
document.addEventListener('DOMContentLoaded',function(){
  var bmCode="javascript:(function(){var s=document.createElement('script');s.src='https://anperprimo.com/tasador/multi_extractor.js?t='+Date.now();document.head.appendChild(s);})();";
  document.querySelectorAll('.bm-btn').forEach(function(btn){btn.href=bmCode;btn.removeAttribute('onclick');});
});
function doSearch(resetPage){
  if(resetPage!==false)searchPage=0;
  searchLimit=parseInt(document.getElementById('sf-limit').value)||20;
  var params={
    texto:document.getElementById('sf-texto').value.trim(),
    ciudad:document.getElementById('sf-ciudad').value.trim(),
    zona:document.getElementById('sf-zona').value.trim(),
    tipo:document.getElementById('sf-tipo').value,
    operacion:document.getElementById('sf-op').value,
    precio_min:parseFloat(document.getElementById('sf-pmin').value)||0,
    precio_max:parseFloat(document.getElementById('sf-pmax').value)||0,
    area_min:parseFloat(document.getElementById('sf-amin').value)||0,
    area_max:parseFloat(document.getElementById('sf-amax').value)||0,
    dorm_min:parseInt(document.getElementById('sf-dmin').value)||0,
    banos:parseInt(document.getElementById('sf-banos').value)||0,
    cochera:document.getElementById('sf-cochera').value,
    order:document.getElementById('sf-order').value,
    dir:document.getElementById('sf-dir').value,
    limit:searchLimit,offset:searchPage*searchLimit
  };
  Object.keys(params).forEach(k=>{if(!params[k]&&params[k]!==0)delete params[k];});
  document.getElementById('search-loading').style.display='block';
  document.getElementById('search-results-wrap').style.display='none';
  document.getElementById('search-empty').style.display='none';
  document.getElementById('search-error').style.display='none';
  document.getElementById('search-stats').style.display='none';
  fetch('api/search_properties.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(params)})
  .then(r=>r.json()).then(renderResults)
  .catch(e=>{document.getElementById('search-loading').style.display='none';document.getElementById('search-error').style.display='block';document.getElementById('search-error').textContent='Error: '+e.message;});
}
function renderResults(d){
  document.getElementById('search-loading').style.display='none';
  if(d.error){document.getElementById('search-error').style.display='block';document.getElementById('search-error').textContent='Error: '+d.error;return;}
  lastTotal=d.total;
  var fmt=n=>n?parseInt(n).toLocaleString('es-AR'):'—';
  if(d.stats){
    document.getElementById('search-stats').style.display='grid';
    document.getElementById('ss-total').textContent=fmt(d.total);
    document.getElementById('ss-avg-p').textContent=d.stats.avg_precio?'USD '+fmt(d.stats.avg_precio):'—';
    document.getElementById('ss-avg-m').textContent=d.stats.avg_ppm2?'$'+fmt(d.stats.avg_ppm2):'—';
    document.getElementById('ss-avg-a').textContent=d.stats.avg_area?fmt(d.stats.avg_area)+' m²':'—';
    document.getElementById('ss-range').textContent=d.stats.min_precio&&d.stats.max_precio?'USD '+fmt(d.stats.min_precio)+' — '+fmt(d.stats.max_precio):'—';
  }
  document.getElementById('search-total-info').textContent=d.total+' resultado'+(d.total!==1?'s':'')+' · mostrando '+d.showing;
  if(d.total===0){document.getElementById('search-empty').style.display='block';return;}
  document.getElementById('search-results-wrap').style.display='block';
  function srcTag(src){
    src=src||'';
    if(src.includes('zonaprop'))return'<span class="tag-src tag-zp">ZP</span>';
    if(src.includes('argenprop'))return'<span class="tag-src tag-ap">AP</span>';
    if(src.includes('ventafe'))return'<span class="tag-src tag-vf">VF</span>';
    if(src.includes('wp_litoral'))return'<span class="tag-src tag-wp">LP</span>';
    if(src.includes('mercado'))return'<span class="tag-src tag-mu">MU</span>';
    return'<span class="tag-src" style="background:rgba(255,255,255,.06);color:var(--muted)">'+src.slice(0,4)+'</span>';
  }
  var rows=d.results.map(function(r){
    var ts=(r.address||r.title||'').slice(0,40);
    var tf=r.address||r.title||'—';
    var ul=r.url?'<a href="'+r.url+'" target="_blank" style="color:var(--gold)" title="'+tf+'">'+ts+' ↗</a>':'<span title="'+tf+'">'+ts+'</span>';
    var sp=[];if(r.bedrooms)sp.push(r.bedrooms+' dorm');if(r.bathrooms)sp.push(r.bathrooms+'b');if(r.garages)sp.push('🚗');
    return'<tr><td>'+ul+(r.zone?'<br><small style="color:var(--muted)">'+r.zone+'</small>':'')+'</td>'
      +'<td><span style="color:var(--muted);font-size:12px">'+(r.property_type||'—')+'</span></td>'
      +'<td>'+(r.covered_area?r.covered_area+'m²':'—')+'</td>'
      +'<td style="font-size:12px">'+sp.join(' · ')+'</td>'
      +'<td style="color:var(--gold);font-weight:700">'+(r.price_usd?'USD '+fmt(r.price_usd):'—')+'</td>'
      +'<td style="color:var(--muted);font-size:12px">'+(r.price_per_m2?'$'+fmt(r.price_per_m2):'—')+'</td>'
      +'<td style="font-size:12px;color:var(--muted)">'+(r.expenses_ars?'$'+fmt(r.expenses_ars):'—')+'</td>'
      +'<td style="font-size:12px;color:var(--muted)">'+(r.city||'—')+'</td>'
      +'<td>'+srcTag(r.source)+'</td>'
      +'<td style="font-size:11px;color:var(--muted)">'+(r.scraped_at||'—')+'</td></tr>';
  }).join('');
  document.getElementById('search-tbody').innerHTML=rows;
  var totalPages=Math.ceil(d.total/searchLimit);
  document.getElementById('page-info').textContent='Pág. '+(searchPage+1)+' de '+totalPages+' ('+d.total+' total)';
  document.getElementById('btn-prev-page').disabled=searchPage===0;
  document.getElementById('btn-next-page').disabled=(searchPage+1)*searchLimit>=d.total;
}
function changePage(dir){searchPage=Math.max(0,searchPage+dir);doSearch(false);document.getElementById('tab-buscador').scrollIntoView({behavior:'smooth'});}
function clearSearch(){
  ['sf-texto','sf-ciudad','sf-zona','sf-pmin','sf-pmax','sf-amin','sf-amax','sf-dmin','sf-banos'].forEach(id=>{var el=document.getElementById(id);if(el)el.value='';});
  ['sf-tipo','sf-op','sf-cochera'].forEach(id=>{var el=document.getElementById(id);if(el)el.value='';});
  document.getElementById('search-results-wrap').style.display='none';
  document.getElementById('search-stats').style.display='none';
  document.getElementById('search-total-info').textContent='';
}
</script>

<!-- ── CONFIGURACIÓN ─────────────────────────────────────────────────────── -->
<div class="panel" id="tab-config">
  <div class="topbar"><h2>⚙️ Configuración</h2></div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

    <!-- Tipo de cambio -->
    <div class="card">
      <div class="card-title">💱 Tipo de cambio</div>
      <form method="POST">
        <div class="field" style="margin-bottom:12px">
          <label>ARS por 1 USD</label>
          <input type="number" name="usd_rate" value="<?=(int)$cfg['ars_usd_rate']?>" min="100" max="9999999" step="50" style="font-size:20px;font-weight:700;color:var(--gold)">
        </div>
        <button type="submit" name="save_usd" class="btn btn-gold">Guardar</button>
      </form>
    </div>

    <!-- URLs del sistema -->
    <div class="card">
      <div class="card-title">🔗 URLs del sistema</div>
      <div style="font-size:13px;line-height:2.2;color:var(--muted)">
        <div>Tasador: <a href="../"><?=htmlspecialchars($cfg['app_url']??'')?>/</a></div>
        <div>Motor API: <a href="api/valuar.php" target="_blank">api/valuar.php</a></div>
        <div>IA: <a href="api/analyze.php" target="_blank">api/analyze.php</a></div>
        <div>BD: <?=htmlspecialchars($cfg['db']['name'])?> @ <?=htmlspecialchars($cfg['db']['host'])?></div>
        <div>PHP: <?=PHP_VERSION?></div>
      </div>
    </div>

    <!-- Email SMTP -->
    <div class="card">
      <div class="card-title">📧 Email / SMTP</div>
      <div style="font-size:13px;color:var(--muted);line-height:2">
        <div>Admin email: <strong style="color:var(--text)"><?=htmlspecialchars($cfg['agency_email']??'')?></strong></div>
        <div>SMTP host: <strong style="color:var(--text)"><?=htmlspecialchars($cfg['smtp']['host']??'(mail del servidor)')?></strong></div>
        <div>SMTP puerto: <?=htmlspecialchars((string)($cfg['smtp']['port']??587))?></div>
      </div>
      <div style="margin-top:10px;font-size:12px;color:var(--muted)">Para cambiar SMTP editar <code>config/settings.php</code></div>
    </div>

    <!-- Proveedores de IA -->
    <div class="card">
      <div class="card-title">🤖 Proveedores de IA</div>
      <?php foreach ($aiProviders as $pKey => $pInfo):
        $pCfg    = is_array($aiProvsCfg[$pKey] ?? null) ? $aiProvsCfg[$pKey] : [];
        $isActive = ($pKey === $activeProv);
        $hasKey   = !empty($pCfg['api_key']) || ($isActive && !empty($aiCfgBlock['api_key']));
        $pModel   = $pCfg['model'] ?? ($isActive ? ($aiCfgBlock['model'] ?? '') : '');
      ?>
      <div class="prov-card <?=$isActive?'active':''?>">
        <div class="prov-icon"><?=$pInfo['icon']?></div>
        <div class="prov-info">
          <div class="prov-label"><?=htmlspecialchars($pInfo['label'])?>
            <?=$isActive?' <span class="chip chip-gold" style="font-size:10px;padding:1px 5px;margin-left:4px">Activo</span>':''?>
          </div>
          <div class="prov-model"><?=$pModel?htmlspecialchars($pModel):'<em style="color:var(--muted)">sin modelo</em>'?></div>
        </div>
        <div><?=$hasKey?'<span class="chip chip-green">✓ Key</span>':'<span class="chip chip-muted">Sin key</span>'?></div>
      </div>
      <?php endforeach;?>
      <div style="margin-top:8px;font-size:12px;color:var(--muted)">Cambiar proveedor: editar <code>config/settings.php</code> → <code>ai.provider</code></div>
    </div>

    <!-- Datos de mercado — ancho completo -->
    <div class="card" style="grid-column:1/-1">
      <div class="card-title">📊 Motor de precio — Blend portales vs zonas</div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:10px">
        El precio final mezcla datos reales de portales (con descuento) y los precios de configuración de zonas.
      </div>
      <?php
        $pctPortal = (int)round($marketBlendWeight * 100);
        $pctCfg    = 100 - $pctPortal;
      ?>
      <div class="blend-bar">
        <div class="blend-seg-portal" style="width:<?=$pctPortal?>%"><?=$pctPortal?>% portales</div>
        <div class="blend-seg-config" style="width:<?=$pctCfg?>%"><?=$pctCfg?>% zonas cfg</div>
      </div>
      <div style="display:flex;gap:24px;margin-top:10px;font-size:13px;flex-wrap:wrap">
        <div><span style="color:var(--muted)">Factor descuento portales:</span>
          <strong style="color:var(--blue);margin-left:6px"><?=(int)round($marketCorrFactor*100)?>%</strong>
          <span style="font-size:11px;color:var(--muted);margin-left:4px">(del precio publicado)</span>
        </div>
        <div><span style="color:var(--muted)">Peso portales:</span> <strong style="color:var(--blue);margin-left:6px"><?=$pctPortal?>%</strong></div>
        <div><span style="color:var(--muted)">Peso zonas config:</span> <strong style="color:var(--gold);margin-left:6px"><?=$pctCfg?>%</strong></div>
        <div><span style="color:var(--muted)">Mínimo listings para blend:</span> <strong style="color:var(--text);margin-left:6px">3</strong></div>
      </div>
      <div style="margin-top:10px;font-size:12px;color:var(--muted)">Ajustar: editar <code>config/settings.php</code> → <code>market.correction_factor</code> y <code>market.blend_weight</code></div>
    </div>

  </div>
</div>

</div><!-- /main -->
</div><!-- /sidebar+main wrapper -->
<script>
function showTab(id,btn){
  document.querySelectorAll('.panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active');
  if(btn)btn.classList.add('active');
}
function toggleEdit(id){
  const r=document.getElementById(id+'-row');
  r.style.display=r.style.display==='table-row'?'none':'table-row';
}
function prevP(pid,val,m2){
  const el=document.getElementById(pid);
  if(el)el.textContent=m2+'m²=$'+(parseInt(val)||0)*m2;
}
function fillSug(fid,mn,av,mx){
  ['mn','av','mx'].forEach((k,i)=>{
    const el=document.getElementById(fid+'-'+k);
    if(el){el.value=[mn,av,mx][i];prevP(fid+'-'+k+'-p',el.value,65);}
  });
}
function showFn(inp){document.getElementById('ifn').textContent=inp.files[0]?.name?'✓ '+inp.files[0].name:'';}
const idz=document.getElementById('idz');
if(idz){
  idz.addEventListener('dragover',e=>{e.preventDefault();idz.classList.add('dragging');});
  idz.addEventListener('dragleave',()=>idz.classList.remove('dragging'));
  idz.addEventListener('drop',e=>{e.preventDefault();idz.classList.remove('dragging');});
}
let impTotal=0,impErr=0;
async function startImport(){
  const btn=document.getElementById('ibtn');
  btn.disabled=true;btn.textContent='Importando...';
  document.getElementById('ipw').style.display='block';
  impTotal=0;impErr=0;
  await impBatch(0);
}
async function impBatch(offset){
  const fd=new FormData();fd.append('action','import_batch');fd.append('offset',offset);
  try{
    const text=await(await fetch('admin.php',{method:'POST',body:fd})).text();
    const start=text.indexOf('{');
    const data=JSON.parse(start>=0?text.slice(start):text);
    if(data.error){iLog('✗ '+data.error,'err');document.getElementById('ibtn').textContent='Error';return;}
    impTotal+=data.done;impErr+=data.errors;
    document.getElementById('ipf').style.width=data.progress+'%';
    document.getElementById('ipt').textContent='Procesando '+Math.min(data.next_offset,data.total)+' de '+data.total+' ('+data.progress+'%)';
    iLog('Lote '+offset+'–'+data.next_offset+': '+data.done+' OK, '+data.errors+' errores',data.errors?'err':'ok');
    if(data.first_error&&data.errors)iLog('⚠ '+data.first_error,'err');
    if(!data.finished){await new Promise(r=>setTimeout(r,250));await impBatch(data.next_offset);}
    else{
      document.getElementById('ipt').textContent='✅ '+impTotal+' importadas, '+impErr+' errores';
      document.getElementById('ibtn').textContent='✓ Listo';
      document.getElementById('ibtn').style.background='var(--green)';
      if(data.stats?.length){iLog('─── BD ───','ok');data.stats.forEach(s=>iLog(s,'ok'));}
    }
  }catch(e){iLog('✗ '+e.message,'err');}
}
function iLog(msg,cls=''){
  const log=document.getElementById('ilog');
  const p=document.createElement('p');
  p.className=cls==='ok'?'log-ok':cls==='err'?'log-err':'';
  p.textContent=msg;log.appendChild(p);log.scrollTop=log.scrollHeight;
}
function toggleDetail(i){
  const el=document.getElementById('detail-'+i);
  if(el)el.style.display=el.style.display==='table-row'?'none':'table-row';
}
</script>
<?php endif; ?>
</body>
</html>
