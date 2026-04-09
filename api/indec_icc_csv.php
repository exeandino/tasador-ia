<?php
/**
 * TasadorIA — api/indec_icc_csv.php
 * Parsea el CSV oficial del INDEC (ICC) subido por el usuario.
 *
 * POST multipart/form-data { file: <csv> }
 * → { success, date, value, column_name, source }
 *
 * Archivos soportados:
 *   - "Serie del Índice del ICC, nivel general y capítulos"
 *   - separador ; o , · encoding UTF-8 / Latin-1
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

$tmpPath = $_FILES['file']['tmp_name'];
$rawName = $_FILES['file']['name'] ?? '';

// Leer contenido — detectar encoding y convertir a UTF-8
$raw = file_get_contents($tmpPath);
if (!mb_check_encoding($raw, 'UTF-8')) {
    $raw = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
}

// Normalizar saltos de línea
$raw = str_replace(["\r\n", "\r"], "\n", $raw);

$lines = explode("\n", $raw);
$lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

if (count($lines) < 3) {
    out(['success' => false, 'error' => 'Archivo muy corto o vacío']);
}

// ── Detectar separador ───────────────────────────────────────
$sep = ';';
if (substr_count($lines[0], ',') > substr_count($lines[0], ';')) {
    $sep = ',';
}

// ── Parsear CSV línea a línea ────────────────────────────────
function parseCsvLine(string $line, string $sep): array {
    // Soporte básico para campos entre comillas
    return array_map(fn($v) => trim($v, " \t\"'"), str_getcsv($line, $sep, '"'));
}

// ── Encontrar fila de encabezado ─────────────────────────────
$headerIdx = null;
$header    = [];

foreach ($lines as $i => $line) {
    $cols = parseCsvLine($line, $sep);
    foreach ($cols as $col) {
        $lower = strtolower(trim($col));
        // La fila de encabezado tiene "materiales" o "período" o "periodo"
        if (strpos($lower, 'material') !== false
         || strpos($lower, 'period') !== false
         || strpos($lower, 'índice') !== false
         || strpos($lower, 'indice') !== false) {
            $headerIdx = $i;
            $header    = $cols;
            break 2;
        }
    }
}

if ($headerIdx === null) {
    out(['success' => false, 'error' => 'No se encontró fila de encabezados (buscando "Materiales" / "Período")']);
}

// ── Detectar columna de Materiales ───────────────────────────
$colMat  = null;
$colDate = null;
$colName = '';

foreach ($header as $ci => $colLabel) {
    $lower = strtolower(trim($colLabel));
    if ($colDate === null && (strpos($lower, 'period') !== false || $lower === '' && $ci === 0)) {
        $colDate = $ci;
    }
    if ($colMat === null && strpos($lower, 'material') !== false) {
        $colMat  = $ci;
        $colName = trim($colLabel);
    }
}

// Si no encontró columna de materiales, intentar la segunda columna numérica
if ($colMat === null) {
    // En el CSV de INDEC la estructura suele ser:
    // Período | Nivel general | Materiales | Mano de obra | Gastos generales
    // Intento columna 2 (índice 2) como fallback
    $colMat  = 2;
    $colName = $header[2] ?? 'Columna 3';
}

if ($colDate === null) $colDate = 0;

// ── Leer filas de datos ───────────────────────────────────────
$dataRows = [];
for ($i = $headerIdx + 1; $i < count($lines); $i++) {
    $cols = parseCsvLine($lines[$i], $sep);
    if (empty($cols) || count($cols) <= max($colDate, $colMat)) continue;

    $dateRaw = trim($cols[$colDate]);
    $valRaw  = trim($cols[$colMat]);

    // Limpiar valor: quitar puntos de miles, convertir coma decimal a punto
    $valClean = str_replace(['.', ','], ['', '.'], $valRaw);
    // Si tiene más de un punto, el primero era separador de miles
    if (substr_count($valRaw, '.') > 1) {
        $valClean = str_replace('.', '', substr($valRaw, 0, strrpos($valRaw, '.')))
                  . '.'
                  . substr($valRaw, strrpos($valRaw, '.') + 1);
    }

    $val = floatval($valClean);
    if ($val <= 0) continue;

    // Normalizar período: "ene-26" → "2026-01", "2026-01" queda igual
    $period = normalizePeriod($dateRaw);
    if (!$period) continue;

    $dataRows[] = ['date' => $period, 'value' => $val, 'raw_date' => $dateRaw];
}

if (empty($dataRows)) {
    out(['success' => false, 'error' => 'No se encontraron valores numéricos en la columna de Materiales']);
}

// ── Ordenar y tomar el más reciente ──────────────────────────
usort($dataRows, fn($a, $b) => strcmp($b['date'], $a['date']));
$latest = $dataRows[0];
$prev   = $dataRows[1] ?? null;

$varPct = ($prev && $prev['value'] > 0)
    ? round(($latest['value'] - $prev['value']) / $prev['value'] * 100, 2)
    : null;

out([
    'success'       => true,
    'date'          => $latest['date'],
    'value'         => $latest['value'],
    'prev_value'    => $prev['value'] ?? null,
    'variation_pct' => $varPct,
    'column_name'   => $colName,
    'total_rows'    => count($dataRows),
    'source'        => 'csv:' . $rawName,
]);

// ── Función de normalización de período ─────────────────────
function normalizePeriod(string $raw): ?string {
    $raw = trim($raw);
    if (empty($raw)) return null;

    // Formato YYYY-MM ya normalizado
    if (preg_match('/^(\d{4})-(\d{2})$/', $raw)) return $raw;

    // Formato MM/YYYY o MM-YYYY
    if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $raw, $m)) {
        return $m[2] . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }

    // Formato "ene-26", "feb-2026", etc. (INDEC usa abreviaturas en español)
    $meses = [
        'ene'=>'01','feb'=>'02','mar'=>'03','abr'=>'04','may'=>'05','jun'=>'06',
        'jul'=>'07','ago'=>'08','sep'=>'09','oct'=>'10','nov'=>'11','dic'=>'12',
        'jan'=>'01','jun'=>'06','jul'=>'07','aug'=>'08','sep'=>'09','oct'=>'10',
        'nov'=>'11','dec'=>'12',
    ];
    if (preg_match('/^([a-záéíóú]{3})[\-\s](\d{2,4})$/i', $raw, $m)) {
        $mes  = strtolower($m[1]);
        $anio = strlen($m[2]) === 2 ? '20' . $m[2] : $m[2];
        if (isset($meses[$mes])) return $anio . '-' . $meses[$mes];
    }

    // Formato "Enero 2026", "enero de 2026"
    $mesesLargo = [
        'enero'=>'01','febrero'=>'02','marzo'=>'03','abril'=>'04','mayo'=>'05',
        'junio'=>'06','julio'=>'07','agosto'=>'08','septiembre'=>'09',
        'octubre'=>'10','noviembre'=>'11','diciembre'=>'12',
    ];
    foreach ($mesesLargo as $nombre => $num) {
        if (stripos($raw, $nombre) !== false && preg_match('/(\d{4})/', $raw, $m)) {
            return $m[1] . '-' . $num;
        }
    }

    // Número puro (no es fecha)
    return null;
}
