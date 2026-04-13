<?php
// tasador/api/analyze.php — Análisis IA de fotos/docs de propiedad (multi-proveedor)
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ob_end_clean(); http_response_code(204); exit; }

function jsonOut(array $d): void { ob_end_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

require_once __DIR__ . '/ai_provider.php';

/** Extrae base64 puro + mime de un array de data-URIs o URLs */
function parseImages(array $rawImages, bool $allowUrls = false): array {
    $b64 = []; $mimes = [];
    foreach ($rawImages as $img) {
        $img = (string)$img;
        if (preg_match('/^data:(image\/[\w]+);base64,(.+)$/', $img, $m)) {
            $mimes[] = $m[1];
            $b64[]   = $m[2];
        } elseif ($allowUrls && filter_var($img, FILTER_VALIDATE_URL)) {
            // Descargar URL → base64 para compatibilidad multi-proveedor
            $raw = @file_get_contents($img);
            if ($raw) {
                $mimes[] = 'image/jpeg';
                $b64[]   = base64_encode($raw);
            }
        }
    }
    return [$b64, $mimes];
}

/** Limpia respuesta de markdown y extrae JSON */
function cleanJson(string $text): ?array {
    $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
    $text = preg_replace('/\s*```\s*$/m', '', trim($text));
    if (preg_match('/(\{[\s\S]+\})/m', $text, $jm)) $text = $jm[1];
    $r = json_decode($text, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($r)) ? $r : null;
}

try {
    $cfg    = is_file(__DIR__.'/../config/settings.php') ? require __DIR__.'/../config/settings.php' : [];
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $images = array_slice($data['images'] ?? [], 0, 8);
    $mode   = $data['mode'] ?? 'photos'; // 'photos' | 'docs' | 'video'

    $aiCfg  = $cfg['ai'] ?? [];
    // Verificar que IA esté habilitada y haya al menoa una key
    $aiEnabled = !empty($aiCfg['enabled']);
    $hasAnyKey = !empty($aiCfg['api_key']);
    if (!$hasAnyKey && !empty($aiCfg['providers'])) {
        foreach ($aiCfg['providers'] as $p) {
            if (!empty($p['api_key'])) { $hasAnyKey = true; break; }
        }
    }

    if (!$aiEnabled || !$hasAnyKey || empty($images)) {
        $why = !$aiEnabled ? 'IA no habilitada.' : (!$hasAnyKey ? 'Sin API key configurada.' : 'Sin imágenes.');
        jsonOut(['success' => true, 'score' => 0, 'notes' => null,
                 'summary' => $why, 'details' => [], 'red_flags' => [], 'highlights' => []]);
    }

    $propData = $data['property'] ?? [];
    $propDesc = "Tipo: " . ($propData['property_type'] ?? 'no especificado') .
                ", Superficie: " . ($propData['covered_area'] ?? '?') . "m²" .
                ", Antigüedad: " . ($propData['age_years'] ?? '?') . " años" .
                ", Estado declarado: " . ($propData['condition'] ?? 'bueno');

    // ── Modo documentos ───────────────────────────────────────────────────────
    if ($mode === 'docs') {
        [$b64, $mimes] = parseImages($images);
        $sys  = "Sos un tasador inmobiliario experto argentino especializado en análisis de documentos.";
        $user = "Analizá estas imágenes de documentos del inmueble (boletas de expensas, ABL, planos, escrituras, servicios, etc.).\n\n" .
                $propDesc . "\n\n" .
                "Extraé SOLO datos relevantes para la tasación:\n" .
                "- Monto de expensas (si hay boleta)\n- Estado según documentos\n" .
                "- Superficie según plano\n- Año de escritura\n- Deudas o restricciones\n\n" .
                "Respondé SOLO con este JSON (sin texto extra, sin markdown):\n" .
                '{"notes":"resumen breve de lo encontrado, máx 150 caracteres o null","expensas_ars":null,"superficie_plano":null,"year_escritura":null}';

        $r = ai_call($cfg, $sys, $user, $b64, $mimes);
        if (!$r['ok']) throw new \Exception($r['error']);
        $result = cleanJson($r['text']) ?? [];
        jsonOut(['success' => true, 'notes' => $result['notes'] ?? null,
                 'expensas_ars' => $result['expensas_ars'] ?? null,
                 'superficie_plano' => $result['superficie_plano'] ?? null]);
    }

    // ── Modo video (fotogramas) ───────────────────────────────────────────────
    if ($mode === 'video') {
        [$b64, $mimes] = parseImages(array_slice($images, 0, 8));
        $sys  = "Sos un tasador inmobiliario experto argentino especializado en análisis visual de propiedades.";
        $user = "Analizá estos fotogramas extraídos de un video filmado de la propiedad.\n\n" .
                $propDesc . "\n\n" .
                "Son fotogramas consecutivos del mismo video, evalúalos como un conjunto.\n" .
                "Respondé SOLO con este JSON exacto (sin texto extra, sin markdown):\n" .
                '{"score":[número -15 a +15],"confidence":"alta|media|baja","summary":"resumen 1 oración del video",' .
                '"details":[{"aspect":"Terminaciones","rating":4,"note":"nota"},{"aspect":"Espacios","rating":3,"note":"nota"},{"aspect":"Iluminación","rating":4,"note":"nota"},{"aspect":"Estado general","rating":5,"note":"nota"}],' .
                '"red_flags":[],"highlights":["punto fuerte"]}';

        $r = ai_call($cfg, $sys, $user, $b64, $mimes);
        if (!$r['ok']) throw new \Exception($r['error']);
        $result = cleanJson($r['text']) ?? [];
        $result['score']   = max(-15, min(15, (int)($result['score'] ?? 0)));
        $result['success'] = true;
        jsonOut($result);
    }

    // ── Modo fotos (default) ──────────────────────────────────────────────────
    [$b64, $mimes] = parseImages($images, true);

    $sys  = "Sos un tasador inmobiliario experto argentino especializado en análisis visual de propiedades.";
    $user = "Analizá estas fotografías de una propiedad.\n\n" .
            $propDesc . "\n\n" .
            "Respondé SOLO con este JSON exacto (sin texto extra, sin markdown):\n" .
            '{"score":[número -15 a +15],"confidence":"alta|media|baja","summary":"resumen 1 oración",' .
            '"details":[{"aspect":"Terminaciones","rating":4,"note":"nota breve"},{"aspect":"Luminosidad","rating":3,"note":"nota"},{"aspect":"Materiales","rating":4,"note":"nota"},{"aspect":"Presentación","rating":5,"note":"nota"}],' .
            '"red_flags":["lista vacía si no hay problemas"],"highlights":["punto fuerte 1"]}';

    $r = ai_call($cfg, $sys, $user, $b64, $mimes);
    if (!$r['ok']) throw new \Exception($r['error']);

    $result = cleanJson($r['text']);
    if (!$result || !isset($result['score'])) throw new \Exception('IA devolvió respuesta no parseable');

    $result['score']   = max(-15, min(15, (int)$result['score']));
    $result['success'] = true;
    jsonOut($result);

} catch (\Throwable $e) {
    jsonOut(['success' => true, 'score' => 0,
             'summary' => 'Sin ajuste por fotos (error IA: ' . $e->getMessage() . ')',
             'details' => [], 'red_flags' => [], 'highlights' => []]);
}
