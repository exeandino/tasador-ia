<?php
// tasador/api/search_properties.php — Buscador de propiedades en market_listings

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }

function jOut(array $d): void { ob_end_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$cfg = require_once __DIR__ . '/../config/settings.php';
$arsRate = (int)($cfg['ars_usd_rate'] ?? 1450);

try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\Throwable $e) { jOut(['error' => 'BD: ' . $e->getMessage()]); }

$raw = json_decode(file_get_contents('php://input'), true) ?? [];
// Aceptar GET también para test
if (empty($raw)) $raw = $_GET;

// ── Filtros ───────────────────────────────────────────────────────────────────
$ciudad      = trim($raw['ciudad']       ?? '');
$zona        = trim($raw['zona']         ?? '');
$tipo        = trim($raw['tipo']         ?? '');
$operacion   = trim($raw['operacion']    ?? '');
$precioMin   = (float)($raw['precio_min'] ?? 0);
$precioMax   = (float)($raw['precio_max'] ?? 0);
$areaMin     = (float)($raw['area_min']  ?? 0);
$areaMax     = (float)($raw['area_max']  ?? 0);
$dormMin     = (int)($raw['dorm_min']    ?? 0);
$dormMax     = (int)($raw['dorm_max']    ?? 0);
$ambMin      = (int)($raw['amb_min']     ?? 0);
$banos       = (int)($raw['banos']       ?? 0);
$cochera     = $raw['cochera']           ?? '';  // '1' = con cochera
$source      = trim($raw['source']       ?? '');
$texto       = trim($raw['texto']        ?? '');  // búsqueda libre
$orderBy     = $raw['order']             ?? 'scraped_at';
$limit       = min(100, max(10, (int)($raw['limit'] ?? 30)));
$offset      = max(0, (int)($raw['offset'] ?? 0));

// ── Construir WHERE ───────────────────────────────────────────────────────────
$where  = ['active = 1'];
$params = [];

if ($ciudad)    { $where[] = '(city LIKE :ciudad OR province LIKE :ciudad2)';   $params[':ciudad'] = "%$ciudad%"; $params[':ciudad2'] = "%$ciudad%"; }
if ($zona)      { $where[] = '(zone LIKE :zona OR address LIKE :zona2)';        $params[':zona']   = "%$zona%";   $params[':zona2']   = "%$zona%"; }
if ($tipo)      { $where[] = 'property_type = :tipo';                           $params[':tipo']   = $tipo; }
if ($operacion) { $where[] = 'operation = :op';                                  $params[':op']     = $operacion; }
if ($precioMin) { $where[] = 'price_usd >= :pmin';                              $params[':pmin']   = $precioMin; }
if ($precioMax) { $where[] = 'price_usd <= :pmax';                              $params[':pmax']   = $precioMax; }
if ($areaMin)   { $where[] = 'covered_area >= :amin';                           $params[':amin']   = $areaMin; }
if ($areaMax)   { $where[] = 'covered_area <= :amax';                           $params[':amax']   = $areaMax; }
if ($dormMin)   { $where[] = 'bedrooms >= :dmin';                               $params[':dmin']   = $dormMin; }
if ($dormMax)   { $where[] = 'bedrooms <= :dmax';                               $params[':dmax']   = $dormMax; }
if ($ambMin)    { $where[] = 'bedrooms >= :ambmin OR covered_area >= :ambarea'; $params[':ambmin'] = max(1,$ambMin-1); $params[':ambarea'] = $ambMin*25; }
if ($banos)     { $where[] = 'bathrooms >= :banos';                             $params[':banos']  = $banos; }
if ($cochera === '1') { $where[] = 'garages >= 1'; }
if ($source)    { $where[] = 'source = :src';                                   $params[':src']    = $source; }
if ($texto)     { $where[] = '(title LIKE :txt OR address LIKE :txt2 OR zone LIKE :txt3)';
                  $params[':txt'] = "%$texto%"; $params[':txt2'] = "%$texto%"; $params[':txt3'] = "%$texto%"; }

// Ordenamiento seguro
$allowedOrders = ['price_usd','price_per_m2','covered_area','scraped_at','bedrooms'];
$order = in_array($orderBy, $allowedOrders) ? $orderBy : 'scraped_at';
$orderDir = ($raw['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── Count total ───────────────────────────────────────────────────────────────
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM market_listings $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// ── Query principal ───────────────────────────────────────────────────────────
$sql = "SELECT id, source, url, title, address, city, province, zone,
               property_type, operation,
               covered_area, total_area, bedrooms, bathrooms, garages,
               price, currency, price_usd, price_per_m2, expenses,
               lat, lng, scraped_at
        FROM market_listings
        $whereSQL
        ORDER BY $order $orderDir
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Enriquecer resultados ─────────────────────────────────────────────────────
$results = [];
foreach ($rows as $r) {
    $pUSD  = (float)($r['price_usd']    ?? 0);
    $ppm2  = (float)($r['price_per_m2'] ?? 0);
    $area  = (float)($r['covered_area'] ?? 0);

    // Recalcular ppm2 si falta
    if (!$ppm2 && $pUSD > 0 && $area > 0) $ppm2 = round($pUSD / $area, 0);

    $results[] = [
        'id'            => (int)$r['id'],
        'source'        => $r['source'],
        'url'           => $r['url'],
        'title'         => $r['title']    ?: ($r['address'] ?: 'Sin título'),
        'address'       => $r['address'],
        'city'          => $r['city'],
        'zone'          => $r['zone'],
        'property_type' => $r['property_type'],
        'operation'     => $r['operation'],
        'covered_area'  => $area > 0 ? $area : null,
        'total_area'    => (float)($r['total_area'] ?? 0) ?: null,
        'bedrooms'      => $r['bedrooms']  ? (int)$r['bedrooms']  : null,
        'bathrooms'     => $r['bathrooms'] ? (int)$r['bathrooms'] : null,
        'garages'       => $r['garages']   ? (int)$r['garages']   : null,
        'price_usd'     => $pUSD > 0 ? (int)$pUSD : null,
        'price_ars'     => $pUSD > 0 ? (int)($pUSD * $arsRate) : null,
        'price_per_m2'  => $ppm2 > 0 ? (int)$ppm2 : null,
        'expenses_ars'  => (float)($r['expenses'] ?? 0) ?: null,
        'lat'           => $r['lat']  ? (float)$r['lat']  : null,
        'lng'           => $r['lng']  ? (float)$r['lng']  : null,
        'scraped_at'    => substr($r['scraped_at'] ?? '', 0, 10),
    ];
}

// ── Stats de los resultados ───────────────────────────────────────────────────
$statsSQL = "SELECT
    COUNT(*) total,
    ROUND(AVG(price_usd),0) avg_precio,
    ROUND(AVG(price_per_m2),0) avg_ppm2,
    ROUND(AVG(covered_area),0) avg_area,
    ROUND(MIN(price_usd),0) min_precio,
    ROUND(MAX(price_usd),0) max_precio
FROM market_listings $whereSQL";
$statsStmt = $pdo->prepare($statsSQL);
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// ── Fuentes disponibles ───────────────────────────────────────────────────────
$sourcesStmt = $pdo->query("SELECT source, COUNT(*) c FROM market_listings WHERE active=1 GROUP BY source ORDER BY c DESC");
$sources = $sourcesStmt->fetchAll(PDO::FETCH_KEY_PAIR);

jOut([
    'success'   => true,
    'total'     => $total,
    'showing'   => count($results),
    'offset'    => $offset,
    'limit'     => $limit,
    'stats'     => $stats,
    'results'   => $results,
    'sources'   => $sources,
    'ars_rate'  => $arsRate,
]);

} catch (\Throwable $e) { jOut(['error' => $e->getMessage(), 'line' => $e->getLine()]); }
