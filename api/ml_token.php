<?php
/**
 * TasadorIA — api/ml_token.php
 * Genera (o devuelve cacheado) un access_token de MercadoLibre
 * usando client_credentials desde settings.php.
 *
 * GET/POST  →  { "token": "APP_USR-...", "expires_in": 21600 }
 *              { "error": "..." }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];

// ── Autorización: solo admin o sesión BIM ──────────────────────
if (!isset($_SESSION['ta_admin']) && !isset($_SESSION['bim_ok'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']); exit;
}

// ── Credenciales ───────────────────────────────────────────────
$appId  = $cfg['mercadolibre']['app_id']        ?? '';
$secret = $cfg['mercadolibre']['client_secret'] ?? '';

if (!$appId || !$secret) {
    http_response_code(500);
    echo json_encode(['error' => 'Credenciales ML no configuradas en settings.php']); exit;
}

// ── Cache en sesión (tokens duran 6 h, renovamos con 5 min de margen) ─
$cached = $_SESSION['ml_token'] ?? null;
if ($cached && isset($cached['expires_at']) && time() < ($cached['expires_at'] - 300)) {
    echo json_encode(['token' => $cached['token'], 'cached' => true]); exit;
}

// ── Llamada OAuth a MercadoLibre ───────────────────────────────
$postData = http_build_query([
    'grant_type'    => 'client_credentials',
    'client_id'     => $appId,
    'client_secret' => $secret,
]);

$ch = curl_init('https://api.mercadolibre.com/oauth/token');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'User-Agent: TasadorIA/5.0',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'cURL error: ' . $curlErr]); exit;
}

$data = json_decode($body, true);

if ($httpCode !== 200 || empty($data['access_token'])) {
    http_response_code($httpCode ?: 502);
    $msg = $data['message'] ?? $data['error'] ?? 'Error desconocido de ML';
    echo json_encode(['error' => "ML OAuth $httpCode: $msg", 'raw' => $data]); exit;
}

// ── Cachear en sesión ──────────────────────────────────────────
$expiresIn = intval($data['expires_in'] ?? 21600);
$_SESSION['ml_token'] = [
    'token'      => $data['access_token'],
    'expires_at' => time() + $expiresIn,
];

echo json_encode([
    'token'      => $data['access_token'],
    'expires_in' => $expiresIn,
    'cached'     => false,
]);
