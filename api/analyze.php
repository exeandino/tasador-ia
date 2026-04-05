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
    $mode = $data['mode'] ?? 'photos'; // 'photos' | 'docs'

    if (!$cfg['ai']['enabled'] || empty($cfg['ai']['api_key'])) {
        jsonOut(['success' => true, 'score' => 0, 'notes' => null, 'summary' => 'IA no configurada.', 'details' => [], 'red_flags' => [], 'highlights' => []]);
    }
    if (empty($images)) {
        jsonOut(['success' => true, 'score' => 0, 'notes' => null, 'summary' => 'Sin imágenes para analizar.', 'details' => [], 'red_flags' => [], 'highlights' => []]);
    }

    $propData = $data['property'] ?? [];
    $content  = [];

    // ── Modo documentos ───────────────────────────────────────────────────────
    if ($mode === 'docs') {
        $content[] = [
            'type' => 'text',
            'text' => "Sos un tasador inmobiliario experto argentino. Te muestro imágenes de documentos del inmueble " .
                "(boletas de expensas, ABL, planos, escrituras, servicios, etc.).\n\n" .
                "Tipo de propiedad: " . ($propData['property_type'] ?? 'no especificado') . ", " .
                "Superficie: " . ($propData['covered_area'] ?? '?') . "m²\n\n" .
                "Extraé SOLO datos relevantes para la tasación: " .
                "- Monto de expensas (si hay boleta de expensas)\n" .
                "- Estado del inmueble según documentos\n" .
                "- Superficie según plano (si hay plano)\n" .
                "- Año de escritura o posesión (si hay escritura)\n" .
                "- Deudas o restricciones (hipotecas, embargos)\n" .
                "- Cualquier dato que afecte el valor de tasación\n\n" .
                "Respondé SOLO con este JSON (sin texto extra, sin markdown):\n" .
                '{"notes":"resumen breve de lo encontrado, máx 150 caracteres o null si no hay datos útiles","expensas_ars":null,"superficie_plano":null,"year_escritura":null}'
        ];
        foreach ($images as $img) {
            if (str_starts_with((string)$img, 'data:image/')) {
                if (preg_match('/^data:(image\/\w+);base64,(.+)$/', $img, $m)) {
                    $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]]];
                }
            }
        }
        $payload = ['model' => $cfg['ai']['model'], 'max_tokens' => 300, 'messages' => [['role' => 'user', 'content' => $content]]];
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),
            CURLOPT_HTTPHEADER=>['x-api-key: '.$cfg['ai']['api_key'],'anthropic-version: 2023-06-01','content-type: application/json'],
            CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) throw new \Exception("API error {$code}");
        $respData = json_decode($resp, true);
        $text = trim($respData['content'][0]['text'] ?? '');
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', trim($text));
        $result = json_decode($text, true) ?? [];
        jsonOut(['success'=>true,'notes'=>$result['notes']??null,'expensas_ars'=>$result['expensas_ars']??null,'superficie_plano'=>$result['superficie_plano']??null]);
    }

    // ── Modo video (fotogramas extraídos) ─────────────────────────────────────
    if ($mode === 'video') {
        $content[] = [
            'type' => 'text',
            'text' => "Sos un tasador inmobiliario experto argentino. Analizá estos fotogramas extraídos de un video filmado de la propiedad.\n\n" .
                "Tipo: " . ($propData['property_type'] ?? 'no especificado') . ", Superficie: " . ($propData['covered_area'] ?? '?') . "m²\n\n" .
                "Son fotogramas consecutivos del mismo video, evalúalos como un conjunto.\n" .
                "Respondé SOLO con este JSON exacto (sin texto extra, sin markdown):\n" .
                '{"score":[número -15 a +15],"confidence":"alta|media|baja","summary":"resumen 1 oración del video",' .
                '"details":[{"aspect":"Terminaciones","rating":4,"note":"nota"},{"aspect":"Espacios","rating":3,"note":"nota"},{"aspect":"Iluminación","rating":4,"note":"nota"},{"aspect":"Estado general","rating":5,"note":"nota"}],' .
                '"red_flags":[],"highlights":["punto fuerte"]}'
        ];
        foreach (array_slice($images, 0, 8) as $img) {
            if (preg_match('/^data:(image\/\w+);base64,(.+)$/', (string)$img, $m)) {
                $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]]];
            }
        }
        $payload = ['model' => $cfg['ai']['model'], 'max_tokens' => 500, 'messages' => [['role' => 'user', 'content' => $content]]];
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),
            CURLOPT_HTTPHEADER=>['x-api-key: '.$cfg['ai']['api_key'],'anthropic-version: 2023-06-01','content-type: application/json'],
            CURLOPT_TIMEOUT=>45,CURLOPT_SSL_VERIFYPEER=>true]);
        $resp = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($httpCode !== 200) throw new \Exception("API error {$httpCode}");
        $respData = json_decode($resp, true);
        $text = trim($respData['content'][0]['text'] ?? '');
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', trim($text));
        $result = json_decode($text, true) ?? [];
        $result['score'] = max(-15, min(15, (int)($result['score'] ?? 0)));
        $result['success'] = true;
        jsonOut($result);
    }

    // ── Modo fotos (default) ──────────────────────────────────────────────────
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
