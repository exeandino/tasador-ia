<?php
// tasador/api/analyze.php
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }

function jsonOut(array $d): void { ob_end_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

try {
    $cfg  = require_once __DIR__ . '/../config/settings.php';
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $images = array_slice($data['images'] ?? [], 0, 6);

    if (!$cfg['ai']['enabled'] || empty($cfg['ai']['api_key'])) {
        jsonOut(['success' => true, 'score' => 0, 'summary' => 'IA no configurada.', 'details' => [], 'red_flags' => [], 'highlights' => []]);
    }
    if (empty($images)) {
        jsonOut(['success' => true, 'score' => 0, 'summary' => 'Sin fotos para analizar.', 'details' => [], 'red_flags' => [], 'highlights' => []]);
    }

    $propData = $data['property'] ?? [];
    $content  = [];
    $content[] = [
        'type' => 'text',
        'text' => "Sos un tasador inmobiliario experto argentino. Analizá estas fotografías de una propiedad.\n\n" .
            "DATOS: Tipo: " . ($propData['property_type'] ?? 'no especificado') . ", " .
            "Superficie: " . ($propData['covered_area'] ?? '?') . "m², " .
            "Antigüedad: " . ($propData['age_years'] ?? '?') . " años, " .
            "Estado declarado: " . ($propData['condition'] ?? 'bueno') . "\n\n" .
            "Respondé SOLO con este JSON exacto (sin texto extra, sin markdown):\n" .
            '{"score":[número -15 a +15],"confidence":"alta|media|baja","summary":"resumen 1 oración",' .
            '"details":[{"aspect":"Terminaciones","rating":4,"note":"nota breve"},{"aspect":"Luminosidad","rating":3,"note":"nota"},{"aspect":"Materiales","rating":4,"note":"nota"},{"aspect":"Presentación","rating":5,"note":"nota"}],' .
            '"red_flags":["lista vacía si no hay problemas"],"highlights":["punto fuerte 1"]}'
    ];

    foreach ($images as $img) {
        if (str_starts_with((string)$img, 'data:image/')) {
            if (preg_match('/^data:(image\/\w+);base64,(.+)$/', $img, $m)) {
                $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]]];
            }
        } elseif (filter_var($img, FILTER_VALIDATE_URL)) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'url', 'url' => $img]];
        }
    }

    // Llamar Anthropic
    $payload = ['model' => $cfg['ai']['model'], 'max_tokens' => 600, 'messages' => [['role' => 'user', 'content' => $content]]];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $cfg['ai']['api_key'],
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) throw new \Exception("Anthropic API error HTTP {$code}");

    $respData = json_decode($resp, true);
    $text = trim($respData['content'][0]['text'] ?? '');
    $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
    $text = preg_replace('/\s*```$/m', '', trim($text));
    $result = json_decode($text, true);

    if (!$result || !isset($result['score'])) throw new \Exception('IA devolvió respuesta no parseable');

    $result['score']   = max(-15, min(15, (int)$result['score']));
    $result['success'] = true;
    jsonOut($result);

} catch (\Throwable $e) {
    jsonOut(['success' => true, 'score' => 0, 'summary' => 'Sin ajuste por fotos (error IA: ' . $e->getMessage() . ')', 'details' => [], 'red_flags' => [], 'highlights' => []]);
}
