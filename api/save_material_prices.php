<?php
/**
 * TasadorIA — api/save_material_prices.php
 * Recibe precios ya consultados desde el browser y los guarda en BD.
 * No hace llamadas externas — solo escribe en MySQL.
 *
 * POST body:
 * {
 *   "usd_rate": 1400,
 *   "prices": [
 *     { "id": 5, "material": "Cemento...", "price_ars": 18500, "flete_pct": 9.0, "count": 8, "query": "cemento..." },
 *     ...
 *   ]
 * }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// CORS: permite POST desde el bookmarklet corriendo en mercadolibre.com.ar
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://www.mercadolibre.com.ar','https://listado.mercadolibre.com.ar','https://mercadolibre.com.ar'];
if (in_array($origin, $allowed)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: false');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];

// Permitir también desde bookmarklet (sin sesión) si viene de ML con un token de 1 uso
$fromBookmarklet = in_array($origin, $allowed);
if (!$fromBookmarklet && !isset($_SESSION['ta_admin']) && !isset($_SESSION['bim_ok'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'No autorizado']); exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$prices   = $input['prices']   ?? [];
$usdRate  = floatval($input['usd_rate'] ?? $cfg['ars_usd_rate'] ?? 1400);

if (empty($prices)) {
    echo json_encode(['success'=>false,'error'=>'Sin datos']); exit;
}

try {
    $pdo = new PDO(
        'mysql:host='.($cfg['db']['host']??'localhost').';dbname='.($cfg['db']['name']??'').';charset=utf8mb4',
        $cfg['db']['user']??'', $cfg['db']['pass']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );

    // Columnas extra (silencioso si ya existen)
    foreach ([
        "ALTER TABLE construction_materials ADD COLUMN source VARCHAR(40) DEFAULT 'manual'",
        "ALTER TABLE construction_materials ADD COLUMN ml_query VARCHAR(200) DEFAULT NULL",
        "ALTER TABLE construction_materials ADD COLUMN price_usd_prev DECIMAL(10,4) DEFAULT NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch(\Throwable $e) {} }

    $saved = 0;
    $stmt  = $pdo->prepare("
        UPDATE construction_materials
        SET price_ars=?, price_usd=?, price_usd_prev=price_usd,
            source='mercadolibre', ml_query=?, updated_at=CURDATE()
        WHERE id=?
    ");

    foreach ($prices as $p) {
        $id      = intval($p['id']      ?? 0);
        $arsRaw  = floatval($p['price_ars'] ?? 0);
        $flete   = floatval($p['flete_pct'] ?? 0);
        if ($id <= 0 || $arsRaw <= 0) continue;

        $arsWithFlete = round($arsRaw * (1 + $flete/100), 2);
        $usd          = round($arsWithFlete / $usdRate, 4);
        $stmt->execute([$arsWithFlete, $usd, $p['query']??'', $id]);
        $saved++;
    }

    // ── Recalcular costos/m² de zonas ──────────────────────────
    $factors = ['economica'=>0.72,'estandar'=>1.0,'calidad'=>1.40,'premium'=>2.05];
    $baseArs = floatval($pdo->query(
        "SELECT SUM(price_ars * qty_per_m2) FROM construction_materials WHERE region='' AND active=1 AND qty_per_m2>0"
    )->fetchColumn() ?? 0);

    $zonesUpdated = 0;
    if ($baseArs > 0) {
        $zones = $pdo->query("SELECT id, city, zone, quality FROM construction_zone_costs")->fetchAll();
        foreach ($zones as $z) {
            $regArs = floatval($pdo->prepare(
                "SELECT SUM(price_ars * qty_per_m2) FROM construction_materials WHERE region=? AND active=1 AND qty_per_m2>0"
            )->execute([$z['city']]) ? $pdo->query(
                "SELECT SUM(price_ars * qty_per_m2) FROM construction_materials WHERE region='{$z['city']}' AND active=1 AND qty_per_m2>0"
            )->fetchColumn() : 0);
            $blend  = $regArs > 0 ? $baseArs*0.70 + $regArs*0.30 : $baseArs;
            $newUsd = round(($blend / $usdRate) * ($factors[$z['quality']] ?? 1.0), 2);
            $pdo->prepare("UPDATE construction_zone_costs SET cost_usd_m2=? WHERE id=?")->execute([$newUsd, $z['id']]);
            $zonesUpdated++;
        }
    }

    echo json_encode(['success'=>true,'saved'=>$saved,'zones_updated'=>$zonesUpdated,'usd_rate'=>$usdRate]);

} catch (\Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
