<?php
/**
 * TasadorIA — api/construction.php
 * ──────────────────────────────────────────────────────────────────────────────
 * Motor de estimación del costo de construcción + análisis de factibilidad.
 * Retorna breakdown por rubro, materiales principales y mini BIM.
 *
 * GET  ?status=1              → health check
 * POST { city, zone, quality, covered_area, uncovered_area?,
 *        property_type?, age_years?, market_value_usd?, operation? }
 *
 * quality: 'economica' | 'estandar' | 'calidad' | 'premium'
 * ──────────────────────────────────────────────────────────────────────────────
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$cfg = is_file(__DIR__ . '/../config/settings.php') ? require __DIR__ . '/../config/settings.php' : [];

// ── Health check ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['status' => 'ok', 'module' => 'construction', 'version' => '1.0']);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function jout(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Input ─────────────────────────────────────────────────────────────────────
$in = json_decode(file_get_contents('php://input'), true) ?? [];

$city           = strtolower(trim((string)($in['city']           ?? '')));
$zone           = strtolower(trim((string)($in['zone']           ?? '')));
$quality        = strtolower(trim((string)($in['quality']        ?? 'estandar')));
$coveredArea    = max(0.0, (float)($in['covered_area']           ?? 0));
$uncoveredArea  = max(0.0, (float)($in['uncovered_area']         ?? 0));
$propertyType   = strtolower(trim((string)($in['property_type']  ?? '')));
$ageYears       = max(0, (int)($in['age_years']                  ?? 0));
$marketValueUsd = max(0.0, (float)($in['market_value_usd']       ?? 0));
$arsRate        = max(1, (float)($cfg['ars_usd_rate']            ?? 1450));

if (!in_array($quality, ['economica', 'estandar', 'calidad', 'premium'])) {
    $quality = 'estandar';
}

if ($coveredArea <= 0) {
    jout(['success' => false, 'error' => 'covered_area requerida y > 0'], 400);
}

// ── DB connection ─────────────────────────────────────────────────────────────
$pdo = null;
if (!empty($cfg['db']['host'])) {
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
            $cfg['db']['user'],
            $cfg['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (\Throwable $e) {
        // continúa con valores default
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. COSTO BASE POR M² (DB → ciudad → zona → calidad)
// ─────────────────────────────────────────────────────────────────────────────
$costUsdM2  = null;
$laborPct   = 40.0;
$dataSource = 'default';

if ($pdo) {
    // Intenta: zona específica → toda la ciudad → nacional
    $tries = [
        [$city, $zone],
        [$city, ''],
        ['', ''],
    ];
    foreach ($tries as [$tryCity, $tryZone]) {
        $st = $pdo->prepare(
            "SELECT cost_usd_m2, labor_pct FROM construction_zone_costs
             WHERE city=? AND zone=? AND quality=? LIMIT 1"
        );
        $st->execute([$tryCity, $tryZone, $quality]);
        $row = $st->fetch();
        if ($row) {
            $costUsdM2  = (float)$row['cost_usd_m2'];
            $laborPct   = (float)$row['labor_pct'];
            $dataSource = $tryZone !== '' ? 'zone_specific'
                        : ($tryCity !== '' ? 'city_general' : 'national_default');
            break;
        }
    }
}

// Fallback hardcoded si no hay DB
if ($costUsdM2 === null) {
    $defaults = ['economica' => 650, 'estandar' => 980, 'calidad' => 1350, 'premium' => 2100];
    $costUsdM2  = $defaults[$quality];
    $dataSource = 'hardcoded_default';
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. MULTIPLICADORES DE AJUSTE
// ─────────────────────────────────────────────────────────────────────────────

// Área: economías/ineficiencias de escala
$areaFactor = 1.0;
if ($coveredArea < 40)        $areaFactor = 1.15;  // muy pequeña, costo fijo alto
elseif ($coveredArea < 70)    $areaFactor = 1.06;
elseif ($coveredArea <= 150)  $areaFactor = 1.00;  // rango óptimo
elseif ($coveredArea <= 250)  $areaFactor = 0.97;
else                          $areaFactor = 0.94;  // escala

// Tipo de propiedad
$typeFactor = 1.0;
switch ($propertyType) {
    case 'casa':         $typeFactor = 1.00; break;
    case 'departamento': $typeFactor = 1.08; break;  // edificio = más estructura
    case 'ph':           $typeFactor = 1.05; break;
    case 'duplex':       $typeFactor = 1.03; break;
    case 'local':        $typeFactor = 0.88; break;  // sin baño completo ni cocina
    case 'oficina':      $typeFactor = 0.92; break;
    case 'galpon':       $typeFactor = 0.65; break;  // estructura simple
    case 'terreno':      $typeFactor = 0.00; break;  // no aplica
}

if ($typeFactor == 0.0) {
    jout(['success' => false, 'error' => 'Tipo de propiedad no aplica (terreno)'], 400);
}

// Superficies no cubiertas (terraza, garage descubierto, jardín): 25% del costo
$uncovCostFactor = 0.25;

// ─────────────────────────────────────────────────────────────────────────────
// 3. COSTO TOTAL
// ─────────────────────────────────────────────────────────────────────────────
$baseUsdM2  = $costUsdM2 * $areaFactor * $typeFactor;
$baseUsdMin = $baseUsdM2 * 0.88;
$baseUsdMax = $baseUsdM2 * 1.12;

$costCovered   = $coveredArea  * $baseUsdM2;
$costUncovered = $uncoveredArea * ($baseUsdM2 * $uncovCostFactor);
$totalUsd      = $costCovered + $costUncovered;
$totalUsdMin   = $totalUsd * 0.88;
$totalUsdMax   = $totalUsd * 1.12;

// ARS
$totalArs    = $totalUsd    * $arsRate;
$totalArsMin = $totalUsdMin * $arsRate;
$totalArsMax = $totalUsdMax * $arsRate;

// ─────────────────────────────────────────────────────────────────────────────
// 4. BREAKDOWN POR RUBROS (porcentajes estándar de la construcción)
//    Fuente: CAC (Cámara Argentina de la Construcción), INDEC ICCV
// ─────────────────────────────────────────────────────────────────────────────
$breakdown_pct = [
    'estructura'        => 22.0,  // cimientos, columnas, vigas, losas
    'mamposteria'       => 18.0,  // paredes, revoques, contrapisos
    'cubierta'          => 11.0,  // techo, impermeabilización
    'inst_electricas'   => 8.5,   // tablero, cables, bocas
    'inst_sanitarias'   => 8.0,   // caños, artefactos, bomba
    'inst_gas'          => 4.5,   // caños, artefactos gas
    'terminaciones'     => 21.0,  // pisos, revestimientos, pintura, carpintería
    'honorarios'        => 4.5,   // arquitecto/maestro, planos, permisos
    'imprevistos'       => 2.5,   // contingencias
];

// Ajuste breakdown por tipo de propiedad
if ($propertyType === 'galpon') {
    $breakdown_pct = [
        'estructura'      => 35.0,
        'mamposteria'     => 15.0,
        'cubierta'        => 22.0,
        'inst_electricas' => 10.0,
        'inst_sanitarias' => 5.0,
        'inst_gas'        => 3.0,
        'terminaciones'   => 5.0,
        'honorarios'      => 3.0,
        'imprevistos'     => 2.0,
    ];
} elseif ($propertyType === 'local' || $propertyType === 'oficina') {
    $breakdown_pct = [
        'estructura'      => 24.0,
        'mamposteria'     => 17.0,
        'cubierta'        => 10.0,
        'inst_electricas' => 12.0,
        'inst_sanitarias' => 6.0,
        'inst_gas'        => 2.0,
        'terminaciones'   => 23.0,
        'honorarios'      => 4.0,
        'imprevistos'     => 2.0,
    ];
}

$rubro_labels = [
    'estructura'        => ['label' => 'Estructura', 'icon' => '🏗', 'desc' => 'Cimientos, columnas, vigas, losas'],
    'mamposteria'       => ['label' => 'Mampostería', 'icon' => '🧱', 'desc' => 'Paredes, revoques, contrapisos'],
    'cubierta'          => ['label' => 'Cubierta', 'icon' => '🏠', 'desc' => 'Techo, impermeabilización, aislación'],
    'inst_electricas'   => ['label' => 'Inst. Eléctricas', 'icon' => '⚡', 'desc' => 'Tablero, cables, bocas, llaves'],
    'inst_sanitarias'   => ['label' => 'Inst. Sanitarias', 'icon' => '🚿', 'desc' => 'Caños, artefactos, griferías'],
    'inst_gas'          => ['label' => 'Inst. Gas', 'icon' => '🔥', 'desc' => 'Caños, termotanque, calefacción'],
    'terminaciones'     => ['label' => 'Terminaciones', 'icon' => '🎨', 'desc' => 'Pisos, revestimientos, pintura, carpintería'],
    'honorarios'        => ['label' => 'Honorarios', 'icon' => '📐', 'desc' => 'Arquitecto/maestro, planos, permisos'],
    'imprevistos'       => ['label' => 'Imprevistos', 'icon' => '⚠️', 'desc' => 'Contingencias y ajustes'],
];

$breakdown = [];
foreach ($breakdown_pct as $rubro => $pct) {
    $cost = $totalUsd * ($pct / 100);
    $breakdown[$rubro] = [
        'pct'   => $pct,
        'usd'   => round($cost, 0),
        'ars'   => round($cost * $arsRate, 0),
        'label' => $rubro_labels[$rubro]['label'] ?? $rubro,
        'icon'  => $rubro_labels[$rubro]['icon']  ?? '•',
        'desc'  => $rubro_labels[$rubro]['desc']  ?? '',
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. MATERIALES PRINCIPALES (DB → region o global)
// ─────────────────────────────────────────────────────────────────────────────
$materials = [];
if ($pdo) {
    try {
        // Intenta materiales de la región específica, luego genéricos
        $st = $pdo->prepare(
            "SELECT category, material, unit, price_ars, price_usd, qty_per_m2, supplier, source_url, is_local
             FROM construction_materials
             WHERE active=1 AND (region=? OR region='')
             ORDER BY is_local DESC, category, price_usd ASC"
        );
        $st->execute([$city]);
        $rows = $st->fetchAll();

        foreach ($rows as $r) {
            $qtyTotal = round((float)$r['qty_per_m2'] * $coveredArea, 2);
            $costRow  = round((float)$r['price_usd'] * $qtyTotal, 2);
            $materials[] = [
                'category'   => $r['category'],
                'material'   => $r['material'],
                'unit'       => $r['unit'],
                'price_ars'  => (float)$r['price_ars'],
                'price_usd'  => (float)$r['price_usd'],
                'qty_per_m2' => (float)$r['qty_per_m2'],
                'qty_total'  => $qtyTotal,
                'cost_usd'   => $costRow,
                'cost_ars'   => round($costRow * $arsRate, 0),
                'supplier'   => $r['supplier'],
                'source_url' => $r['source_url'],
                'is_local'   => (bool)$r['is_local'],
            ];
        }
    } catch (\Throwable $e) {
        // sin materiales desde DB
    }
}

// Materiales embebidos si no hay DB (fallback básico)
if (empty($materials)) {
    $fallbackMats = [
        ['estructura', 'Cemento Portland 50kg',   'bolsa',   12500, 8.62,  4.5],
        ['mamposteria','Ladrillo cerámico (millar)','millar', 92000, 63.45, 0.10],
        ['mamposteria','Arena fina',               'm3',      38000, 26.21, 0.09],
        ['mamposteria','Cal hidratada 30kg',       'bolsa',   6200,  4.28,  1.8],
        ['estructura', 'Hierro Ø8mm × 12m',        'barra',  19500, 13.45, 0.45],
        ['cubierta',   'Teja colonial',            'unidad',  2600,  1.79, 18.0],
        ['terminaciones','Pintura látex 10L',      'lata',   39000, 26.90, 0.12],
        ['terminaciones','Porcelanato 60×60 (m²)', 'm2',     24000, 16.55, 0.60],
    ];
    foreach ($fallbackMats as [$cat, $mat, $unit, $ars, $usd, $qty]) {
        $qtyTotal = round($qty * $coveredArea, 2);
        $costRow  = round($usd * $qtyTotal, 2);
        $materials[] = [
            'category'   => $cat, 'material'   => $mat, 'unit'      => $unit,
            'price_ars'  => $ars, 'price_usd'  => $usd, 'qty_per_m2'=> $qty,
            'qty_total'  => $qtyTotal, 'cost_usd' => $costRow,
            'cost_ars'   => round($costRow * $arsRate, 0),
            'supplier'   => '', 'source_url' => '', 'is_local' => false,
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. ANÁLISIS DE FACTIBILIDAD / INCIDENCIA
// ─────────────────────────────────────────────────────────────────────────────
$feasibility = null;

if ($marketValueUsd > 0 && $coveredArea > 0) {
    // Valor del suelo estimado = valor de mercado - costo de construcción
    $landValueEst    = max(0, $marketValueUsd - $totalUsd);
    $landValueEstMin = max(0, $marketValueUsd - $totalUsdMax);
    $landValueEstMax = max(0, $marketValueUsd - $totalUsdMin);

    // Incidencia del terreno = terreno / valor total
    $incidenciaTierra = $marketValueUsd > 0
        ? round(($landValueEst / $marketValueUsd) * 100, 1)
        : 0;

    // ROI si construye y vende al precio de mercado
    // (precio venta - costo construccion - costo terreno) / (costo construccion + costo terreno)
    $totalInversion   = $totalUsd + $landValueEst;
    $roi = $totalInversion > 0
        ? round((($marketValueUsd - $totalInversion) / $totalInversion) * 100, 1)
        : 0;

    // Precio de venta mínimo para cubrir costos (punto de equilibrio)
    // Considera: construcción + terreno + honorarios 5% + impuestos estimados 3%
    $puntoEquilibrio = round($totalUsd * 1.08 + $landValueEst, 0);

    // Interpretación
    $incidenciaLabel = '';
    if ($incidenciaTierra < 15)     $incidenciaLabel = 'Terreno sub-valuado';
    elseif ($incidenciaTierra < 25) $incidenciaLabel = 'Incidencia normal (zona en desarrollo)';
    elseif ($incidenciaTierra < 40) $incidenciaLabel = 'Incidencia normal (zona consolidada)';
    elseif ($incidenciaTierra < 55) $incidenciaLabel = 'Terreno de alto valor';
    else                            $incidenciaLabel = 'Terreno premium (más valer que la obra)';

    $roiLabel = '';
    if ($roi < 0)       $roiLabel = 'No rentable a precio actual';
    elseif ($roi < 10)  $roiLabel = 'Margen ajustado';
    elseif ($roi < 20)  $roiLabel = 'Rentabilidad normal';
    elseif ($roi < 35)  $roiLabel = 'Buena rentabilidad';
    else                $roiLabel = 'Alta rentabilidad';

    // Comparativo: precio de compra vs costo de construcción
    $compraVsConstruccion = $totalUsd > 0 ? round($marketValueUsd / $totalUsd, 2) : 0;
    $conveniencia = '';
    if ($compraVsConstruccion < 0.85)      $conveniencia = 'Conviene construir — el precio de mercado está por debajo del costo de obra';
    elseif ($compraVsConstruccion < 1.05)  $conveniencia = 'Precio de mercado similar al costo de construcción';
    elseif ($compraVsConstruccion < 1.25)  $conveniencia = 'Precio de mercado razonable frente al costo de obra';
    else                                   $conveniencia = 'Conviene comprar — precio competitivo respecto de construir desde cero';

    $feasibility = [
        'land_value_est'        => round($landValueEst, 0),
        'land_value_est_min'    => round($landValueEstMin, 0),
        'land_value_est_max'    => round($landValueEstMax, 0),
        'land_value_est_ars'    => round($landValueEst * $arsRate, 0),
        'incidencia_tierra_pct' => $incidenciaTierra,
        'incidencia_label'      => $incidenciaLabel,
        'roi_pct'               => $roi,
        'roi_label'             => $roiLabel,
        'punto_equilibrio_usd'  => $puntoEquilibrio,
        'punto_equilibrio_ars'  => round($puntoEquilibrio * $arsRate, 0),
        'compra_vs_construccion'=> $compraVsConstruccion,
        'conveniencia'          => $conveniencia,
        'total_inversion_usd'   => round($totalInversion, 0),
        'total_inversion_ars'   => round($totalInversion * $arsRate, 0),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. MANO DE OBRA (desglose)
// ─────────────────────────────────────────────────────────────────────────────
$laborUsd     = $totalUsd * ($laborPct / 100);
$materialsUsd = $totalUsd - $laborUsd;

// ─────────────────────────────────────────────────────────────────────────────
// 8. RESPONSE
// ─────────────────────────────────────────────────────────────────────────────
$qualityLabels = [
    'economica' => 'Económica',
    'estandar'  => 'Estándar',
    'calidad'   => 'Calidad',
    'premium'   => 'Premium',
];

jout([
    'success'     => true,
    'quality'     => $quality,
    'quality_label'=> $qualityLabels[$quality] ?? $quality,
    'covered_area'  => $coveredArea,
    'uncovered_area'=> $uncoveredArea,

    // Costo total
    'cost_total'  => [
        'usd'     => round($totalUsd, 0),
        'usd_min' => round($totalUsdMin, 0),
        'usd_max' => round($totalUsdMax, 0),
        'ars'     => round($totalArs, 0),
        'ars_min' => round($totalArsMin, 0),
        'ars_max' => round($totalArsMax, 0),
    ],

    // Costo por m²
    'cost_per_m2' => [
        'usd'     => round($baseUsdM2, 0),
        'usd_min' => round($baseUsdMin, 0),
        'usd_max' => round($baseUsdMax, 0),
        'ars'     => round($baseUsdM2 * $arsRate, 0),
    ],

    // Mano de obra vs materiales
    'labor' => [
        'pct'   => $laborPct,
        'usd'   => round($laborUsd, 0),
        'ars'   => round($laborUsd * $arsRate, 0),
    ],
    'materials_cost' => [
        'pct'   => round(100 - $laborPct, 1),
        'usd'   => round($materialsUsd, 0),
        'ars'   => round($materialsUsd * $arsRate, 0),
    ],

    // Breakdown por rubro
    'breakdown'     => $breakdown,

    // Lista de materiales con precios
    'materials'     => $materials,

    // Análisis de factibilidad (solo si se envía market_value_usd)
    'feasibility'   => $feasibility,

    // Meta
    'data_source'   => $dataSource,
    'ars_rate'      => $arsRate,
    'adjustments'   => [
        'area_factor' => $areaFactor,
        'type_factor' => $typeFactor,
    ],
]);
