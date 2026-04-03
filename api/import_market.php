<?php
// tasador/api/import_market.php
// Importar datos de scraping propio en formato CSV o JSON
// Acceso solo desde admin: requiere el mismo password del admin.php

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Key');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }

function jsonOut(array $d): void { ob_end_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); exit; }

$cfg = require_once __DIR__ . '/../config/settings.php';

// Auth simple
$adminKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? $_GET['key'] ?? '';
if ($adminKey !== ($cfg['admin_pass'] ?? 'YOUR_ADMIN_PASSWORD')) {
    jsonOut(['error' => 'Unauthorized', 'hint' => 'Enviar header X-Admin-Key o ?key=']);
}

// GET: estadísticas
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
            $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stats = $pdo->query("SELECT source, city, COUNT(*) as count, ROUND(AVG(price_per_m2),0) as avg_ppm2, MAX(scraped_at) as last_update FROM market_listings WHERE active=1 GROUP BY source, city ORDER BY city, source")->fetchAll(PDO::FETCH_ASSOC);
        $total = $pdo->query("SELECT COUNT(*) FROM market_listings WHERE active=1")->fetchColumn();
        ob_end_clean();
        echo json_encode(['total_listings' => $total, 'by_source_city' => $stats], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    } catch (\Throwable $e) {
        jsonOut(['error' => $e->getMessage()]);
    }
}

// POST: importar datos
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Detectar formato: JSON body o multipart con archivo CSV
if (!$data && !empty($_FILES['file'])) {
    // CSV upload
    $file    = $_FILES['file']['tmp_name'];
    $content = file_get_contents($file);
    $data    = parseCsv($content);
}

if (!$data || empty($data['listings'])) {
    // Intentar interpretar como array directo de listings
    $arr = json_decode($raw, true);
    if (is_array($arr) && isset($arr[0])) {
        $data = ['listings' => $arr, 'source' => 'manual'];
    } else {
        jsonOut(['error' => 'Payload inválido. Enviar JSON con {listings:[...]} o CSV con header.']);
    }
}

// ── Conectar BD ────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\Throwable $e) {
    jsonOut(['error' => 'DB: ' . $e->getMessage()]);
}

$source   = $data['source'] ?? 'csv';
$listings = $data['listings'];
$arsRate  = (float)($data['ars_usd_rate'] ?? $cfg['ars_usd_rate'] ?? 1450);

$inserted = 0;
$updated  = 0;
$errors   = [];
$skipped  = 0;

$stmt = $pdo->prepare("
    INSERT INTO market_listings
        (source, external_id, url, address, city, province, zone, lat, lng,
         property_type, operation, covered_area, total_area,
         bedrooms, bathrooms, garages, age_years, floor,
         price, currency, price_usd, price_per_m2, expenses,
         title, active, scraped_at, created_at)
    VALUES
        (:source, :external_id, :url, :address, :city, :province, :zone, :lat, :lng,
         :property_type, :operation, :covered_area, :total_area,
         :bedrooms, :bathrooms, :garages, :age_years, :floor,
         :price, :currency, :price_usd, :price_per_m2, :expenses,
         :title, 1, :scraped_at, NOW())
    ON DUPLICATE KEY UPDATE
        price       = VALUES(price),
        currency    = VALUES(currency),
        price_usd   = VALUES(price_usd),
        price_per_m2= VALUES(price_per_m2),
        active      = 1,
        scraped_at  = VALUES(scraped_at),
        updated_at  = NOW()
");

foreach ($listings as $i => $row) {
    try {
        $price    = (float)($row['price']    ?? $row['precio']    ?? 0);
        $currency = strtoupper($row['currency'] ?? $row['moneda'] ?? 'USD');
        $area     = (float)($row['covered_area'] ?? $row['superficie'] ?? $row['m2'] ?? 0);

        if ($price <= 0 || $area <= 0) { $skipped++; continue; }

        // Normalizar moneda → USD
        $priceUSD = match($currency) {
            'ARS'  => $price / $arsRate,
            'USD'  => $price,
            default=> $price,
        };

        $ppm2  = $area > 0 ? round($priceUSD / $area, 2) : null;
        $extId = ($row['id'] ?? $row['external_id'] ?? null)
               ? $source . '_' . ($row['id'] ?? $row['external_id'])
               : null;

        // Normalizar tipo
        $type = strtolower($row['property_type'] ?? $row['tipo'] ?? 'departamento');
        $type = match(true) {
            str_contains($type, 'depto') || str_contains($type, 'dpto') || str_contains($type, 'apartamento') => 'departamento',
            str_contains($type, 'casa') => 'casa',
            str_contains($type, 'ph')   => 'ph',
            str_contains($type, 'terreno') || str_contains($type, 'lote') => 'terreno',
            str_contains($type, 'local') || str_contains($type, 'comercial') => 'local',
            str_contains($type, 'oficina') => 'oficina',
            default => $type,
        };

        $op = strtolower($row['operation'] ?? $row['operacion'] ?? 'venta');
        $op = str_contains($op, 'alquil') ? 'alquiler' : 'venta';

        $stmt->execute([
            ':source'       => $source,
            ':external_id'  => $extId,
            ':url'          => $row['url'] ?? null,
            ':address'      => $row['address'] ?? $row['direccion'] ?? null,
            ':city'         => $row['city']    ?? $row['ciudad']    ?? null,
            ':province'     => $row['province']?? $row['provincia'] ?? null,
            ':zone'         => $row['zone']    ?? $row['barrio']    ?? $row['zona'] ?? null,
            ':lat'          => $row['lat']     ?? $row['latitud']   ?? null,
            ':lng'          => $row['lng']     ?? $row['longitud']  ?? null,
            ':property_type'=> $type,
            ':operation'    => $op,
            ':covered_area' => $area,
            ':total_area'   => $row['total_area'] ?? $row['sup_total'] ?? $area,
            ':bedrooms'     => $row['bedrooms'] ?? $row['dormitorios'] ?? $row['ambientes'] ?? null,
            ':bathrooms'    => $row['bathrooms']?? $row['banos']    ?? null,
            ':garages'      => $row['garages']  ?? $row['cocheras'] ?? null,
            ':age_years'    => $row['age_years']?? $row['antiguedad']?? null,
            ':floor'        => $row['floor']    ?? $row['piso']     ?? null,
            ':price'        => $price,
            ':currency'     => $currency,
            ':price_usd'    => round($priceUSD, 2),
            ':price_per_m2' => $ppm2,
            ':expenses'     => $row['expenses'] ?? $row['expensas'] ?? null,
            ':title'        => $row['title']    ?? $row['titulo']   ?? null,
            ':scraped_at'   => $row['scraped_at'] ?? $row['fecha'] ?? date('Y-m-d H:i:s'),
        ]);

        $stmt->rowCount() > 0 ? $inserted++ : $updated++;

    } catch (\Throwable $e) {
        $errors[] = "Fila $i: " . $e->getMessage();
        if (count($errors) >= 20) break; // cortar si hay demasiados errores
    }
}

jsonOut([
    'success'  => true,
    'inserted' => $inserted,
    'updated'  => $updated,
    'skipped'  => $skipped,
    'errors'   => $errors,
    'total_procesados' => $inserted + $updated + $skipped,
    'hint' => $inserted + $updated > 0
        ? "Datos importados correctamente. El motor de tasación los usará automáticamente."
        : "No se importó ningún dato. Revisar formato.",
]);

function parseCsv(string $content): array {
    $lines    = explode("\n", trim($content));
    $headers  = str_getcsv(array_shift($lines));
    $listings = [];
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $values   = str_getcsv($line);
        $listings[] = array_combine($headers, array_pad($values, count($headers), null));
    }
    return ['source' => 'csv', 'listings' => $listings];
}
