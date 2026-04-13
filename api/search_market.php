<?php
// tasador/api/search_market.php
// Busca precios reales en Zonaprop, Argenprop y Properati para una dirección/zona
// Cachea los resultados en la BD para no sobrecargar los portales

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }

function jsonOut(array $d): void { ob_end_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$cfg = require_once __DIR__ . '/../config/settings.php';

$raw    = file_get_contents('php://input') ?: '{}';
$params = json_decode($raw, true) ?? [];

// Parámetros de búsqueda
$city      = strtolower(trim($params['city']          ?? 'santa-fe'));
$zone      = strtolower(trim($params['zone']          ?? ''));
$propType  = strtolower(trim($params['property_type'] ?? 'departamento'));
$operation = strtolower(trim($params['operation']     ?? 'venta'));
$area      = (float)($params['covered_area']          ?? 65);
$rooms     = (int)($params['bedrooms']                ?? 2);
$source    = strtolower(trim($params['source']        ?? 'zonaprop'));
$force     = (bool)($params['force_refresh']          ?? false);

// ── Conectar BD ────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\Throwable $e) {
    jsonOut(['success' => false, 'error' => 'DB: ' . $e->getMessage()]);
}

// ── 1. Buscar primero en datos importados propios ─────────────────────────────
$ownData = getOwnData($pdo, $city, $zone, $propType, $operation);

// ── 2. Cache check ────────────────────────────────────────────────────────────
$cacheKey  = hash('sha256', "$source|$city|$zone|$propType|$operation");
$cached    = null;
if (!$force) {
    try {
        $row = $pdo->prepare("SELECT result_json, avg_price_m2, result_count, created_at FROM market_cache WHERE query_hash=? AND expires_at > NOW()")->execute([$cacheKey]) ? $pdo->prepare("SELECT result_json, avg_price_m2, result_count, created_at FROM market_cache WHERE query_hash=? AND expires_at > NOW()")->execute([$cacheKey]) : null;
        // Simpler:
        $stmt = $pdo->prepare("SELECT result_json, avg_price_m2, result_count, created_at FROM market_cache WHERE query_hash=? AND expires_at > NOW()");
        $stmt->execute([$cacheKey]);
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}
}

if ($cached && !$force) {
    $listings = json_decode($cached['result_json'], true) ?? [];
    jsonOut([
        'success'      => true,
        'source'       => $source,
        'from_cache'   => true,
        'cached_at'    => $cached['created_at'],
        'listings'     => $listings,
        'count'        => $cached['result_count'],
        'avg_price_m2' => (float)$cached['avg_price_m2'],
        'own_data'     => $ownData,
        'combined'     => combineData($listings, $ownData, $area),
    ]);
}

// ── 3. Scraping de Zonaprop ───────────────────────────────────────────────────
$listings = [];
$avgPpm2  = 0;

if ($source === 'zonaprop' || $source === 'all') {
    $result = scrapeZonaprop($city, $zone, $propType, $operation, $cfg);
    $listings = array_merge($listings, $result['listings']);
}

if ($source === 'argenprop' || $source === 'all') {
    $result2 = scrapeArgenprop($city, $zone, $propType, $operation, $cfg);
    $listings = array_merge($listings, $result2['listings']);
}

// Calcular promedios
if (!empty($listings)) {
    $prices = array_filter(array_column($listings, 'price_per_m2'));
    if ($prices) {
        sort($prices);
        // Remover outliers: sacar 10% superior e inferior
        $trim  = max(1, (int)(count($prices) * 0.1));
        $clean = array_slice($prices, $trim, count($prices) - $trim * 2);
        $avgPpm2 = $clean ? round(array_sum($clean) / count($clean), 0) : round(array_sum($prices) / count($prices), 0);
    }
}

// ── 4. Guardar en caché y en market_listings ──────────────────────────────────
try {
    // Guardar en caché (24 horas)
    $pdo->prepare("INSERT INTO market_cache (query_hash, source, query_params, result_json, result_count, avg_price_m2, expires_at) VALUES (?,?,?,?,?,?, DATE_ADD(NOW(), INTERVAL 24 HOUR)) ON DUPLICATE KEY UPDATE result_json=VALUES(result_json), result_count=VALUES(result_count), avg_price_m2=VALUES(avg_price_m2), expires_at=VALUES(expires_at), created_at=NOW()")
        ->execute([$cacheKey, $source, json_encode($params, JSON_UNESCAPED_UNICODE), json_encode($listings, JSON_UNESCAPED_UNICODE), count($listings), $avgPpm2]);

    // Guardar listings individuales en market_listings
    if (!empty($listings)) {
        $stmtIns = $pdo->prepare("INSERT IGNORE INTO market_listings (source, external_id, url, title, city, zone, property_type, operation, covered_area, price, currency, price_usd, price_per_m2, bedrooms, active, scraped_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())");
        foreach ($listings as $l) {
            if (empty($l['price_per_m2'])) continue;
            $extId = $source . '_' . md5(($l['url'] ?? $l['title'] ?? uniqid()));
            $stmtIns->execute([
                $source, $extId, $l['url'] ?? null, $l['title'] ?? null,
                $l['city'] ?? $city, $l['zone'] ?? $zone,
                $propType, $operation, $l['area'] ?? null,
                $l['price'] ?? null, $l['currency'] ?? 'USD',
                $l['price_usd'] ?? $l['price'] ?? null,
                $l['price_per_m2'],
                $l['bedrooms'] ?? null,
            ]);
        }
    }
} catch (\Throwable $e) {
    // Guardar en caché falla silenciosamente
}

jsonOut([
    'success'      => true,
    'source'       => $source,
    'from_cache'   => false,
    'listings'     => $listings,
    'count'        => count($listings),
    'avg_price_m2' => $avgPpm2,
    'own_data'     => $ownData,
    'combined'     => combineData($listings, $ownData, $area),
]);

// ────────────────────────────────────────────────────────────────────────────
// FUNCIONES DE SCRAPING
// ────────────────────────────────────────────────────────────────────────────

function fetchUrl(string $url): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: es-AR,es;q=0.9',
            'Accept-Encoding: gzip, deflate',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
        ],
        CURLOPT_ENCODING       => 'gzip',
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return (string)$response;
}

function buildZonapropUrl(string $city, string $zone, string $propType, string $operation): string {
    // Mapeo de tipos
    $typeMap = [
        'departamento' => 'departamentos',
        'casa'         => 'casas',
        'ph'           => 'ph',
        'terreno'      => 'terrenos',
        'local'        => 'locales-comerciales',
        'oficina'      => 'oficinas',
    ];
    $opMap = ['venta' => 'venta', 'alquiler' => 'alquiler'];

    $typeSlug = $typeMap[$propType] ?? 'departamentos';
    $opSlug   = $opMap[$operation]  ?? 'venta';

    // Normalizar ciudad
    $citySlug = match(true) {
        str_contains($city, 'santa') && str_contains($city, 'fe') => 'santa-fe-capital',
        str_contains($city, 'buenos') || str_contains($city, 'caba') => 'capital-federal',
        str_contains($city, 'rosario')  => 'rosario',
        str_contains($city, 'cordoba')  => 'cordoba-capital',
        default => str_replace(' ', '-', $city),
    };

    // Con zona específica
    $zoneSlug = $zone ? '-' . str_replace(' ', '-', $zone) : '';

    return "https://www.zonaprop.com.ar/{$typeSlug}-{$opSlug}{$zoneSlug}-{$citySlug}.html";
}

function scrapeZonaprop(string $city, string $zone, string $propType, string $operation, array $cfg): array {
    $url  = buildZonapropUrl($city, $zone, $propType, $operation);
    $html = fetchUrl($url);

    if (empty($html) || strlen($html) < 1000) {
        return ['listings' => [], 'url' => $url, 'error' => 'Sin respuesta o bloqueado'];
    }

    $listings = [];

    // Zonaprop usa JSON-LD o variables JS para los datos
    // Intentar extraer de window.__INITIAL_STATE__ o similar
    if (preg_match('/window\.__PRELOADED_STATE__\s*=\s*({.+?});\s*(?:window|<\/script>)/s', $html, $m)) {
        try {
            $state = json_decode($str = preg_replace('/,\s*([}\]])/','$1',$m[1]), true);
            $items = $state['listPostings'] ?? $state['results'] ?? $state['listings'] ?? [];
            foreach ((array)$items as $item) {
                $price = (float)($item['price']['amount'] ?? $item['price'] ?? 0);
                $curr  = $item['price']['currency'] ?? 'USD';
                $area  = (float)($item['totalArea'] ?? $item['roofedArea'] ?? 0);
                if ($price > 0 && $area > 5) {
                    $priceUSD = $curr === 'ARS' ? $price / ($cfg['ars_usd_rate'] ?? 1450) : $price;
                    $listings[] = [
                        'source'       => 'zonaprop',
                        'title'        => $item['title'] ?? $item['address'] ?? '',
                        'price'        => $price,
                        'currency'     => $curr,
                        'price_usd'    => round($priceUSD, 0),
                        'area'         => $area,
                        'price_per_m2' => $area > 0 ? round($priceUSD / $area, 0) : 0,
                        'bedrooms'     => $item['bedrooms'] ?? $item['rooms'] ?? null,
                        'city'         => $city,
                        'zone'         => $item['address']['neighborhood'] ?? $zone,
                        'url'          => 'https://www.zonaprop.com.ar' . ($item['url'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable $e) {}
    }

    // Fallback: regex sobre el HTML para extraer precios visibles
    if (empty($listings)) {
        $listings = extractListingsFromHtml($html, $city, $zone, $cfg);
    }

    return ['listings' => $listings, 'url' => $url, 'count' => count($listings)];
}

function scrapeArgenprop(string $city, string $zone, string $propType, string $operation, array $cfg): array {
    $typeMap = ['departamento' => 'departamentos', 'casa' => 'casas', 'ph' => 'ph', 'terreno' => 'terrenos'];
    $typeSlug = $typeMap[$propType] ?? 'departamentos';
    $opSlug   = $operation === 'alquiler' ? 'alquiler' : 'venta';

    $citySlug = match(true) {
        str_contains($city, 'santa') => 'santa-fe',
        str_contains($city, 'buenos') || str_contains($city, 'caba') => 'capital-federal',
        str_contains($city, 'rosario') => 'rosario',
        default => str_replace(' ', '-', $city),
    };

    $url  = "https://www.argenprop.com/{$typeSlug}/{$opSlug}/{$citySlug}";
    $html = fetchUrl($url);

    if (empty($html) || strlen($html) < 1000) {
        return ['listings' => [], 'url' => $url, 'error' => 'Sin respuesta'];
    }

    // Extraer JSON de script tags
    $listings = [];
    if (preg_match_all('/"price"\s*:\s*\{[^}]+\}/s', $html, $matches)) {
        // Parsear cada match de precio
    }

    $listings = extractListingsFromHtml($html, $city, $zone, $cfg, 'argenprop');
    return ['listings' => $listings, 'url' => $url, 'count' => count($listings)];
}

function extractListingsFromHtml(string $html, string $city, string $zone, array $cfg, string $source = 'zonaprop'): array {
    $listings = [];
    $arsRate  = (float)($cfg['ars_usd_rate'] ?? 1450);

    // Buscar patrones de precio USD y ARS en el HTML
    // Zonaprop: "USD 125.000" o "$ 50.000.000"
    preg_match_all('/\b(USD|U\$S|u\$s)\s*[\$]?\s*([\d\.,]+)\b/i', $html, $usdMatches);
    preg_match_all('/\$\s*([\d\.,]+)\s*(?:ARS|pesos)?/i', $html, $arsMatches);

    // Buscar m² cerca de los precios
    preg_match_all('/(\d+(?:\.\d+)?)\s*m[²2]/i', $html, $areaMatches);

    $prices    = [];
    foreach ($usdMatches[2] ?? [] as $p) {
        $val = (float)str_replace(['.', ','], ['', '.'], $p);
        if ($val > 10000 && $val < 10000000) $prices[] = ['price' => $val, 'currency' => 'USD', 'usd' => $val];
    }

    $areas = [];
    foreach ($areaMatches[1] ?? [] as $a) {
        $val = (float)str_replace(',', '.', $a);
        if ($val > 15 && $val < 2000) $areas[] = $val;
    }

    if (empty($prices) || empty($areas)) return $listings;

    // Combinar precios y áreas para generar listings aproximados
    $avgArea  = array_sum($areas)  / count($areas);
    $maxItems = min(20, count($prices), count($areas));

    for ($i = 0; $i < $maxItems; $i++) {
        $price  = $prices[$i]['price']    ?? 0;
        $curr   = $prices[$i]['currency'] ?? 'USD';
        $priceUSD = $curr === 'ARS' ? $price / $arsRate : $price;
        $area   = $areas[$i] ?? $avgArea;

        if ($area > 0 && $priceUSD > 0) {
            $ppm2 = round($priceUSD / $area, 0);
            if ($ppm2 > 200 && $ppm2 < 20000) {
                $listings[] = [
                    'source'       => $source,
                    'price'        => $price,
                    'currency'     => $curr,
                    'price_usd'    => round($priceUSD, 0),
                    'area'         => $area,
                    'price_per_m2' => $ppm2,
                    'city'         => $city,
                    'zone'         => $zone,
                ];
            }
        }
    }

    return $listings;
}

// ── Datos propios de la BD ─────────────────────────────────────────────────────
function getOwnData(PDO $pdo, string $city, string $zone, string $propType, string $operation): array {
    try {
        $where = "active=1 AND scraped_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) AND price_per_m2 > 200 AND price_per_m2 < 20000";
        $bind  = [];

        if ($city)     { $where .= " AND city LIKE ?";          $bind[] = "%$city%"; }
        if ($zone)     { $where .= " AND (zone LIKE ? OR address LIKE ?)"; $bind[] = "%$zone%"; $bind[] = "%$zone%"; }
        if ($propType) { $where .= " AND property_type = ?";    $bind[] = $propType; }
        if ($operation){ $where .= " AND operation = ?";        $bind[] = $operation; }

        $stmt = $pdo->prepare("SELECT COUNT(*) as count, ROUND(AVG(price_per_m2),0) as avg_ppm2, ROUND(MIN(price_per_m2),0) as min_ppm2, ROUND(MAX(price_per_m2),0) as max_ppm2, MAX(scraped_at) as last_update FROM market_listings WHERE $where");
        $stmt->execute($bind);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$stats['count'] < 3) return ['available' => false, 'count' => (int)$stats['count']];

        return [
            'available'   => true,
            'count'       => (int)$stats['count'],
            'avg_ppm2'    => (float)$stats['avg_ppm2'],
            'min_ppm2'    => (float)$stats['min_ppm2'],
            'max_ppm2'    => (float)$stats['max_ppm2'],
            'last_update' => $stats['last_update'],
        ];
    } catch (\Throwable $e) {
        return ['available' => false, 'error' => $e->getMessage()];
    }
}

// ── Combinar datos propios + portales para price_m2 final ─────────────────────
function combineData(array $portalListings, array $ownData, float $area): array {
    $sources = [];

    // Datos propios (más confiables, peso mayor)
    if (!empty($ownData['available']) && $ownData['avg_ppm2'] > 0) {
        $sources[] = ['source' => 'datos_propios', 'avg_ppm2' => $ownData['avg_ppm2'], 'count' => $ownData['count'], 'weight' => 2.0];
    }

    // Portal scraped
    if (!empty($portalListings)) {
        $prices = array_filter(array_column($portalListings, 'price_per_m2'));
        if ($prices) {
            sort($prices);
            $trim  = max(0, (int)(count($prices) * 0.1));
            $clean = array_slice($prices, $trim, count($prices) - max(1, $trim * 2));
            $avg   = $clean ? round(array_sum($clean) / count($clean), 0) : round(array_sum($prices) / count($prices), 0);
            $sources[] = ['source' => 'portal', 'avg_ppm2' => $avg, 'count' => count($prices), 'weight' => 1.0];
        }
    }

    if (empty($sources)) return ['avg_ppm2' => null, 'confidence' => 'sin_datos'];

    // Promedio ponderado
    $totalWeight = array_sum(array_column($sources, 'weight'));
    $weightedSum = array_sum(array_map(fn($s) => $s['avg_ppm2'] * $s['weight'], $sources));
    $finalPpm2   = round($weightedSum / $totalWeight, 0);

    return [
        'avg_ppm2'      => $finalPpm2,
        'estimated_price' => round($finalPpm2 * $area, 0),
        'confidence'    => count($sources) >= 2 ? 'alta' : 'media',
        'sources'       => $sources,
    ];
}
