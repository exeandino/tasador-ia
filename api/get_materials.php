<?php
/**
 * TasadorIA — api/get_materials.php
 * Devuelve la lista completa de materiales con precios, variación y query ML.
 * GET → { materials: [...], usd_rate, updated_at_max }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ta_admin']) && !isset($_SESSION['bim_ok'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'No autorizado']); exit;
}

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];

try {
    $pdo = new PDO(
        'mysql:host='.($cfg['db']['host']??'localhost').';dbname='.($cfg['db']['name']??'').';charset=utf8mb4',
        $cfg['db']['user']??'', $cfg['db']['pass']??'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );

    // Añadir columnas nuevas si no existen (silencioso)
    foreach ([
        "ALTER TABLE construction_materials ADD COLUMN source VARCHAR(40) DEFAULT 'manual'",
        "ALTER TABLE construction_materials ADD COLUMN ml_query VARCHAR(200) DEFAULT NULL",
        "ALTER TABLE construction_materials ADD COLUMN price_usd_prev DECIMAL(10,4) DEFAULT NULL",
    ] as $sql) { try { $pdo->exec($sql); } catch(\Throwable $e) {} }

    $rows = $pdo->query("
        SELECT id, category, material, unit,
               price_ars, price_usd, price_usd_prev,
               qty_per_m2, source, ml_query,
               updated_at, notes, active
        FROM construction_materials
        WHERE region=''
        ORDER BY category, material
    ")->fetchAll();

    $usdRate   = floatval($cfg['ars_usd_rate'] ?? $cfg['usd_rate'] ?? 1450);
    $updatedAt = '';

    $materials = array_map(function($r) use ($usdRate, &$updatedAt) {
        if ($r['updated_at'] > $updatedAt) $updatedAt = $r['updated_at'];
        $prevUsd = floatval($r['price_usd_prev'] ?? 0);
        $curUsd  = floatval($r['price_usd']      ?? 0);
        $delta   = ($prevUsd > 0 && $curUsd > 0) ? round(($curUsd - $prevUsd) / $prevUsd * 100, 1) : null;

        // Construir URL de búsqueda en ML
        $q      = $r['ml_query'] ?? '';
        $mlUrl  = $q ? 'https://listado.mercadolibre.com.ar/'.urlencode(str_replace(' ','-',$q)).'_NoIndex_True' : '';

        return [
            'id'          => (int)$r['id'],
            'category'    => $r['category'],
            'material'    => $r['material'],
            'unit'        => $r['unit'],
            'price_ars'   => (float)$r['price_ars'],
            'price_usd'   => $curUsd,
            'price_usd_prev' => $prevUsd ?: null,
            'delta_pct'   => $delta,
            'qty_per_m2'  => (float)$r['qty_per_m2'],
            'source'      => $r['source'] ?? 'manual',
            'ml_query'    => $q,
            'ml_url'      => $mlUrl,
            'updated_at'  => $r['updated_at'],
            'active'      => (bool)$r['active'],
        ];
    }, $rows);

    echo json_encode([
        'success'       => true,
        'materials'     => $materials,
        'usd_rate'      => $usdRate,
        'updated_at'    => $updatedAt,
        'total'         => count($materials),
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
