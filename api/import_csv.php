<?php
/**
 * TasadorIA — api/import_csv.php
 * Importa propiedades desde un CSV (exportado desde Excel).
 *
 * POST multipart/form-data:
 *   file  = archivo .csv
 *
 * Columnas esperadas (orden libre, se detectan por nombre de encabezado):
 *   titulo, ciudad, zona, tipo, operacion, precio, moneda,
 *   superficie_cubierta, superficie_total, dormitorios, banos,
 *   cocheras, direccion, latitud, longitud
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['ta_admin'])) {
    out(['success' => false, 'error' => 'No autorizado'], 403);
}

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];

try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\Throwable $e) {
    out(['success' => false, 'error' => 'DB: ' . $e->getMessage()]);
}

// ── Recibir archivo ───────────────────────────────────────────
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    out(['success' => false, 'error' => 'No se recibió archivo CSV']);
}

$tmpFile  = $_FILES['file']['tmp_name'];
$origName = strtolower($_FILES['file']['name'] ?? '');

if (!preg_match('/\.csv$/i', $origName)) {
    out(['success' => false, 'error' => 'El archivo debe ser .csv (exportá desde Excel: Archivo → Guardar como → CSV separado por comas)']);
}

// ── Detectar delimitador y encoding ──────────────────────────
$sample = file_get_contents($tmpFile, false, null, 0, 2048);

// BOM UTF-8
if (str_starts_with($sample, "\xEF\xBB\xBF")) {
    $sample = substr($sample, 3);
    $content = substr(file_get_contents($tmpFile), 3);
    file_put_contents($tmpFile, $content);
}

// Detectar encoding (Windows-1252 vs UTF-8)
if (!mb_detect_encoding($sample, 'UTF-8', true)) {
    $converted = mb_convert_encoding(file_get_contents($tmpFile), 'UTF-8', 'Windows-1252');
    file_put_contents($tmpFile, $converted);
}

// Detectar separador (,  ;  \t)
$comma     = substr_count($sample, ',');
$semicolon = substr_count($sample, ';');
$tab       = substr_count($sample, "\t");
$sep = ',';
if ($semicolon > $comma && $semicolon > $tab) $sep = ';';
elseif ($tab > $comma && $tab > $semicolon)  $sep = "\t";

// ── Leer CSV ──────────────────────────────────────────────────
$fh = fopen($tmpFile, 'r');
if (!$fh) out(['success' => false, 'error' => 'No se pudo abrir el archivo']);

// Primera línea = encabezados
$rawHeaders = fgetcsv($fh, 0, $sep);
if (!$rawHeaders) {
    fclose($fh);
    out(['success' => false, 'error' => 'El archivo está vacío o no tiene encabezados']);
}

// Normalizar nombres de columnas → slug limpio
$colMap = [];
foreach ($rawHeaders as $i => $h) {
    $slug = strtolower(trim($h));
    $slug = preg_replace('/[^a-z0-9_]/', '_', $slug);
    $slug = preg_replace('/_+/', '_', trim($slug, '_'));
    $colMap[$slug] = $i;
}

// Aliases comunes (por si exportan con otros nombres)
$aliases = [
    'titulo'              => ['titulo', 'title', 'nombre', 'name', 'propiedad'],
    'ciudad'              => ['ciudad', 'city', 'localidad'],
    'zona'                => ['zona', 'barrio', 'zone', 'neighborhood', 'zona_barrio'],
    'tipo'                => ['tipo', 'type', 'property_type', 'tipo_propiedad'],
    'operacion'           => ['operacion', 'operation', 'modalidad', 'status', 'property_status'],
    'precio'              => ['precio', 'price', 'valor', 'fave_property_price'],
    'moneda'              => ['moneda', 'currency', 'moneda_precio', 'fave_currency'],
    'superficie_cubierta' => ['superficie_cubierta', 'sup_cubierta', 'cubierta', 'superficie', 'size', 'fave_property_size', 'm2_cubiertos', 'm2'],
    'superficie_total'    => ['superficie_total', 'sup_total', 'total', 'land', 'fave_property_land', 'm2_totales', 'terreno'],
    'dormitorios'         => ['dormitorios', 'bedrooms', 'habitaciones', 'camas', 'fave_property_bedrooms', 'ambientes'],
    'banos'               => ['banos', 'ba_os', 'bathrooms', 'fave_property_bathrooms'],
    'cocheras'            => ['cocheras', 'garage', 'garages', 'fave_property_garage'],
    'direccion'           => ['direccion', 'direcci_n', 'address', 'fave_property_map_address'],
    'latitud'             => ['latitud', 'lat', 'latitude', 'houzez_geolocation_lat'],
    'longitud'            => ['longitud', 'lng', 'lon', 'longitude', 'houzez_geolocation_long'],
];

function findCol(array $colMap, array $candidates): ?int {
    foreach ($candidates as $name) {
        if (isset($colMap[$name])) return $colMap[$name];
    }
    return null;
}

$cols = [];
foreach ($aliases as $field => $candidates) {
    $cols[$field] = findCol($colMap, $candidates);
}

// Verificar columnas obligatorias
$required = ['precio', 'superficie_cubierta'];
$missing  = [];
foreach ($required as $r) {
    if ($cols[$r] === null) $missing[] = $r;
}
if ($missing) {
    fclose($fh);
    out(['success' => false, 'error' => 'Columnas obligatorias no encontradas: ' . implode(', ', $missing) .
         '. Encabezados detectados: ' . implode(', ', array_keys($colMap))]);
}

// ── Mapeo de tipos ────────────────────────────────────────────
function mapType(string $t): string {
    $t = strtolower(trim($t));
    $map = [
        'departamento' => 'departamento', 'depto'      => 'departamento', 'dpto'  => 'departamento',
        'apartment'    => 'departamento', 'flat'       => 'departamento',
        'casa'         => 'casa',         'house'      => 'casa',
        'ph'           => 'ph',           'penthouse'  => 'ph',
        'local'        => 'local',        'shop'       => 'local',       'comercial' => 'local',
        'oficina'      => 'oficina',      'office'     => 'oficina',
        'terreno'      => 'terreno',      'lote'       => 'terreno',     'land'  => 'terreno',
        'cochera'      => 'cochera',      'garage'     => 'cochera',
        'galpon'       => 'galpon',       'galpón'     => 'galpon',      'warehouse' => 'galpon',
    ];
    return $map[$t] ?? 'departamento';
}

// ── Insertar en BD ────────────────────────────────────────────
$stmt = $pdo->prepare("
    INSERT INTO market_listings
        (url, title, address, city, province, zone, property_type, operation,
         covered_area, total_area, bedrooms, bathrooms, garages,
         price, currency, price_per_m2, lat, lng, source, active, scraped_at)
    VALUES
        (:url,:title,:addr,:city,:prov,:zone,:type,:op,
         :cov,:tot,:beds,:baths,:gar,
         :price,:cur,:ppm2,:lat,:lng,'csv_import',1,NOW())
    ON DUPLICATE KEY UPDATE
        price=VALUES(price), covered_area=VALUES(covered_area),
        price_per_m2=VALUES(price_per_m2), active=1, scraped_at=NOW()
");

$usdRate  = (float)($cfg['ars_usd_rate'] ?? 1400);
$inserted = 0;
$skipped  = 0;
$errors   = [];
$lineNum  = 1;

function getV(array $row, ?int $idx, $default = ''): string {
    if ($idx === null || !isset($row[$idx])) return (string)$default;
    return trim((string)$row[$idx]);
}

while (($row = fgetcsv($fh, 0, $sep)) !== false) {
    $lineNum++;

    // Saltar filas vacías o de notas (fila 2 del template = notas de ayuda)
    $precio_raw = getV($row, $cols['precio']);
    if ($precio_raw === '' || !is_numeric(str_replace(['.', ',', ' '], ['', '.', ''], $precio_raw))) {
        $skipped++;
        continue;
    }

    $precio = (float) str_replace(['.', ',', ' '], ['', '.', ''], $precio_raw);
    $cov    = (float) str_replace(['.', ',', ' '], ['', '.', ''], getV($row, $cols['superficie_cubierta']));
    $tot    = (float) str_replace(['.', ',', ' '], ['', '.', ''], getV($row, $cols['superficie_total']));

    if ($precio <= 0 || $cov <= 0) { $skipped++; continue; }
    if ($tot <= 0) $tot = $cov;

    $moneda = strtoupper(getV($row, $cols['moneda'], 'USD'));
    $priceUsd = ($moneda === 'ARS' && $usdRate > 0) ? $precio / $usdRate : $precio;
    $ppm2     = $cov > 0 ? round($priceUsd / $cov, 2) : 0;

    // Filtrar valores irreales de ppm2
    if ($ppm2 < 50 || $ppm2 > 30000) { $skipped++; continue; }

    $tipo = mapType(getV($row, $cols['tipo'], 'departamento'));
    $op   = str_contains(strtolower(getV($row, $cols['operacion'], 'venta')), 'alquil') ? 'alquiler' : 'venta';

    $title = getV($row, $cols['titulo'], 'Sin título');
    $city  = getV($row, $cols['ciudad'], 'Sin ciudad');
    $zone  = getV($row, $cols['zona'], $city);
    $addr  = getV($row, $cols['direccion'], "$zone, $city, Argentina");

    $lat = ($v = getV($row, $cols['latitud'])) ? (float)$v : null;
    $lng = ($v = getV($row, $cols['longitud'])) ? (float)$v : null;

    $beds  = ($v = getV($row, $cols['dormitorios'])) !== '' ? (int)$v : null;
    $baths = ($v = getV($row, $cols['banos']))       !== '' ? (int)$v : null;
    $gar   = ($v = getV($row, $cols['cocheras']))    !== '' ? (int)$v : null;

    $url = 'csv_' . md5($title . $addr . $precio);

    try {
        $stmt->execute([
            ':url'   => $url,   ':title' => $title,
            ':addr'  => $addr,  ':city'  => $city,  ':prov'  => $city,
            ':zone'  => $zone,  ':type'  => $tipo,  ':op'    => $op,
            ':cov'   => $cov,   ':tot'   => $tot,
            ':beds'  => $beds,  ':baths' => $baths, ':gar'   => $gar,
            ':price' => $priceUsd, ':cur' => 'USD',
            ':ppm2'  => $ppm2,
            ':lat'   => $lat,   ':lng'   => $lng,
        ]);
        $inserted++;
    } catch (\Throwable $e) {
        $errors[] = "Línea $lineNum: " . $e->getMessage();
    }
}
fclose($fh);

$msg = "Se importaron $inserted propiedades.";
if ($skipped)        $msg .= " ($skipped filas vacías o inválidas omitidas)";
if (count($errors))  $msg .= " " . count($errors) . " errores.";

out([
    'success'  => true,
    'inserted' => $inserted,
    'skipped'  => $skipped,
    'errors'   => array_slice($errors, 0, 5),
    'msg'      => $msg,
]);
