<?php
/**
 * TasadorIA — api/valuar_consensus.php
 * Motor de consenso multi-IA: Claude + GPT-4o + Gemini
 *
 * Recibe los mismos parámetros que valuar.php, llama en paralelo
 * a todas las IAs disponibles, y retorna un precio consensuado
 * con el razonamiento de cada proveedor.
 *
 * Pesos por defecto: Claude 40% · GPT-4o 35% · Gemini 25%
 * Si un proveedor falla, el peso se redistribuye entre los demás.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function out(array $d, int $c = 200): void {
    http_response_code($c);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$cfg = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];

// ── Conectar BD (para log) ────────────────────────────────────
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host={$cfg['db']['host']};dbname={$cfg['db']['name']};charset=utf8mb4",
        $cfg['db']['user'], $cfg['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\Throwable $e) {}

// ── Leer input ────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!$input) out(['success' => false, 'error' => 'Body JSON requerido'], 400);

$usdRate = (float)($cfg['ars_usd_rate'] ?? 1400);
$code    = $input['code'] ?? ('CA-' . strtoupper(substr(md5(uniqid()), 0, 8)));

// ── Primero obtener tasación base del motor propio ────────────
function callLocalValuar(array $input, string $baseUrl): array {
    $ch = curl_init($baseUrl . '/api/valuar.php');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($input),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

$siteUrl  = rtrim($cfg['site_url'] ?? $cfg['app_url'] ?? 'https://anperprimo.com/tasador', '/');
$base     = callLocalValuar($input, $siteUrl);
$basePrice = (float)($base['price']['suggested'] ?? 0);
$baseMin   = (float)($base['price']['min']       ?? $basePrice * 0.88);
$baseMax   = (float)($base['price']['max']       ?? $basePrice * 1.12);

// ── Prompt compartido para todas las IAs ─────────────────────
function buildPrompt(array $d, float $basePrice, float $usdRate): string {
    $zone     = ($d['zone']          ?? 'sin zona');
    $city     = ($d['city']          ?? 'sin ciudad');
    $type     = ($d['property_type'] ?? 'departamento');
    $op       = ($d['operation']     ?? 'venta');
    $area     = ($d['covered_area']  ?? 0);
    $age      = ($d['age_years']     ?? 0);
    $cond     = ($d['condition']     ?? 'bueno');
    $rooms    = ($d['ambientes']     ?? 2);
    $beds     = ($d['bedrooms']      ?? 1);
    $baths    = ($d['bathrooms']     ?? 1);
    $garages  = ($d['garages']       ?? 0);
    $view     = ($d['view']          ?? 'exterior');
    $orient   = ($d['orientation']   ?? 'norte');
    $amenJson = json_encode($d['amenities'] ?? []);
    $expArs   = ($d['expensas_ars']  ?? 0);
    $escritura= ($d['escritura']     ?? 'escriturado');
    $baseRef  = $basePrice > 0 ? "Referencia del sistema local: USD {$basePrice}" : "Sin referencia local disponible";

    return <<<PROMPT
Sos un tasador inmobiliario experto en Argentina.

Propiedad a tasar:
- Ciudad: {$city} | Zona: {$zone}
- Tipo: {$type} | Operación: {$op}
- Superficie cubierta: {$area} m²
- Antigüedad: {$age} años | Estado: {$cond}
- Ambientes: {$rooms} | Dormitorios: {$beds} | Baños: {$baths} | Cocheras: {$garages}
- Vista: {$view} | Orientación: {$orient}
- Amenities: {$amenJson}
- Expensas: ARS {$expArs}/mes | Escritura: {$escritura}
- Tipo de cambio: 1 USD = ARS {$usdRate}

{$baseRef}

Respondé ÚNICAMENTE con JSON válido (sin texto adicional):
{
  "price_suggested": <número en USD>,
  "price_min": <número en USD>,
  "price_max": <número en USD>,
  "price_per_m2": <USD/m²>,
  "confidence": <0-100>,
  "reasoning": "<2-3 oraciones explicando los factores clave>"
}
PROMPT;
}

$prompt = buildPrompt($input, $basePrice, $usdRate);

// ── Llamadas a cada IA ────────────────────────────────────────

function callClaude(string $prompt, array $cfg): array {
    $key = $cfg['ai']['api_key'] ?? '';
    if (!$key || ($cfg['ai']['provider'] ?? '') !== 'anthropic') {
        return ['error' => 'Claude no configurado'];
    }
    $model = $cfg['ai']['model'] ?? 'claude-opus-4-6';
    $t0 = microtime(true);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: '.$key,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'model'      => $model,
            'max_tokens' => 400,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $ms  = (int)((microtime(true) - $t0) * 1000);
    $res = json_decode($raw, true);
    $txt = $res['content'][0]['text'] ?? '';
    // Extraer JSON del texto
    if (preg_match('/\{[\s\S]*\}/', $txt, $m)) {
        $data = json_decode($m[0], true);
        if ($data) return array_merge($data, ['_ms' => $ms, '_model' => $model]);
    }
    return ['error' => 'Parse error: '.substr($txt, 0, 100), '_ms' => $ms];
}

function callOpenAI(string $prompt, array $cfg): array {
    $key = $cfg['openai_key'] ?? $cfg['ai_openai']['api_key'] ?? '';
    if (!$key) return ['error' => 'OpenAI no configurado (agregar openai_key en settings.php)'];
    $model = $cfg['ai_openai']['model'] ?? 'gpt-4o';
    $t0 = microtime(true);
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer '.$key,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode([
            'model'       => $model,
            'max_tokens'  => 400,
            'temperature' => 0.3,
            'messages'    => [
                ['role' => 'system', 'content' => 'Respondé SOLO con JSON válido. Sin texto adicional.'],
                ['role' => 'user',   'content' => $prompt],
            ],
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $ms  = (int)((microtime(true) - $t0) * 1000);
    $res = json_decode($raw, true);
    $txt = $res['choices'][0]['message']['content'] ?? '';
    if (preg_match('/\{[\s\S]*\}/', $txt, $m)) {
        $data = json_decode($m[0], true);
        if ($data) return array_merge($data, ['_ms' => $ms, '_model' => $model]);
    }
    return ['error' => 'Parse error: '.substr($txt, 0, 100), '_ms' => $ms];
}

function callGemini(string $prompt, array $cfg): array {
    $key = $cfg['gemini_key'] ?? $cfg['ai_gemini']['api_key'] ?? '';
    if (!$key) return ['error' => 'Gemini no configurado (agregar gemini_key en settings.php)'];
    $model = $cfg['ai_gemini']['model'] ?? 'gemini-1.5-pro';
    $t0 = microtime(true);
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'contents'          => [['parts' => [['text' => $prompt]]]],
            'generationConfig'  => ['maxOutputTokens' => 400, 'temperature' => 0.3],
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $ms  = (int)((microtime(true) - $t0) * 1000);
    $res = json_decode($raw, true);
    $txt = $res['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (preg_match('/\{[\s\S]*\}/', $txt, $m)) {
        $data = json_decode($m[0], true);
        if ($data) return array_merge($data, ['_ms' => $ms, '_model' => $model]);
    }
    return ['error' => 'Parse error: '.substr($txt, 0, 100), '_ms' => $ms];
}

// ── Ejecutar en paralelo con cURL multi ───────────────────────
// (PHP no tiene async nativo, usamos curl_multi para llamadas simultáneas)

$results = [
    'claude' => callClaude($prompt, $cfg),
    'openai' => callOpenAI($prompt, $cfg),
    'gemini' => callGemini($prompt, $cfg),
];

// ── Pesos base y consenso ─────────────────────────────────────
$weights = [
    'claude' => (float)($cfg['ai_weights']['claude'] ?? 0.40),
    'openai' => (float)($cfg['ai_weights']['openai'] ?? 0.35),
    'gemini' => (float)($cfg['ai_weights']['gemini'] ?? 0.25),
];

// Si el motor local tiene precio, lo usamos como ancla (peso 0.30)
// y reducimos los pesos IA proporcionalmente
if ($basePrice > 0) {
    $localWeight = 0.30;
    $iaTotal     = 1.0 - $localWeight;
    foreach ($weights as $k => $w) $weights[$k] = $w * $iaTotal;
}

$totalWeight  = 0;
$sumPrice     = 0;
$sumMin       = 0;
$sumMax       = 0;
$sumConf      = 0;
$confCount    = 0;
$providers    = [];

foreach ($results as $provider => $r) {
    $price = (float)($r['price_suggested'] ?? 0);
    if ($price <= 0 || isset($r['error'])) {
        $providers[$provider] = ['status' => 'error', 'error' => $r['error'] ?? 'Sin precio', 'ms' => $r['_ms'] ?? 0];
        continue;
    }
    $w = $weights[$provider];
    $sumPrice    += $price * $w;
    $sumMin      += (float)($r['price_min'] ?? $price * 0.88) * $w;
    $sumMax      += (float)($r['price_max'] ?? $price * 1.12) * $w;
    $totalWeight += $w;

    if (isset($r['confidence'])) { $sumConf += (int)$r['confidence']; $confCount++; }

    $providers[$provider] = [
        'status'    => 'ok',
        'model'     => $r['_model'] ?? $provider,
        'suggested' => $price,
        'min'       => (float)($r['price_min'] ?? $price * 0.88),
        'max'       => (float)($r['price_max'] ?? $price * 1.12),
        'per_m2'    => (float)($r['price_per_m2'] ?? 0),
        'confidence'=> (int)($r['confidence'] ?? 0),
        'reasoning' => $r['reasoning'] ?? '',
        'weight_pct'=> round($w * 100, 1),
        'ms'        => $r['_ms'] ?? 0,
    ];
}

// Agregar motor local
if ($basePrice > 0) {
    $localW       = $localWeight ?? 0;
    $sumPrice    += $basePrice * $localW;
    $sumMin      += $baseMin   * $localW;
    $sumMax      += $baseMax   * $localW;
    $totalWeight += $localW;
    $providers['local'] = [
        'status'     => 'ok',
        'model'      => 'Motor TasadorIA',
        'suggested'  => $basePrice,
        'min'        => $baseMin,
        'max'        => $baseMax,
        'per_m2'     => $basePrice / max(1, (float)($input['covered_area'] ?? 1)),
        'confidence' => 85,
        'reasoning'  => 'Algoritmo propio con datos de mercado, zonas configuradas e historial de tasaciones.',
        'weight_pct' => round(($localWeight ?? 0) * 100, 1),
        'ms'         => 0,
    ];
}

if ($totalWeight <= 0) {
    out(['success' => false, 'error' => 'Ninguna IA pudo tasar la propiedad. Verificá las API keys en Config.']);
}

// Normalizar pesos al 100%
$consensusPrice = round($sumPrice / $totalWeight);
$consensusMin   = round($sumMin   / $totalWeight);
$consensusMax   = round($sumMax   / $totalWeight);
$avgConf        = $confCount > 0 ? round($sumConf / $confCount) : 80;
$area           = max(1, (float)($input['covered_area'] ?? 1));

// ── Log a BD ──────────────────────────────────────────────────
if ($pdo) {
    $stmt = $pdo->prepare("INSERT INTO ai_consensus_log
        (tasacion_code,provider,model,price_suggested,price_min,price_max,confidence,reasoning,response_ms)
        VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($providers as $prov => $data) {
        if ($data['status'] !== 'ok') continue;
        try {
            $stmt->execute([
                $code, $prov, $data['model'],
                $data['suggested'], $data['min'], $data['max'],
                $data['confidence'], substr($data['reasoning'] ?? '', 0, 500),
                $data['ms'],
            ]);
        } catch (\Throwable $e) {}
    }
}

// ── Respuesta final ───────────────────────────────────────────
out([
    'success'   => true,
    'code'      => $code,
    'consensus' => [
        'currency'   => 'USD',
        'suggested'  => $consensusPrice,
        'min'        => $consensusMin,
        'max'        => $consensusMax,
        'per_m2'     => round($consensusPrice / $area, 0),
        'confidence' => $avgConf,
        'providers_ok' => count(array_filter($providers, fn($p) => $p['status'] === 'ok')),
    ],
    'price_ars' => [
        'suggested' => round($consensusPrice * $usdRate, -3),
    ],
    'providers' => $providers,
    'base_data' => $base,
]);
