<?php
/**
 * TasadorIA — Helper unificado de proveedores IA
 * Soporta: Anthropic (Claude), OpenAI, Grok (xAI), DeepSeek, Gemini
 *
 * Uso:
 *   $result = ai_call($cfg, $systemPrompt, $userText, $base64Images, $mimeTypes);
 *   if ($result['ok']) $text = $result['text'];
 *   else               $error = $result['error'];
 *
 * $base64Images  = array de strings base64 (sin prefijo data:...)
 * $mimeTypes     = array paralelo con 'image/jpeg', 'image/png', etc.
 *                  (puede omitirse → se asume 'image/jpeg')
 */

/**
 * Hace un llamado HTTP con cURL y retorna ['ok'=>bool,'body'=>string,'code'=>int]
 */
function ai_curl(string $url, array $headers, string $body): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return ['ok' => false, 'body' => '', 'code' => 0, 'error' => $err];
    return ['ok' => true, 'body' => $resp, 'code' => $code];
}

/**
 * Punto de entrada principal.
 *
 * @param array  $cfg          Config completa del sistema (el array de settings.php)
 * @param string $systemPrompt Prompt de sistema
 * @param string $userText     Texto del usuario
 * @param array  $base64Images Imágenes en base64 (vacío si no hay fotos)
 * @param array  $mimeTypes    Mime types paralelos a $base64Images
 * @param string|null $providerOverride  Fuerza un provider ('anthropic','openai',etc.)
 * @return array ['ok'=>bool, 'text'=>string, 'provider'=>string, 'model'=>string, 'error'=>string]
 */
function ai_call(
    array  $cfg,
    string $systemPrompt,
    string $userText,
    array  $base64Images  = [],
    array  $mimeTypes     = [],
    ?string $providerOverride = null,
    int    $maxTokens     = 4096
): array {
    $aiCfg   = $cfg['ai'] ?? [];
    $provider = strtolower($providerOverride ?? ($aiCfg['provider'] ?? 'anthropic'));

    // Seleccionar config del proveedor
    $providerCfg = $aiCfg['providers'][$provider] ?? [];

    // Compatibilidad con formato anterior (api_key + model en raíz del bloque ai)
    $apiKey = $providerCfg['api_key'] ?? ($aiCfg['api_key'] ?? '');
    $model  = $providerCfg['model']   ?? ($aiCfg['model']   ?? '');

    // Defaults por proveedor
    $defaults = [
        'anthropic' => 'claude-opus-4-6',
        'openai'    => 'gpt-4o',
        'grok'      => 'grok-2-vision-latest',
        'deepseek'  => 'deepseek-chat',
        'gemini'    => 'gemini-2.0-flash',
    ];
    if (empty($model)) $model = $defaults[$provider] ?? 'gpt-4o';

    // Normalizar mime types
    foreach ($base64Images as $i => $_) {
        if (empty($mimeTypes[$i])) $mimeTypes[$i] = 'image/jpeg';
    }

    switch ($provider) {
        case 'anthropic':
            return ai_call_anthropic($apiKey, $model, $systemPrompt, $userText, $base64Images, $mimeTypes, $maxTokens);
        case 'openai':
        case 'grok':
        case 'deepseek':
            return ai_call_openai_compat($provider, $apiKey, $model, $systemPrompt, $userText, $base64Images, $mimeTypes, $maxTokens);
        case 'gemini':
            return ai_call_gemini($apiKey, $model, $systemPrompt, $userText, $base64Images, $mimeTypes, $maxTokens);
        default:
            return ['ok' => false, 'text' => '', 'provider' => $provider, 'model' => $model,
                    'error' => "Proveedor de IA no soportado: {$provider}"];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ANTHROPIC (Claude)
// ─────────────────────────────────────────────────────────────────────────────
function ai_call_anthropic(
    string $apiKey, string $model,
    string $systemPrompt, string $userText,
    array $images, array $mimes,
    int $maxTokens = 4096
): array {
    $content = [];

    foreach ($images as $i => $b64) {
        $content[] = [
            'type'   => 'image',
            'source' => [
                'type'       => 'base64',
                'media_type' => $mimes[$i],
                'data'       => $b64,
            ],
        ];
    }
    $content[] = ['type' => 'text', 'text' => $userText];

    $payload = [
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'system'     => $systemPrompt,
        'messages'   => [['role' => 'user', 'content' => $content]],
    ];

    $r = ai_curl(
        'https://api.anthropic.com/v1/messages',
        [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        json_encode($payload)
    );
    if (!$r['ok']) return ['ok' => false, 'text' => '', 'provider' => 'anthropic', 'model' => $model, 'error' => $r['error']];

    $data = json_decode($r['body'], true);
    if ($r['code'] !== 200) {
        $msg = $data['error']['message'] ?? $r['body'];
        return ['ok' => false, 'text' => '', 'provider' => 'anthropic', 'model' => $model, 'error' => $msg];
    }
    $text = $data['content'][0]['text'] ?? '';
    return ['ok' => true, 'text' => $text, 'provider' => 'anthropic', 'model' => $model, 'error' => ''];
}

// ─────────────────────────────────────────────────────────────────────────────
// OPENAI-COMPATIBLE (OpenAI, Grok/xAI, DeepSeek)
// ─────────────────────────────────────────────────────────────────────────────
function ai_call_openai_compat(
    string $provider, string $apiKey, string $model,
    string $systemPrompt, string $userText,
    array $images, array $mimes,
    int $maxTokens = 4096
): array {
    $endpoints = [
        'openai'   => 'https://api.openai.com/v1/chat/completions',
        'grok'     => 'https://api.x.ai/v1/chat/completions',
        'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
    ];
    $url = $endpoints[$provider] ?? $endpoints['openai'];

    // DeepSeek no soporta visión de imágenes — solo texto
    $supportsVision = ($provider !== 'deepseek');

    $userContent = [];
    if ($supportsVision) {
        foreach ($images as $i => $b64) {
            $userContent[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => 'data:' . $mimes[$i] . ';base64,' . $b64],
            ];
        }
    }
    $userContent[] = ['type' => 'text', 'text' => $userText];

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userContent],
    ];

    $payload = [
        'model'      => $model,
        'messages'   => $messages,
        'max_tokens' => $maxTokens,
    ];

    $r = ai_curl(
        $url,
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        json_encode($payload)
    );
    if (!$r['ok']) return ['ok' => false, 'text' => '', 'provider' => $provider, 'model' => $model, 'error' => $r['error']];

    $data = json_decode($r['body'], true);
    if ($r['code'] !== 200) {
        $msg = $data['error']['message'] ?? $r['body'];
        return ['ok' => false, 'text' => '', 'provider' => $provider, 'model' => $model, 'error' => $msg];
    }
    $text = $data['choices'][0]['message']['content'] ?? '';
    return ['ok' => true, 'text' => $text, 'provider' => $provider, 'model' => $model, 'error' => ''];
}

// ─────────────────────────────────────────────────────────────────────────────
// GEMINI (Google)
// ─────────────────────────────────────────────────────────────────────────────
function ai_call_gemini(
    string $apiKey, string $model,
    string $systemPrompt, string $userText,
    array $images, array $mimes,
    int $maxTokens = 4096
): array {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $parts = [];
    foreach ($images as $i => $b64) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $mimes[$i],
                'data'      => $b64,
            ],
        ];
    }
    $parts[] = ['text' => $userText];

    $payload = [
        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents'           => [['role' => 'user', 'parts' => $parts]],
        'generationConfig'   => ['maxOutputTokens' => $maxTokens],
    ];

    $r = ai_curl($url, ['Content-Type: application/json'], json_encode($payload));
    if (!$r['ok']) return ['ok' => false, 'text' => '', 'provider' => 'gemini', 'model' => $model, 'error' => $r['error']];

    $data = json_decode($r['body'], true);
    if ($r['code'] !== 200) {
        $msg = $data['error']['message'] ?? $r['body'];
        return ['ok' => false, 'text' => '', 'provider' => 'gemini', 'model' => $model, 'error' => $msg];
    }
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    return ['ok' => true, 'text' => $text, 'provider' => 'gemini', 'model' => $model, 'error' => ''];
}
