<?php
/**
 * TasadorIA — Motor de Tasación v5.0
 * Factores: zona, superficie, antigüedad, estado, ambientes, baños,
 *           cocheras, expensas, deuda, escritura, piso, luminosidad,
 *           orientación, vista, amenities, IA fotos + datos reales BD
 * POI: escuelas, parques, shoppings, hospitales via Overpass API
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// ── Bootstrap ────────────────────────────────────────────────────────────────
$cfg = require __DIR__ . '/../config/settings.php';
$zones = require __DIR__ . '/../config/zones.php';

// ── Conexión BD ──────────────────────────────────────────────────────────────
$db = null;
try {
    $db = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Exception $e) { /* continuar sin BD */ }

// ── Helpers ───────────────────────────────────────────────────────────────────
/**
 * Genera un label legible para un comparable.
 * Prioridad: address limpia → title limpio → "Zona: {zone}"
 * "Limpia" = tiene letras (no es solo un número o precio).
 */
function comp_label(array $r): string {
    $candidates = [
        trim($r['address'] ?? ''),
        trim($r['title']   ?? ''),
    ];
    foreach ($candidates as $c) {
        if ($c === '') continue;
        // Descartamos si parece un número puro o precio (ej: "70.000", "USD 140")
        $stripped = preg_replace('/[\d\.\,\$\s]/', '', $c);
        if (strlen($stripped) < 3) continue; // casi sin letras → descartado
        // Truncamos si es muy largo
        return mb_strlen($c) > 50 ? mb_substr($c, 0, 47) . '...' : $c;
    }
    // Fallback a zona
    $zone = trim($r['zone'] ?? '');
    return $zone ? ucwords(str_replace('_', ' ', $zone)) : 'Propiedad en zona';
}

// ── GET de status ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    outputJson(['status'=>'ok','msg'=>'TasadorIA API v5.0','php'=>PHP_VERSION,'version'=>'5.0']);
}

// ── Leer input ───────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?: [];
foreach ($_POST as $k => $v) { if (!isset($input[$k])) $input[$k] = $v; }

// ── Campos con defaults ──────────────────────────────────────────────────────
$city          = strtolower(trim($input['city']          ?? ''));
$zone_key      = strtolower(trim($input['zone']          ?? ''));
$prop_type     = strtolower(trim($input['property_type'] ?? 'departamento'));
$operation     = strtolower(trim($input['operation']     ?? 'venta'));
$covered_area  = max(1, floatval($input['covered_area']  ?? 0));
$total_area    = max($covered_area, floatval($input['total_area'] ?? $covered_area));
$age_years     = max(0, intval($input['age_years']       ?? 0));
$condition     = strtolower(trim($input['condition']     ?? 'bueno'));
$ambientes     = max(0, intval($input['ambientes']       ?? 0));
$bedrooms      = max(0, intval($input['bedrooms']        ?? 0));
$bathrooms     = max(1, intval($input['bathrooms']       ?? 1));
$garages       = max(0, intval($input['garages']         ?? 0));
$floor         = max(0, intval($input['floor']           ?? 0));
$has_elevator  = !empty($input['has_elevator']);
$view          = strtolower(trim($input['view']          ?? 'exterior'));
$orientation   = strtolower(trim($input['orientation']   ?? 'sin_dato'));
$luminosity    = strtolower(trim($input['luminosity']    ?? 'normal'));
$expensas_ars  = max(0, floatval($input['expensas_ars']  ?? 0));
$escritura     = strtolower(trim($input['escritura']     ?? 'escriturado'));
$tiene_deuda   = !empty($input['tiene_deuda']);
$deuda_usd     = max(0, floatval($input['deuda_usd']     ?? 0));
$ai_adjustment = floatval($input['ai_adjustment']        ?? 0); // -15 a +15 (%)
$amenities     = is_array($input['amenities']  ?? null) ? $input['amenities']  : [];
$lat           = floatval($input['lat']   ?? 0);
$lng           = floatval($input['lng']   ?? 0);
$address       = trim($input['address']   ?? '');

$usd_rate = floatval($cfg['ars_usd_rate'] ?? 1000);

// ── Resolver zona ─────────────────────────────────────────────────────────────
$zone_data = null;
$city_data = null;
$zone_label = '';
$city_label = '';

// 1. Buscar por city + zone exactos
if ($city && isset($zones[$city])) {
    $city_data = $zones[$city];
    $city_label = $city_data['label'];
    if ($zone_key && isset($city_data['zones'][$zone_key])) {
        $zone_data  = $city_data['zones'][$zone_key];
        $zone_label = $zone_data['label'];
    } elseif (isset($city_data['zones']['general'])) {
        $zone_data  = $city_data['zones']['general'];
        $zone_label = $zone_data['label'];
    }
}

// 2. Fallback: buscar por coordenadas en bounding box
if (!$zone_data && $lat && $lng) {
    foreach ($zones as $ckey => $cval) {
        $b = $cval['bounds'] ?? null;
        if (!$b) continue;
        if ($lat >= $b['lat_min'] && $lat <= $b['lat_max'] &&
            $lng >= $b['lng_min'] && $lng <= $b['lng_max']) {
            $city_data  = $cval;
            $city_label = $cval['label'];
            $city       = $ckey;
            foreach ($cval['zones'] as $zk => $zv) {
                if (!isset($zv['coords'])) continue;
                $dist = sqrt(pow($lat - $zv['coords']['lat'],2) + pow($lng - $zv['coords']['lng'],2));
                if (!$zone_data || $dist < $best_dist) {
                    $zone_data  = $zv;
                    $zone_label = $zv['label'];
                    $zone_key   = $zk;
                    $best_dist  = $dist;
                }
            }
            break;
        }
    }
}

// 3. Fallback: santa_fe_capital general
if (!$zone_data) {
    $city       = 'santa_fe_capital';
    $zone_key   = 'general';
    $city_data  = $zones['santa_fe_capital'];
    $city_label = $city_data['label'];
    $zone_data  = $city_data['zones']['general'];
    $zone_label = $zone_data['label'];
}

$base_prices = $zone_data['price_m2'];

// ── Datos reales de mercado (BD) ──────────────────────────────────────────────
$market_data = ['used' => false, 'count' => 0, 'avg_ppm2' => 0];
$comparables = [];

if ($db) {
    // Match por ciudad + tipo + superficie similar (±40%)
    $area_min = $covered_area * 0.6;
    $area_max = $covered_area * 1.4;
    $city_search = ['Santa Fe', 'santa fe', $city_label];
    $type_map = [
        'departamento' => ['departamento', 'depto'],
        'casa'         => ['casa'],
        'ph'           => ['ph', 'penthouse'],
        'terreno'      => ['terreno', 'lote'],
        'local'        => ['local', 'local-comercial', 'local_comercial'],
        'oficina'      => ['oficina'],
        'galpon'       => ['galpon', 'galpón'],
    ];
    $types_to_search = $type_map[$prop_type] ?? [$prop_type];
    $placeholders_type = implode(',', array_fill(0, count($types_to_search), '?'));
    $placeholders_city = implode(',', array_fill(0, count($city_search), '?'));

    // ── 1. Query amplia (toda la ciudad) → para estadísticas de mercado ──────
    $params_all = array_merge($city_search, $types_to_search, [$area_min, $area_max]);
    $sql_all = "SELECT price_usd, price_per_m2, covered_area, address, zone, url, title
                FROM market_listings
                WHERE active = 1
                  AND city IN ($placeholders_city)
                  AND property_type IN ($placeholders_type)
                  AND covered_area BETWEEN ? AND ?
                  AND price_usd > 0
                  AND price_per_m2 > 0
                ORDER BY ABS(covered_area - $covered_area) ASC
                LIMIT 60";

    try {
        $stmt = $db->prepare($sql_all);
        $stmt->execute($params_all);
        $rows_all = $stmt->fetchAll();

        // Estadísticas de mercado con todos los datos disponibles (IQR)
        if (count($rows_all) >= 3) {
            $ppms = array_column($rows_all, 'price_per_m2');
            sort($ppms);
            $n = count($ppms);
            $q1 = $ppms[(int)($n * 0.25)];
            $q3 = $ppms[(int)($n * 0.75)];
            $iqr = $q3 - $q1;
            $filtered_ppms = array_filter($ppms, fn($p) => $p >= ($q1 - 1.5 * $iqr) && $p <= ($q3 + 1.5 * $iqr));
            if (count($filtered_ppms) >= 2) {
                $market_data['used']     = true;
                $market_data['count']    = count($filtered_ppms);
                $market_data['avg_ppm2'] = array_sum($filtered_ppms) / count($filtered_ppms);
                $market_data['min_ppm2'] = min($filtered_ppms);
                $market_data['max_ppm2'] = max($filtered_ppms);
            }
        }

        // ── 2. Comparables: misma zona, precio razonable ──────────────────────
        // Rango de precio razonable: ±45% del precio base de la zona
        $ref_ppm2   = $base_prices['avg'];
        $ppm2_floor = $ref_ppm2 * 0.55;
        $ppm2_ceil  = $ref_ppm2 * 1.55;

        // Función para normalizar texto de zona (quita tildes, lowercase, underscores→space)
        $norm_zone = function(string $z): string {
            $z = mb_strtolower(trim($z));
            $z = strtr($z, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
            return str_replace('_', ' ', $z);
        };

        $zone_key_n   = $norm_zone($zone_key);
        $zone_label_n = $norm_zone($zone_label);

        // Detectar si un comparable pertenece a la misma zona
        $is_same_zone = function(array $r) use ($norm_zone, $zone_key_n, $zone_label_n): bool {
            $rz = $norm_zone($r['zone'] ?? '');
            if ($rz === '') return false;
            return ($rz === $zone_key_n || $rz === $zone_label_n
                 || str_contains($rz, $zone_key_n) || str_contains($zone_key_n, $rz)
                 || str_contains($rz, $zone_label_n) || str_contains($zone_label_n, $rz));
        };

        // Filtrar: misma zona Y precio dentro del rango razonable
        $same_zone_rows = array_filter($rows_all, function($r) use ($is_same_zone, $ppm2_floor, $ppm2_ceil) {
            return $is_same_zone($r)
                && $r['price_per_m2'] >= $ppm2_floor
                && $r['price_per_m2'] <= $ppm2_ceil;
        });

        // Si hay al menos 2 de la misma zona, usarlos como comparables
        // Si no hay suficientes, usar ciudad completa PERO solo los de precio acorde
        if (count($same_zone_rows) >= 2) {
            $comp_pool = $same_zone_rows;
        } else {
            // Fallback: toda la ciudad, precio dentro del rango razonable
            $comp_pool = array_filter($rows_all, fn($r) =>
                $r['price_per_m2'] >= $ppm2_floor && $r['price_per_m2'] <= $ppm2_ceil
            );
        }

        // Ordenar por cercanía de superficie y tomar los top 5
        $comp_pool = array_values($comp_pool);
        usort($comp_pool, fn($a, $b) =>
            abs($a['covered_area'] - $covered_area) <=> abs($b['covered_area'] - $covered_area)
        );

        foreach (array_slice($comp_pool, 0, 5) as $r) {
            if ($r['price_usd'] > 0) {
                $comparables[] = [
                    'price'     => round($r['price_usd']),
                    'area'      => $r['covered_area'],
                    'ppm2'      => round($r['price_per_m2']),
                    'label'     => comp_label($r),
                    'zone'      => $r['zone'],
                    'url'       => $r['url'],
                    'same_zone' => $is_same_zone($r),
                ];
            }
        }
    } catch (Exception $e) { /* silencioso */ }
}

// ── Precio base por m² (blend 60/40 si hay datos) ────────────────────────────
if ($market_data['used']) {
    $blend_real   = 0.60;
    $blend_config = 0.40;
    $avg_base = ($market_data['avg_ppm2'] * $blend_real) + ($base_prices['avg'] * $blend_config);
    $min_base = ($market_data['min_ppm2'] * $blend_real) + ($base_prices['min'] * $blend_config);
    $max_base = ($market_data['max_ppm2'] * $blend_real) + ($base_prices['max'] * $blend_config);
} else {
    $avg_base = $base_prices['avg'];
    $min_base = $base_prices['min'];
    $max_base = $base_prices['max'];
}

// ── Factor por tipo de propiedad ─────────────────────────────────────────────
$prop_factor = 1.0;
if ($prop_type === 'ph')       $prop_factor = 1.12;
elseif ($prop_type === 'casa') $prop_factor = 0.92;
elseif ($prop_type === 'terreno') $prop_factor = 0.40;
elseif ($prop_type === 'local')   $prop_factor = 1.05;
elseif ($prop_type === 'oficina') $prop_factor = 0.95;
elseif ($prop_type === 'galpon')  $prop_factor = 0.55;

// ── Alquiler vs venta ─────────────────────────────────────────────────────────
$op_factor = ($operation === 'alquiler') ? 0.004 : 1.0; // 0.4% del valor para alquiler mensual

// ── Factor superficie (economías/diseconomías de escala) ──────────────────────
function surfaceFactor(float $area): float {
    if ($area < 30)  return 1.18;
    if ($area < 45)  return 1.10;
    if ($area < 60)  return 1.05;
    if ($area <= 90) return 1.00;
    if ($area <= 130) return 0.96;
    if ($area <= 200) return 0.92;
    return 0.88;
}

// ── Factor antigüedad ──────────────────────────────────────────────────────────
function ageFactor(int $years): array {
    if ($years === 0)    return [1.20, 'A estrenar'];
    if ($years <= 5)     return [1.10, '1-5 años'];
    if ($years <= 10)    return [1.05, '6-10 años'];
    if ($years <= 20)    return [1.00, '11-20 años'];
    if ($years <= 30)    return [0.94, '21-30 años'];
    if ($years <= 40)    return [0.88, '31-40 años'];
    if ($years <= 50)    return [0.82, '41-50 años'];
    if ($years <= 60)    return [0.76, '51-60 años'];
    return [0.72, '60+ años'];
}

// ── Factor condición ───────────────────────────────────────────────────────────
function conditionFactor(string $cond): array {
    switch ($cond) {
        case 'excelente':   return [1.12, 'Excelente'];
        case 'muy_bueno':   return [1.06, 'Muy bueno'];
        case 'bueno':       return [1.00, 'Bueno'];
        case 'regular':     return [0.88, 'Regular'];
        case 'a_refaccionar': return [0.75, 'A refaccionar'];
        default:            return [1.00, 'Bueno'];
    }
}

// ── Factor ambientes ────────────────────────────────────────────────────────────
function ambientesFactor(int $amb): array {
    if ($amb <= 0)  return [1.00, 'Sin dato'];
    if ($amb === 1) return [0.92, '1 ambiente'];
    if ($amb === 2) return [1.00, '2 ambientes'];
    if ($amb === 3) return [1.05, '3 ambientes'];
    if ($amb === 4) return [1.08, '4 ambientes'];
    return [1.10, '5+ ambientes'];
}

// ── Factor baños ────────────────────────────────────────────────────────────────
function bathroomsFactor(int $baths): array {
    if ($baths <= 1) return [1.00, '1 baño'];
    if ($baths === 2) return [1.04, '2 baños'];
    return [1.08, '3+ baños'];
}

// ── Factor cocheras ────────────────────────────────────────────────────────────
function garagesFactor(int $gar): array {
    if ($gar === 0)  return [1.00, 'Sin cochera'];
    if ($gar === 1)  return [1.06, '1 cochera'];
    return [1.09, '2+ cocheras'];
}

// ── Factor piso + ascensor ──────────────────────────────────────────────────────
function floorFactor(int $fl, bool $elevator): array {
    if ($fl === 0 && !$elevator) return [0.96, 'Planta baja'];
    if ($fl === 0)               return [0.98, 'Planta baja'];
    if ($fl <= 3 && !$elevator)  return [0.97, 'Piso bajo sin ascensor'];
    if ($fl <= 3)                return [1.00, 'Piso bajo con ascensor'];
    if ($fl <= 7)                return [1.03, 'Piso medio'];
    if ($fl <= 12)               return [1.07, 'Piso alto'];
    return [1.12, 'Piso muy alto'];
}

// ── Factor vista ────────────────────────────────────────────────────────────────
function viewFactor(string $v): array {
    switch ($v) {
        case 'rio_mar':  return [1.18, 'Río/Mar'];
        case 'parque':   return [1.10, 'Parque/Verde'];
        case 'exterior': return [1.02, 'Exterior'];
        case 'interno':  return [0.96, 'Interno'];
        default:         return [1.00, 'Sin dato'];
    }
}

// ── Factor orientación ──────────────────────────────────────────────────────────
function orientacionFactor(string $o): array {
    switch ($o) {
        case 'norte':   return [1.05, 'Norte'];
        case 'noreste':
        case 'noroeste': return [1.03, 'Nororiente'];
        case 'este':    return [1.00, 'Este'];
        case 'oeste':   return [0.98, 'Oeste'];
        case 'sur':     return [0.95, 'Sur'];
        default:        return [1.00, 'Sin dato'];
    }
}

// ── Factor luminosidad ──────────────────────────────────────────────────────────
function luminosityFactor(string $l): array {
    switch ($l) {
        case 'muy_luminoso': return [1.05, 'Muy luminoso'];
        case 'luminoso':     return [1.02, 'Luminoso'];
        case 'normal':       return [1.00, 'Normal'];
        case 'poco':         return [0.95, 'Poco luminoso'];
        case 'oscuro':       return [0.93, 'Oscuro'];
        default:             return [1.00, 'Normal'];
    }
}

// ── Factor amenities ────────────────────────────────────────────────────────────
function amenitiesFactor(array $amenities): array {
    $premiums = ['pileta','gimnasio','sum','solarium','spa','jacuzzi','sauna','roof_top'];
    $basics   = ['ascensor','seguridad','lavadero','baulera','bike_room','cowork'];
    $count_p  = 0;
    $count_b  = 0;
    foreach ($amenities as $key => $val) {
        if (!$val) continue;
        if (in_array($key, $premiums)) $count_p++;
        elseif (in_array($key, $basics)) $count_b++;
    }
    $factor = 1.00 + ($count_p * 0.025) + ($count_b * 0.01);
    $factor = min($factor, 1.12);
    $total = $count_p + $count_b;
    if ($total === 0) return [1.00, 'Sin amenities'];
    return [$factor, "$total amenities"];
}

// ── Factor expensas ─────────────────────────────────────────────────────────────
function expensasFactor(float $ars, float $usd_rate): array {
    if ($ars <= 0) return [1.00, 'Sin dato', 0];
    $usd_month = $ars / $usd_rate;
    $base_usd  = 30; // base ~$30/mes
    if ($usd_month <= $base_usd) return [1.00, '$' . number_format($ars,0,',','.') . ' ARS/mes', 0];
    // -0.5% por cada $10 USD extra sobre la base, max -15%
    $extra = $usd_month - $base_usd;
    $penalty = min(0.15, ($extra / 10) * 0.005);
    $pct = round($penalty * 100, 1);
    return [1.0 - $penalty, '$' . number_format($ars,0,',','.') . ' ARS/mes', $pct];
}

// ── Factor escritura ────────────────────────────────────────────────────────────
function escrituraFactor(string $esc): array {
    switch ($esc) {
        case 'escriturado': return [1.00, 'Escriturado'];
        case 'boleto':      return [0.94, 'Boleto de compraventa'];
        case 'posesion':    return [0.88, 'Posesión sin escritura'];
        case 'sucesion':    return [0.85, 'En trámite de sucesión'];
        default:            return [1.00, 'Escriturado'];
    }
}

// ── Calcular todos los factores ───────────────────────────────────────────────
[$age_f,  $age_l]   = ageFactor($age_years);
[$cond_f, $cond_l]  = conditionFactor($condition);
[$amb_f,  $amb_l]   = ambientesFactor($ambientes);
[$bath_f, $bath_l]  = bathroomsFactor($bathrooms);
[$gar_f,  $gar_l]   = garagesFactor($garages);
[$fl_f,   $fl_l]    = floorFactor($floor, $has_elevator);
[$view_f, $view_l]  = viewFactor($view);
[$ori_f,  $ori_l]   = orientacionFactor($orientation);
[$lum_f,  $lum_l]   = luminosityFactor($luminosity);
[$ame_f,  $ame_l]   = amenitiesFactor($amenities);
[$exp_f,  $exp_l, $exp_pct] = expensasFactor($expensas_ars, $usd_rate);
[$esc_f,  $esc_l]   = escrituraFactor($escritura);
$surf_f = surfaceFactor($covered_area);

// Factor IA fotos
$ai_f = 1.0 + (max(-15, min(15, $ai_adjustment)) / 100);
$ai_l = $ai_adjustment != 0 ? sprintf('%+.1f%%', $ai_adjustment) : 'Sin análisis';

// Total de factores (excluyendo AI, expensas y escritura que se aplican al final)
$total_factor =
    $prop_factor *
    $surf_f *
    $age_f *
    $cond_f *
    $amb_f *
    $bath_f *
    $gar_f *
    $fl_f *
    $view_f *
    $ori_f *
    $lum_f *
    $ame_f *
    $exp_f *
    $esc_f *
    $ai_f;

// ── Precio bruto ────────────────────────────────────────────────────────────────
$price_gross_avg = round($avg_base * $covered_area * $total_factor);
// Rango de negociación realista: ±12% sobre el precio sugerido.
// Usar min/max de zona directamente genera spreads absurdos (>100%).
$price_gross_min = round($price_gross_avg * 0.88);
$price_gross_max = round($price_gross_avg * 1.12);

// Opción alquiler
if ($operation === 'alquiler') {
    $price_gross_avg = round($price_gross_avg * 0.004);
    $price_gross_min = round($price_gross_min * 0.004);
    $price_gross_max = round($price_gross_max * 0.004);
}

// ── Deducir deuda hipotecaria ────────────────────────────────────────────────
$deuda_usd_final = $tiene_deuda ? $deuda_usd : 0;
$price_avg = max(0, $price_gross_avg - $deuda_usd_final);
$price_min = max(0, $price_gross_min - $deuda_usd_final);
$price_max = max(0, $price_gross_max - $deuda_usd_final);

// ── Precio por m² final ──────────────────────────────────────────────────────
$ppm2 = $covered_area > 0 ? round($price_avg / $covered_area) : 0;

// ── POI via Overpass API ──────────────────────────────────────────────────────
$poi = ['escuelas'=>[], 'parques'=>[], 'shoppings'=>[], 'hospitales'=>[], 'transporte'=>[]];

if ($lat && $lng) {
    // "out center" es clave: devuelve centroide de ways/relations además de nodos
    $overpass_q = '[out:json][timeout:12];('
        . 'node["amenity"~"school|college"](around:700,' . $lat . ',' . $lng . ');'
        . 'node["amenity"~"hospital|clinic|pharmacy"](around:1000,' . $lat . ',' . $lng . ');'
        . 'node["leisure"~"park|garden|plaza"](around:600,' . $lat . ',' . $lng . ');'
        . 'way["leisure"~"park|garden"](around:600,' . $lat . ',' . $lng . ');'
        . 'relation["leisure"~"park|garden"](around:600,' . $lat . ',' . $lng . ');'
        . 'node["shop"~"mall|supermarket"](around:1000,' . $lat . ',' . $lng . ');'
        . 'node["highway"="bus_stop"](around:500,' . $lat . ',' . $lng . ');'
        . 'node["amenity"="bus_station"](around:500,' . $lat . ',' . $lng . ');'
        . ');out center;';       // "center" da lat/lon para nodos Y centroide para ways

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => 'https://overpass-api.de/api/interpreter',
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'data=' . urlencode($overpass_q),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'TasadorIA/5.0',
    ]);
    $resp = curl_exec($curl);
    $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http === 200 && $resp) {
        $data = json_decode($resp, true);
        $seen = [];
        foreach (($data['elements'] ?? []) as $el) {
            $tags = $el['tags'] ?? [];
            $name = $tags['name'] ?? ($tags['name:es'] ?? null);
            if (!$name || isset($seen[$name])) continue;
            $seen[$name] = true;

            // Nodos tienen lat/lon directo; ways/relations tienen center.lat/lon
            $elLat = $el['lat'] ?? ($el['center']['lat'] ?? null);
            $elLon = $el['lon'] ?? ($el['center']['lon'] ?? null);

            $entry = ['name' => $name];
            if ($elLat && $elLon) {
                $dlat = ($elLat - $lat) * 111000;
                $dlng = ($elLon - $lng) * 111000 * cos(deg2rad((float)$lat));
                $entry['dist'] = round(sqrt($dlat*$dlat + $dlng*$dlng));
            }

            $amenity = $tags['amenity'] ?? '';
            $leisure = $tags['leisure'] ?? '';
            $shop    = $tags['shop']    ?? '';
            $hw      = $tags['highway'] ?? '';

            if (in_array($amenity, ['school','college'])) {
                $poi['escuelas'][] = $entry;
            } elseif (in_array($amenity, ['hospital','clinic','pharmacy'])) {
                $poi['hospitales'][] = $entry;
            } elseif (in_array($leisure, ['park','garden','plaza'])) {
                $poi['parques'][] = $entry;
            } elseif (in_array($shop, ['mall','supermarket'])) {
                $poi['shoppings'][] = $entry;
            } elseif ($hw === 'bus_stop' || $amenity === 'bus_station') {
                $poi['transporte'][] = $entry;
            }
        }
        // Ordenar por distancia y limitar a 5 por categoría
        foreach ($poi as $k => &$arr) {
            usort($arr, fn($a,$b) => ($a['dist'] ?? 9999) - ($b['dist'] ?? 9999));
            $arr = array_slice($arr, 0, 5);
        }
    }
}

// ── Código de tasación ────────────────────────────────────────────────────────
$code = 'TA-' . strtoupper(substr(md5(uniqid()), 0, 8));

// ── Guardar en BD (compatible con tabla actual + columnas nuevas opcionales) ───
if ($db) {
    try {
        $data_json   = json_encode(array_merge((array)$input, ['city_resolved'=>$city,'zone_resolved'=>$zone_key]), JSON_UNESCAPED_UNICODE);
        $result_json = json_encode(['min'=>$price_min,'suggested'=>$price_avg,'max'=>$price_max,'ppm2'=>$ppm2], JSON_UNESCAPED_UNICODE);
        // Intentar con columnas extendidas primero
        try {
            $stmt = $db->prepare("INSERT INTO tasaciones
                (code, data_json, result_json, city, zone, ai_score,
                 ambientes, bedrooms, bathrooms, garages, floor, has_elevator,
                 expensas_ars, escritura, tiene_deuda, deuda_usd,
                 price_suggested, price_ppm2, address, lat, lng)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $code, $data_json, $result_json, $city_label, $zone_label,
                $ai_adjustment != 0 ? $ai_adjustment : null,
                $ambientes ?: null, $bedrooms ?: null, $bathrooms, $garages ?: null,
                $floor ?: null, $has_elevator ? 1 : 0,
                $expensas_ars ?: null, $escritura !== 'escriturado' ? $escritura : null,
                $tiene_deuda ? 1 : 0, $deuda_usd ?: null,
                $price_avg, $ppm2, $address ?: null,
                $lat ?: null, $lng ?: null
            ]);
        } catch (Exception $e2) {
            // Fallback: insertar solo con las columnas originales
            $stmt = $db->prepare("INSERT INTO tasaciones (code, data_json, result_json, city, zone, ai_score) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$code, $data_json, $result_json, $city_label, $zone_label, $ai_adjustment ?: null]);
        }
    } catch (Exception $e) { /* silencioso */ }
}

// ── Respuesta final ───────────────────────────────────────────────────────────
$multipliers = [
    'Tipo de propiedad' => ['factor' => $prop_factor, 'label' => ucfirst($prop_type)],
    'Superficie'        => ['factor' => $surf_f, 'label' => "{$covered_area} m²"],
    'Antigüedad'        => ['factor' => $age_f,  'label' => $age_l],
    'Estado'            => ['factor' => $cond_f, 'label' => $cond_l],
    'Ambientes'         => ['factor' => $amb_f,  'label' => $amb_l],
    'Baños'             => ['factor' => $bath_f, 'label' => $bath_l],
    'Cocheras'          => ['factor' => $gar_f,  'label' => $gar_l],
    'Piso'              => ['factor' => $fl_f,   'label' => $fl_l],
    'Vista'             => ['factor' => $view_f, 'label' => $view_l],
    'Orientación'       => ['factor' => $ori_f,  'label' => $ori_l],
    'Luminosidad'       => ['factor' => $lum_f,  'label' => $lum_l],
    'Amenities'         => ['factor' => $ame_f,  'label' => $ame_l],
    'Expensas'          => ['factor' => $exp_f,  'label' => $exp_l],
    'Escritura'         => ['factor' => $esc_f,  'label' => $esc_l],
];
if ($ai_adjustment != 0) {
    $multipliers['Análisis IA fotos'] = ['factor' => $ai_f, 'label' => $ai_l];
}

outputJson([
    'success'      => true,
    'code'         => $code,
    'zone'         => ['city' => $city_label, 'zone' => $zone_label, 'city_key' => $city],
    'price'        => [
        'currency'  => 'USD',
        'min'       => $price_min,
        'suggested' => $price_avg,
        'max'       => $price_max,
        'ppm2'      => $ppm2,
        'gross'     => $price_gross_avg,
    ],
    'price_ars'    => [
        'min'       => round($price_min * $usd_rate / 1000) * 1000,
        'suggested' => round($price_avg * $usd_rate / 1000) * 1000,
        'max'       => round($price_max * $usd_rate / 1000) * 1000,
        'rate'      => $usd_rate,
    ],
    'deuda'        => [
        'tiene_deuda'=> $tiene_deuda,
        'monto_usd'  => $deuda_usd_final,
    ],
    'expensas'     => [
        'ars_mes'   => $expensas_ars,
        'usd_mes'   => round($expensas_ars / $usd_rate, 1),
        'impacto_pct' => $exp_pct,
    ],
    'total_factor' => round($total_factor, 4),
    'multipliers'  => $multipliers,
    'market_data'  => $market_data,
    'comparables'  => $comparables,
    'poi'          => $poi,
    'property'     => [
        'type'       => $prop_type,
        'operation'  => $operation,
        'covered'    => $covered_area,
        'total'      => $total_area,
        'age'        => $age_years,
        'ambientes'  => $ambientes,
        'bedrooms'   => $bedrooms,
        'bathrooms'  => $bathrooms,
        'garages'    => $garages,
        'floor'      => $floor,
        'elevator'   => $has_elevator,
        'escritura'  => $escritura,
        'address'    => $address,
    ],
    'timestamp'    => date('c'),
]);

// ── Helper ───────────────────────────────────────────────────────────────────
function outputJson(array $data): void {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
