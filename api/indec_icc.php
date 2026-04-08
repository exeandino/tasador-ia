<?php
/**
 * TasadorIA — api/indec_icc.php
 * Actualiza precios de materiales usando el ICC INDEC (datos.gob.ar).
 *
 * GET  ?preview=1  →  muestra qué cambiaría sin guardar
 * POST { preview:false }  →  aplica cambios
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Helper para salir con JSON limpio
function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['ta_admin']) && !isset($_SESSION['bim_ok'])) {
    out(['success'=>false,'error'=>'No autorizado'], 403);
}

$cfg     = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$preview = ($_SERVER['REQUEST_METHOD'] === 'GET') || !empty($input['preview']);

// ── 1. Obtener ICC materiales desde datos.gob.ar ──────────────
function fetchICC(): array {
    // IDs de series del ICC en datos.gob.ar (orden de probabilidad)
    $candidates = [
        'icc_1.1_materiales_total',
        'icc_2.1_materiales_total',
        'icc_1_materiales_total',
        'icc_materiales',
        'ICC_MATERIALES',
    ];

    // Intentar descubrir el ID correcto buscando en el catálogo
    $searchUrl = 'https://apis.datos.gob.ar/series/api/search/?q=costo+construccion+materiales&limit=10';
    $ch = curl_init($searchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: TasadorIA/5.0'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $body) {
        $d = json_decode($body, true);
        foreach ($d['data'] ?? [] as $series) {
            $title = strtolower($series['title'] ?? '');
            $id    = $series['id'] ?? '';
            if ($id && (strpos($title,'material') !== false || strpos($title,'materiales') !== false)
                    && strpos($title,'construc') !== false) {
                array_unshift($candidates, $id); // ponerlo primero
                break;
            }
        }
    }

    // Probar cada candidato
    foreach (array_unique($candidates) as $seriesId) {
        $url = "https://apis.datos.gob.ar/series/api/series/?ids={$seriesId}&limit=3&sort=desc&format=json";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: TasadorIA/5.0'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $code !== 200 || !$body) continue;

        $d = json_decode($body, true);
        if (empty($d['data'])) continue;

        // Fila más reciente: [ "2024-11", valor_col1, valor_col2, ... ]
        // Buscar la primera columna numérica > 0
        $latest = $d['data'][0] ?? [];
        $prev   = $d['data'][1] ?? [];
        $value  = null;
        $prevV  = null;

        for ($col = 1; $col < count($latest); $col++) {
            if (isset($latest[$col]) && is_numeric($latest[$col]) && $latest[$col] > 0) {
                $value = floatval($latest[$col]);
                $prevV = isset($prev[$col]) ? floatval($prev[$col]) : null;
                break;
            }
        }

        if (!$value) continue;

        return [
            'series_id'     => $seriesId,
            'date'          => $latest[0] ?? '',
            'value'         => $value,
            'prev_value'    => $prevV,
            'variation_pct' => ($prevV && $prevV > 0) ? round(($value - $prevV) / $prevV * 100, 2) : null,
        ];
    }

    // Último recurso: intentar endpoint alternativo con múltiples series a la vez
    $multiUrl = 'https://apis.datos.gob.ar/series/api/series/?ids=icc_1.1_total_total,icc_1.1_materiales_total,icc_1.1_manodeobra_total&limit=3&sort=desc';
    $ch = curl_init($multiUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
        CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_SSL_VERIFYPEER=>true]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $body) {
        $d = json_decode($body, true);
        $row = $d['data'][0] ?? [];
        // Columna 2 = materiales (si está)
        for ($col = 1; $col < count($row); $col++) {
            if (isset($row[$col]) && $row[$col] > 0) {
                return [
                    'series_id'     => 'icc_1.1_materiales_total',
                    'date'          => $row[0] ?? '',
                    'value'         => floatval($row[$col]),
                    'prev_value'    => null,
                    'variation_pct' => null,
                    'note'          => 'columna '.$col,
                ];
            }
        }
    }

    return ['error' => 'No se pudo obtener el ICC de datos.gob.ar. Verificá conectividad al servidor.'];
}

// ── Ejecutar fetch ICC primero (antes de tocar la BD) ────────
$icc = fetchICC();

if (!empty($icc['error'])) {
    out(['success'=>false, 'error'=>$icc['error'],
         'tip'   =>'Probá abrir: https://apis.datos.gob.ar/series/api/series/?ids=icc_1.1_materiales_total&limit=2']);
}

// ── 2. Conexión BD ────────────────────────────────────────────
$db_cfg = $cfg['db'] ?? [];
try {
    $db = new PDO(
        'mysql:host='.($db_cfg['host']??'localhost').
        ';dbname='.($db_cfg['name']??'').
        ';charset='.($db_cfg['charset']??'utf8mb4'),
        $db_cfg['user'] ?? '',
        $db_cfg['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\Throwable $e) {
    out(['success'=>false,'error'=>'DB: '.$e->getMessage()]);
}

// ── 3. Asegurar tabla tasador_settings ───────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS tasador_settings (
        meta_key   VARCHAR(80) PRIMARY KEY,
        meta_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch(\Throwable $e) {}

function getSetting(PDO $db, string $key): ?string {
    try {
        $r = $db->prepare("SELECT meta_value FROM tasador_settings WHERE meta_key=? LIMIT 1");
        $r->execute([$key]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['meta_value'] : null;
    } catch(\Throwable $e) { return null; }
}

function setSetting(PDO $db, string $key, string $value): void {
    try {
        $db->prepare("INSERT INTO tasador_settings (meta_key,meta_value) VALUES (?,?)
            ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)")->execute([$key,$value]);
    } catch(\Throwable $e) {}
}

// ── 4. ICC base ───────────────────────────────────────────────
$iccBaseVal  = getSetting($db, 'icc_base_value');
$iccBaseDate = getSetting($db, 'icc_base_date');

// Primera vez: guardar como baseline
if (!$iccBaseVal) {
    if (!$preview) {
        setSetting($db, 'icc_base_value', (string)$icc['value']);
        setSetting($db, 'icc_base_date',  (string)$icc['date']);
    }
    out([
        'success' => true,
        'action'  => 'baseline_set',
        'message' => 'ICC base registrado por primera vez. Los precios actuales quedan como referencia.',
        'icc'     => $icc,
        'ratio'   => 1.0,
        'preview' => $preview,
    ]);
}

// ── 5. Calcular ratio ─────────────────────────────────────────
$ratio    = round($icc['value'] / floatval($iccBaseVal), 6);
$ratioPct = round(($ratio - 1) * 100, 2);

// ── 6. Vista previa materiales ────────────────────────────────
$materials = [];
try {
    $materials = $db->query(
        "SELECT id, name, price_ars FROM construction_materials WHERE price_ars > 0 ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch(\Throwable $e) {}

$previewRows = array_map(fn($m) => [
    'id'        => (int)$m['id'],
    'name'      => $m['name'],
    'old_ars'   => (int)$m['price_ars'],
    'new_ars'   => (int)round($m['price_ars'] * $ratio),
    'delta_pct' => round(($ratio - 1) * 100, 1),
], $materials);

if ($preview) {
    out([
        'success'         => true,
        'action'          => 'preview',
        'icc_current'     => $icc,
        'icc_base'        => ['value' => floatval($iccBaseVal), 'date' => $iccBaseDate],
        'ratio'           => $ratio,
        'ratio_pct'       => $ratioPct,
        'materials_count' => count($previewRows),
        'preview'         => array_slice($previewRows, 0, 10),
    ]);
}

// ── 7. Aplicar actualización ──────────────────────────────────
$qualityFactors = ['economica'=>0.72,'estandar'=>1.0,'calidad'=>1.40,'premium'=>2.05];
$usdRate        = floatval($cfg['usd_rate'] ?? $cfg['ars_usd_rate'] ?? 1400);

$db->beginTransaction();
try {
    // Actualizar precios materiales
    $stmt = $db->prepare("UPDATE construction_materials SET price_ars=ROUND(price_ars*?) WHERE price_ars>0");
    $stmt->execute([$ratio]);
    $matUpdated = $stmt->rowCount();

    // Recalcular zone_costs
    $baseArs = floatval($db->query(
        "SELECT SUM(price_ars * qty_per_m2) FROM construction_materials WHERE qty_per_m2>0 AND active=1"
    )->fetchColumn() ?: 0);

    $zonesUpdated = 0;
    if ($baseArs > 0) {
        $zones = $db->query("SELECT id, city, zone, quality FROM construction_zone_costs")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($zones as $z) {
            $factor = $qualityFactors[$z['quality']] ?? 1.0;
            $newUsd = round(($baseArs / $usdRate) * $factor, 2);
            $db->prepare("UPDATE construction_zone_costs SET cost_usd_m2=? WHERE id=?")->execute([$newUsd, $z['id']]);
            $zonesUpdated++;
        }
    }

    // Guardar nuevo ICC base
    setSetting($db, 'icc_base_value', (string)$icc['value']);
    setSetting($db, 'icc_base_date',  (string)$icc['date']);

    $db->commit();

    out([
        'success'           => true,
        'action'            => 'updated',
        'icc_current'       => $icc,
        'icc_base_was'      => ['value'=>floatval($iccBaseVal),'date'=>$iccBaseDate],
        'ratio'             => $ratio,
        'ratio_pct'         => $ratioPct,
        'materials_updated' => $matUpdated,
        'zones_updated'     => $zonesUpdated,
        'message'           => ($ratioPct >= 0 ? '+' : '').$ratioPct.'% aplicado según ICC INDEC ('.$icc['date'].')',
    ]);

} catch (\Throwable $e) {
    $db->rollBack();
    out(['success'=>false,'error'=>$e->getMessage()]);
}
