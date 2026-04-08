<?php
// tasador/config/settings.php
// ──────────────────────────────────────────────────────────────────────────────
// INSTRUCCIONES:
//   1. Copiar este archivo: cp config/settings.example.php config/settings.php
//   2. Completar con TUS valores
//   3. settings.php está en .gitignore — NUNCA subir al repositorio
// ──────────────────────────────────────────────────────────────────────────────

return [
    // ── Identidad ────────────────────────────────────────────────────────────
    'app_name'      => 'TasadorIA',
    'app_tagline'   => 'Tasación inteligente de propiedades',
    'app_url'       => 'https://tudominio.com/tasador',   // ← SIN barra al final
    'logo_url'      => '',
    'primary_color' => '#c9a84c',

    // ── Agencia / empresa ────────────────────────────────────────────────────
    'agency_name'   => 'Tu Inmobiliaria',
    'agency_phone'  => '+54 000 000-0000',
    'agency_email'  => 'info@tudominio.com',    // ← recibe los leads
    'agency_web'    => 'https://tudominio.com',

    // ── Panel admin ──────────────────────────────────────────────────────────
    'admin_pass'    => 'cambiar_esta_clave',    // ← contraseña del admin.php

    // ── Base de datos MySQL ──────────────────────────────────────────────────
    // Crear en cPanel/CloudPanel: base "tasador_db", usuario "tasador_user"
    'db' => [
        'host'    => 'localhost',
        'name'    => 'tasador_db',       // ← tu nombre de BD
        'user'    => 'tasador_user',      // ← tu usuario de BD
        'pass'    => 'CAMBIAR_PASSWORD',  // ← tu contraseña de BD
        'charset' => 'utf8mb4',
    ],

    // ── Email / SMTP ─────────────────────────────────────────────────────────
    // smtp.host vacío = usa mail() del servidor (igual que WordPress sin plugin)
    // Completar para SMTP externo (Brevo, Gmail, SendGrid, etc.)
    'smtp' => [
        'host'   => '',       // 'smtp-relay.brevo.com' | 'smtp.gmail.com' | 'mail.tudominio.com'
        'port'   => 587,      // 587=TLS  465=SSL  25=sin cifrado
        'secure' => 'tls',    // 'tls' | 'ssl' | 'none'
        'user'   => '',       // tu email SMTP completo
        'pass'   => '',       // contraseña SMTP
    ],

    // ── IA — análisis de fotos y documentos ─────────────────────────────────
    // Proveedores soportados: anthropic | openai | grok | deepseek | gemini
    // provider → proveedor activo por defecto
    // api_key / model → compatibilidad con instalaciones anteriores (se usa si providers está vacío)
    // providers → configuración independiente por proveedor (recomendado)
    'ai' => [
        'provider' => 'anthropic',          // proveedor activo por defecto
        'api_key'  => '',                   // ← key del proveedor por defecto (compat. legada)
        'model'    => 'claude-opus-4-6',    // ← modelo del proveedor por defecto (compat. legada)
        'enabled'  => true,

        // Configuración por proveedor (opcional — si están aquí, se usan en lugar de api_key/model arriba)
        'providers' => [
            'anthropic' => [
                'api_key' => '',                    // console.anthropic.com → API Keys
                'model'   => 'claude-opus-4-6',     // claude-opus-4-6 | claude-sonnet-4-6 | claude-haiku-4-5-20251001
            ],
            'openai' => [
                'api_key' => '',                    // platform.openai.com → API Keys
                'model'   => 'gpt-4o',              // gpt-4o | gpt-4o-mini | gpt-4-turbo
            ],
            'grok' => [
                'api_key' => '',                    // console.x.ai → API Keys
                'model'   => 'grok-2-vision-latest', // grok-2-vision-latest | grok-2-latest
            ],
            'deepseek' => [
                'api_key' => '',                    // platform.deepseek.com → API Keys
                'model'   => 'deepseek-chat',       // deepseek-chat | deepseek-reasoner (sin visión)
            ],
            'gemini' => [
                'api_key' => '',                    // aistudio.google.com → Get API Key
                'model'   => 'gemini-2.0-flash',    // gemini-2.0-flash | gemini-1.5-pro
            ],
        ],
    ],

    // ── Datos de mercado — control del blend de precios ──────────────────────
    // correction_factor: factor de ajuste sobre precios de portales (los portales
    //   suelen publicar precios inflados). 0.80 = aplicar -20% al precio de portales
    //   antes de mezclarlo con los precios de zonas configurados.
    //   Rango típico: 0.70 (−30%) a 1.00 (sin descuento)
    // blend_weight: peso de los datos reales de portales en el precio final.
    //   0.00 = solo usar precios de zonas configuradas
    //   0.30 = 30% portales + 70% zonas (default recomendado)
    //   1.00 = solo datos de portales
    'market' => [
        'correction_factor' => 0.80,   // descuento sobre precios publicados en portales
        'blend_weight'      => 0.30,   // peso de datos de portales en el blend final
    ],

    // ── Geocoding ────────────────────────────────────────────────────────────
    // 'nominatim' es gratis (OpenStreetMap). 'google' requiere API key y billing.
    'geocoding' => [
        'provider'   => 'nominatim',
        'google_key' => '',
        'cache_days' => 30,
    ],

    // ── Moneda y tipo de cambio ──────────────────────────────────────────────
    'currency'     => 'USD',
    'ars_usd_rate' => 1450,   // ← actualizar periódicamente (o usar usd_fix.php)
    'show_ars'     => true,   // mostrar precio también en pesos

    // ── Widget embebible ─────────────────────────────────────────────────────
    'embed' => [
        'allowed_origins' => ['*'],   // o ['midominio.com', 'otroportal.com']
        'iframe_height'   => '940px',
    ],

    // ── Reporte ──────────────────────────────────────────────────────────────
    'report' => [
        'validity_days' => 90,
        'disclaimer'    => 'Esta tasación es orientativa y no constituye oferta ni documento legal. Para una tasación oficial consultar a un martillero matriculado.',
        'show_logo'     => true,
    ],

    // ── Pesos del algoritmo (deben sumar 100) ────────────────────────────────
    'algorithm' => [
        'location_weight'   => 40,
        'size_weight'       => 25,
        'condition_weight'  => 20,
        'amenities_weight'  => 10,
        'ai_photo_weight'   => 5,
    ],

    // ── MercadoLibre — OAuth para scraping de materiales ────────────────────
    // Opcional: si el servidor tiene acceso a api.mercadolibre.com, el bookmarklet
    // de materiales BIM usa esto para autenticarse. Si el servidor está bloqueado
    // por ML (error 403), igual funciona el bookmarklet desde el browser.
    // Crear app en: https://developers.mercadolibre.com.ar
    'mercadolibre' => [
        'app_id'        => '',   // ← Application ID de tu app ML
        'client_secret' => '',   // ← Secret Key de tu app ML
    ],

    // ── Apify — scraping automático de MercadoLibre (opcional, pago) ────────
    // Permite actualizar precios de materiales de construcción automáticamente
    // sin necesidad del bookmarklet ni del browser.
    //
    // Actor recomendado: https://apify.com/ultramarine_freezer/meli
    // Costo estimado: ~$2.50 USD / 1000 resultados (según plan Apify)
    //
    // Setup:
    //   1. Crear cuenta en https://console.apify.com
    //   2. Ir a Settings → Integrations → API tokens → Create new token
    //   3. Copiar el token aquí
    //   4. En admin_bim.php → botón "Apify" → configurar y ejecutar
    //
    'apify' => [
        'enabled'    => false,         // ← true para activar
        'api_token'  => '',            // ← token de Apify (console.apify.com → Settings → API)
        'actor_id'   => 'xTr91sBE3Cjj56g2A',  // ← ID del actor ML scraper (ultramarine_freezer/meli)
        'max_items'  => 200,           // ← máximo de resultados por búsqueda
    ],

    // ── WordPress / Houzez — publicación automática ──────────────────────────
    // Cuando un usuario completa el formulario de contacto (paso 7 del wizard),
    // TasadorIA crea automáticamente la propiedad en tu WordPress con Houzez.
    //
    // Requisitos:
    //   • WordPress 5.6+  con Application Passwords habilitado
    //   • Houzez 3.x+     (post type 'property' con show_in_rest = true)
    //   • El usuario WP debe tener rol Editor o Administrator
    //
    // Cómo crear la Application Password:
    //   WP Admin → Usuarios → (tu usuario) → Application Passwords
    //   → Nombre: "TasadorIA" → Agregar → copiar la clave (con espacios es OK)
    //
    'wordpress' => [
        'enabled'       => false,                           // ← true para activar
        'url'           => 'https://tudominio.com',         // ← URL de tu WordPress (sin barra final)
        'user'          => 'admin',                         // ← usuario WordPress
        'app_pass'      => 'xxxx xxxx xxxx xxxx xxxx xxxx', // ← Application Password
        'status'        => 'draft',                         // ← 'draft' (borrador) | 'publish' (publicar directo)
        'agent_id'      => 0,                               // ← ID del agente/usuario Houzez (0 = sin asignar)
        'default_state' => 'Santa Fe',                      // ← provincia por defecto para el meta fave_property_state
    ],
];
