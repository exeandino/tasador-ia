<?php
/**
 * TasadorIA — wp_publish.php
 * ──────────────────────────────────────────────────────────────────────────────
 * Crea automáticamente una propiedad en WordPress/Houzez cuando se envía
 * el formulario de contacto de una tasación.
 *
 * Método: WordPress REST API + Application Passwords (WP 5.6+)
 * No requiere plugins extra — funciona con Houzez 3.x+
 *
 * Configuración en settings.php → clave 'wordpress':
 * ──────────────────────────────────────────────────
 * 'wordpress' => [
 *     'enabled'       => true,
 *     'url'           => 'https://anperprimo.com',
 *     'user'          => 'admin',
 *     'app_pass'      => 'xxxx xxxx xxxx xxxx xxxx xxxx',
 *     'status'        => 'draft',   // 'draft' | 'publish'
 *     'agent_id'      => 0,         // ID usuario Houzez agente (0 = sin asignar)
 *     'default_state' => 'Santa Fe',
 * ],
 *
 * Tipos de propiedad soportados por Houzez:
 *   departamento → Departamento
 *   casa         → Casa
 *   ph           → PH
 *   local        → Local comercial
 *   oficina      → Oficina
 *   terreno      → Terreno
 *   campo        → Campo
 *   galpon       → Galpón
 * ──────────────────────────────────────────────────────────────────────────────
 */

if (!function_exists('createWpProperty')) :

/**
 * Crea una propiedad en WordPress/Houzez via REST API.
 *
 * @param array $data  Datos de la tasación (ver send_email.php para campos)
 * @param array $wpCfg Configuración WordPress de settings.php
 * @return array       ['ok'=>bool, 'wp_id'=>int|null, 'wp_link'=>string|null, 'error'=>string|null]
 */
function createWpProperty(array $data, array $wpCfg): array
{
    // ── Configuración ──────────────────────────────────────────────────────────
    $wpUrl   = rtrim((string)($wpCfg['url']     ?? ''), '/');
    $user    = (string)($wpCfg['user']           ?? '');
    $appPass = str_replace(' ', '', (string)($wpCfg['app_pass'] ?? ''));
    $status  = (string)($wpCfg['status']         ?? 'draft');
    $agentId = (int)($wpCfg['agent_id']          ?? 0);
    $defState = (string)($wpCfg['default_state'] ?? 'Santa Fe');

    if ($wpUrl === '' || $user === '' || $appPass === '') {
        return ['ok' => false, 'error' => 'Configuración WordPress incompleta (url/user/app_pass)'];
    }

    // ── Labels y mapeos ────────────────────────────────────────────────────────
    $typeLabels = [
        'departamento' => 'Departamento',
        'casa'         => 'Casa',
        'ph'           => 'PH',
        'local'        => 'Local comercial',
        'oficina'      => 'Oficina',
        'terreno'      => 'Terreno',
        'campo'        => 'Campo',
        'galpon'       => 'Galpón',
        'quinta'       => 'Quinta',
        'duplex'       => 'Dúplex',
    ];

    // Slugs de taxonomía property-type en Houzez (ajustar según instalación)
    $typeSlugMap = [
        'departamento' => 'apartment',
        'casa'         => 'house',
        'ph'           => 'apartment',     // suele mapearse a Apartment en Houzez
        'local'        => 'commercial',
        'oficina'      => 'office',
        'terreno'      => 'land',
        'campo'        => 'farm',
        'galpon'       => 'warehouse',
        'quinta'       => 'country-home',
        'duplex'       => 'house',
    ];

    $propTypeRaw = (string)($data['property_type'] ?? '');
    $typeLabel   = $typeLabels[$propTypeRaw] ?? ucfirst($propTypeRaw ?: 'Propiedad');
    $typeSlug    = $typeSlugMap[$propTypeRaw] ?? '';

    $isAlquiler  = strtolower((string)($data['operation'] ?? 'venta')) === 'alquiler';
    $statusSlug  = $isAlquiler ? 'for-rent' : 'for-sale';

    // ── Año de construcción ────────────────────────────────────────────────────
    $ageYears  = (int)($data['age_years'] ?? 0);
    $yearBuilt = $ageYears > 0 ? (string)(date('Y') - $ageYears) : date('Y');

    // ── Título de la publicación ───────────────────────────────────────────────
    $zone  = (string)($data['zone']  ?? '');
    $city  = (string)($data['city']  ?? '');
    $area  = (int)($data['covered_area'] ?? 0);
    $price = (int)($data['price_suggested'] ?? 0);

    $title = "$typeLabel en $zone, $city";
    if ($area > 0) $title .= " — {$area}m²";

    // ── Contenido / descripción ────────────────────────────────────────────────
    $code        = (string)($data['code']          ?? '');
    $contactName = (string)($data['contact_name']  ?? '');
    $contactMail = (string)($data['contact_email'] ?? '');
    $contactTel  = (string)($data['contact_phone'] ?? '');
    $condition   = (string)($data['condition']     ?? '');

    $condLabels = [
        'excelente' => 'Excelente',
        'muy_bueno' => 'Muy bueno',
        'bueno'     => 'Bueno',
        'regular'   => 'Regular',
        'a_refaccionar' => 'A refaccionar',
    ];
    $condLabel = $condLabels[$condition] ?? ucfirst($condition);

    $priceFormatted    = 'USD ' . number_format($price, 0, ',', '.');
    $priceMinFormatted = 'USD ' . number_format((int)($data['price_min'] ?? 0), 0, ',', '.');
    $priceMaxFormatted = 'USD ' . number_format((int)($data['price_max'] ?? 0), 0, ',', '.');

    $lines = [];
    $lines[] = "Tasación generada automáticamente por TasadorIA.";
    $lines[] = "";
    $lines[] = "Código: $code";
    $lines[] = "Precio sugerido: $priceFormatted";
    $lines[] = "Rango: $priceMinFormatted – $priceMaxFormatted";
    if ($condLabel) $lines[] = "Estado: $condLabel";
    if ($area > 0)  $lines[] = "Superficie cubierta: {$area} m²";
    $uncov = (int)($data['uncovered_area'] ?? 0);
    if ($uncov > 0) $lines[] = "Superficie descubierta: {$uncov} m²";
    if ($ageYears === 0) $lines[] = "Antigüedad: A estrenar";
    elseif ($ageYears > 0) $lines[] = "Antigüedad: {$ageYears} años (ca. {$yearBuilt})";
    $lines[] = "";
    $lines[] = "Vendedor: $contactName";
    if ($contactMail) $lines[] = "Email: $contactMail";
    if ($contactTel)  $lines[] = "Teléfono: $contactTel";
    $lines[] = "";
    $lines[] = "Esta publicación es orientativa. Para una valuación oficial consultar a un martillero matriculado.";

    $content = implode("\n", $lines);

    // ── Dirección ──────────────────────────────────────────────────────────────
    $address = (string)($data['address'] ?? '');
    if ($address === '') {
        $address = "$zone, $city";
    }
    // Eliminar lat/lng si vienen pegados (algunos formularios los agregan)
    $address = preg_replace('/\s*[-\d\.]+\s*,\s*[-\d\.]+$/', '', $address);
    $address = trim($address);

    // ── Meta fields Houzez ────────────────────────────────────────────────────
    $meta = [
        // Precios
        'fave_property_price'           => (string)$price,
        'fave_property_price_prefix'    => 'USD',
        'fave_property_price_postfix'   => $isAlquiler ? '/mes' : '',
        'fave_property_min_price'       => (string)(int)($data['price_min'] ?? 0),
        'fave_property_max_price'       => (string)(int)($data['price_max'] ?? 0),

        // Moneda secundaria (ARS)
        'fave_property_sec_price'        => (string)(int)($data['price_ars'] ?? 0),
        'fave_property_sec_price_prefix' => 'ARS',

        // Dimensiones
        'fave_property_size'            => (string)$area,
        'fave_property_size_prefix'     => 'm2',

        // Habitaciones
        'fave_property_bedrooms'        => (string)(int)($data['bedrooms'] ?? 0),
        'fave_property_bathrooms'       => (string)(int)($data['bathrooms'] ?? 0),
        'fave_property_garage'          => (string)(int)($data['garages']  ?? 0),

        // Año
        'fave_property_year'            => $yearBuilt,

        // Ubicación
        'fave_property_address'         => $address,
        'fave_property_map_address'     => $address,
        'fave_property_country'         => 'AR',
        'fave_property_state'           => $defState,
        'fave_property_city'            => $city,
        'fave_property_zip'             => '',
        'fave_property_map_zoom'        => '15',

        // Miscelánea
        'fave_featured'                 => '0',
        'fave_property_notes'           => "TasadorIA — Código: $code | Contacto: $contactName ($contactMail / $contactTel)",
    ];

    // Coordenadas GPS si están disponibles
    $lat = $data['lat'] ?? null;
    $lng = $data['lng'] ?? null;
    if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
        $meta['fave_property_location'] = "$lat,$lng";
    }

    // Agente asignado
    if ($agentId > 0) {
        $meta['fave_agents'] = [$agentId];
    }

    // ── Body del POST ──────────────────────────────────────────────────────────
    $body = [
        'title'   => $title,
        'content' => $content,
        'status'  => $status,
        'meta'    => $meta,
    ];

    // Taxonomías (REST API acepta array de slugs o IDs)
    // Houzez usa: property-type, property-status, property-area, property-city
    if ($typeSlug !== '') {
        $body['property-type'] = [$typeSlug];
    }
    $body['property-status'] = [$statusSlug];

    // ── Llamada HTTP a WP REST API ─────────────────────────────────────────────
    $endpoint = "$wpUrl/wp-json/wp/v2/property";
    $authB64  = base64_encode("$user:$appPass");
    $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        // cURL (preferido)
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $authB64,
                'User-Agent: TasadorIA/5.0',
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['ok' => false, 'error' => "cURL: $curlErr"];
        }
    } else {
        // Fallback: stream_context (sin cURL)
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n"
                           . "Authorization: Basic $authB64\r\n"
                           . "User-Agent: TasadorIA/5.0\r\n",
                'content' => $jsonBody,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true],
        ]);
        $response = @file_get_contents($endpoint, false, $ctx);
        $httpCode = 0;
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
            $httpCode = (int)($m[1] ?? 0);
        }
        if ($response === false) {
            return ['ok' => false, 'error' => 'No se pudo conectar a WordPress (file_get_contents)'];
        }
    }

    // ── Parsear respuesta ──────────────────────────────────────────────────────
    $resp = json_decode($response, true);

    if ($httpCode === 201) {
        return [
            'ok'      => true,
            'wp_id'   => (int)($resp['id'] ?? 0),
            'wp_link' => (string)($resp['link'] ?? ''),
            'status'  => (string)($resp['status'] ?? $status),
        ];
    }

    // Error — puede ser meta no registrado (requiere plugin) o auth fallida
    $errMsg = (string)($resp['message'] ?? "HTTP $httpCode");
    $errCode = (string)($resp['code']   ?? '');

    // Diagnóstico amigable
    if ($httpCode === 401 || $errCode === 'rest_not_logged_in') {
        $errMsg = "Autenticación fallida. Verificá usuario y Application Password en WP Admin.";
    } elseif ($httpCode === 403 || $errCode === 'rest_forbidden') {
        $errMsg = "Sin permiso para crear propiedades. El usuario debe tener rol Editor o superior.";
    } elseif ($httpCode === 404) {
        $errMsg = "Endpoint no encontrado ($endpoint). Verificá que el post type 'property' esté activo y show_in_rest=true.";
    } elseif ($httpCode === 0) {
        $errMsg = "No se pudo conectar a WordPress ($wpUrl). Verificá la URL y que HTTPS esté habilitado.";
    }

    return [
        'ok'        => false,
        'http_code' => $httpCode,
        'error'     => $errMsg,
        'wp_code'   => $errCode,
    ];
}

endif;
