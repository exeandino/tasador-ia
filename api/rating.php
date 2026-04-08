<?php
/**
 * TasadorIA — Rating de precio
 * Recibe: { code: "TA-XXXXXXXX", rating: "caro|justo|barato" }
 * Guarda en la columna `rating` de la tabla tasaciones
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }

function jsonOut(array $d): void { ob_end_clean(); echo json_encode($d); exit; }

try {
    $cfg  = require_once __DIR__ . '/../config/settings.php';
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $code   = trim($data['code']   ?? '');
    $rating = trim($data['rating'] ?? '');

    if (!in_array($rating, ['caro', 'justo', 'barato'], true)) {
        jsonOut(['success' => false, 'error' => 'rating inválido']);
    }

    $db = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Agregar columna si no existe (primera vez)
    try {
        $db->exec("ALTER TABLE tasaciones ADD COLUMN IF NOT EXISTS rating VARCHAR(10) DEFAULT NULL");
    } catch (\Throwable $ignored) {}

    if ($code) {
        $db->prepare("UPDATE tasaciones SET rating = ? WHERE code = ? LIMIT 1")
           ->execute([$rating, $code]);
    }

    jsonOut(['success' => true]);

} catch (\Throwable $e) {
    jsonOut(['success' => false, 'error' => $e->getMessage()]);
}
