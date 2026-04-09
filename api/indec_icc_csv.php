<?php
/**
 * TasadorIA — api/indec_icc_csv.php
 * Parsea el CSV oficial del INDEC (ICC) subido por el usuario.
 *
 * Soporta el formato largo (long format) publicado en datos.gob.ar:
 *   periodo;nivel_general_aperturas;indice_icc
 *   1/1/2022;materiales;100
 *   1/2/2022;materiales;103,46
 *   ...
 *
 * POST multipart/form-data { file: <csv> }
 * → { success, date, value, prev_value, variation_pct, total_rows }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['ta_admin']) && !isset($_SESSION['bim_ok'])) {
    out(['success' => false, 'error' => 'No autorizado'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['success' => false, 'error' => 'Método incorrecto'], 405);
}
if (empty($_FILES['file']['tmp_name'])) {
    out(['success' => false, 'error' => 'No se recibió archivo']);
}

// ── Leer y normalizar encoding ───────────────────────────────
$raw = file_get_contents($_FILES['file']['tmp_name']);
if (!mb_check_encoding($raw, 'UTF-8')) {
    $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
}
$raw   = str_replace(["\r\n", "\r"], "\n", $raw);
$lines = array_values(array_filter(explode("\n", $raw), fn($l) => trim($l) !== ''));

if (count($lines) < 3) {
    out(['success' => false, 'error' => 'Archivo muy corto o vacío']);
}

// ── Detectar separador ───────────────────────────────────────
$sep = ';';
foreach ($lines as $l) {
    if (trim($l) !== '') {
        $sep = substr_count($l, ',') > substr_count($l, ';') ? ',' : ';';
        break;
    }
}

// ── Parsear encabezado ───────────────────────────────────────
function splitLine(string $line, string $sep): array {
    return array_map('trim', str_getcsv($line, $sep, '"'));
}

$header = splitLine($lines[0], $sep);
$header = array_map('strtolower', $header);

// Detectar columnas
$colPeriodo   = null;
$colCategoria = null;
$colValor     = null;

foreach ($header as $i => $h) {
    if (str_contains($h, 'periodo') || str_contains($h, 'período') || str_contains($h, 'date') || str_contains($h, 'fecha')) {
        $colPeriodo = $i;
    } elseif (str_contains($h, 'apertura') || str_contains($h, 'capitulo') || str_contains($h, 'categoria') || str_contains($h, 'nivel_general_apertura')) {
        $colCategoria = $i;
    } elseif (str_contains($h, 'indice') || str_contains($h, 'índice') || str_contains($h, 'valor') || str_contains($h, 'value') || str_contains($h, 'icc')) {
        $colValor = $i;
    }
}

// Fallback: asumir columnas por posición si no se detectaron por nombre
if ($colPeriodo   === null) $colPeriodo   = 0;
if ($colCategoria === null) $colCategoria = 1;
if ($colValor     === null) $colValor     = 2;

// ── Detectar si el CSV es "formato largo" (long) o "ancho" (wide) ──
// Formato largo: la columna de categoría tiene valores como "materiales", "nivel_general", etc.
// Formato ancho: hay columnas separadas por categoría

$isLong = false;
if ($colCategoria !== null) {
    // Revisar los primeros datos para ver si hay "materiales" como valor
    for ($i = 1; $i < min(10, count($lines)); $i++) {
        $cols = splitLine($lines[$i], $sep);
        $cat  = strtolower($cols[$colCategoria] ?? '');
        if (str_contains($cat, 'material') || str_contains($cat, 'nivel') || str_contains($cat, 'mano')) {
            $isLong = true;
            break;
        }
    }
}

// ── Parsear según formato ────────────────────────────────────
$dataRows = [];

if ($isLong) {
    // FORMATO LARGO: filtrar filas donde categoría == "materiales"
    for ($i = 1; $i < count($lines); $i++) {
        $cols = splitLine($lines[$i], $sep);
        if (count($cols) <= max($colPeriodo, $colCategoria, $colValor)) continue;

        $cat = strtolower($cols[$colCategoria] ?? '');
        if (!str_contains($cat, 'material')) continue;

        $period = parsePeriod($cols[$colPeriodo] ?? '');
        $val    = parseNum($cols[$colValor]     ?? '');
        if (!$period || $val <= 0) continue;

        $dataRows[$period] = $val;
    }
} else {
    // FORMATO ANCHO: buscar columna llamada "materiales"
    $colMat = null;
    foreach ($header as $i => $h) {
        if (str_contains($h, 'material')) { $colMat = $i; break; }
    }
    if ($colMat === null) {
        out(['success' => false, 'error' => 'No se encontró columna "materiales" ni formato largo reconocible. Encabezados detectados: ' . implode(', ', $header)]);
    }
    for ($i = 1; $i < count($lines); $i++) {
        $cols   = splitLine($lines[$i], $sep);
        $period = parsePeriod($cols[$colPeriodo] ?? '');
        $val    = parseNum($cols[$colMat]        ?? '');
        if (!$period || $val <= 0) continue;
        $dataRows[$period] = $val;
    }
}

if (empty($dataRows)) {
    out(['success' => false, 'error' => 'No se encontraron filas de materiales. Revisá que el archivo sea el correcto (Serie del Índice ICC, nivel general y capítulos).']);
}

// ── Tomar el más reciente ────────────────────────────────────
krsort($dataRows); // ordenar por clave (YYYY-MM) descendente
$periods = array_keys($dataRows);
$latest  = $periods[0];
$prev    = $periods[1] ?? null;

$latestVal = $dataRows[$latest];
$prevVal   = $prev ? $dataRows[$prev] : null;
$varPct    = ($prevVal && $prevVal > 0)
    ? round(($latestVal - $prevVal) / $prevVal * 100, 2)
    : null;

out([
    'success'       => true,
    'date'          => $latest,
    'value'         => $latestVal,
    'prev_value'    => $prevVal,
    'prev_date'     => $prev,
    'variation_pct' => $varPct,
    'total_rows'    => count($dataRows),
    'format'        => $isLong ? 'long' : 'wide',
    'source'        => 'csv:' . ($_FILES['file']['name'] ?? ''),
]);

// ── Helpers ──────────────────────────────────────────────────
function parseNum(string $s): float {
    $s = trim($s, " \t\"'");
    // Formato argentino: 1.373,39 → eliminar puntos de miles, convertir coma decimal
    if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $s)) {
        // 1.373,39 → 1373.39
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (preg_match('/^\d+(,\d+)?$/', $s)) {
        // 1373,39 → 1373.39
        $s = str_replace(',', '.', $s);
    }
    return floatval($s);
}

function parsePeriod(string $raw): ?string {
    $raw = trim($raw, " \t\"'");
    if (empty($raw)) return null;

    // D/M/YYYY (formato INDEC) → YYYY-MM
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m)) {
        return $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    }
    // YYYY-MM
    if (preg_match('/^(\d{4})-(\d{2})$/', $raw)) return $raw;
    // MM/YYYY
    if (preg_match('/^(\d{1,2})\/(\d{4})$/', $raw, $m)) {
        return $m[2] . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }
    // ene-26, feb-2026
    $meses = ['ene'=>'01','feb'=>'02','mar'=>'03','abr'=>'04','may'=>'05','jun'=>'06',
               'jul'=>'07','ago'=>'08','sep'=>'09','oct'=>'10','nov'=>'11','dic'=>'12'];
    if (preg_match('/^([a-z]{3})[\-\/](\d{2,4})$/i', $raw, $m)) {
        $mes = strtolower($m[1]);
        $anio = strlen($m[2]) === 2 ? '20' . $m[2] : $m[2];
        if (isset($meses[$mes])) return $anio . '-' . $meses[$mes];
    }
    return null;
}
