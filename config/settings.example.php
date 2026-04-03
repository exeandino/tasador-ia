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

    // ── IA — análisis de fotos ───────────────────────────────────────────────
    // Si api_key está vacío, el análisis de fotos se desactiva automáticamente.
    // Soporta: 'anthropic' (Claude) o 'openai' (GPT-4o)
    'ai' => [
        'provider' => 'anthropic',
        'api_key'  => '',                 // ← sk-ant-... de console.anthropic.com
        'model'    => 'claude-opus-4-6',  // o 'gpt-4o' para OpenAI
        'enabled'  => true,
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
];
