-- ============================================================
-- TasadorIA — users.sql
-- Sistema de usuarios, agencias, suscripciones y pagos
-- Importar DESPUÉS de install.sql
-- ============================================================

-- ── Agencias (nivel superior, multi-tenant) ───────────────────
CREATE TABLE IF NOT EXISTS agencies (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    slug            VARCHAR(80)  NOT NULL UNIQUE,   -- subdomain o prefijo URL
    owner_email     VARCHAR(200) NOT NULL,
    logo_url        VARCHAR(400) DEFAULT NULL,
    primary_color   VARCHAR(10)  DEFAULT '#c9a84c',
    custom_domain   VARCHAR(200) DEFAULT NULL,       -- para white-label
    tier            ENUM('agency','enterprise') DEFAULT 'agency',
    status          ENUM('active','suspended','cancelled') DEFAULT 'active',
    max_users       SMALLINT UNSIGNED DEFAULT 5,
    settings_json   JSON DEFAULT NULL,               -- config custom por agencia
    stripe_customer_id   VARCHAR(100) DEFAULT NULL,
    mp_customer_id       VARCHAR(100) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Usuarios ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agency_id       INT UNSIGNED DEFAULT NULL,       -- NULL = usuario independiente
    email           VARCHAR(200) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    name            VARCHAR(120) DEFAULT NULL,
    phone           VARCHAR(30)  DEFAULT NULL,
    role            ENUM('user','agent','agency_admin','super_admin') DEFAULT 'user',
    tier            ENUM('free','pro','agency','enterprise') DEFAULT 'free',
    status          ENUM('active','inactive','banned') DEFAULT 'active',
    -- Freemium
    tasaciones_count    SMALLINT UNSIGNED DEFAULT 0,
    tasaciones_limit    SMALLINT UNSIGNED DEFAULT 5,  -- 5 gratis, luego NULL=ilimitado
    -- Auth
    email_verified      TINYINT(1) DEFAULT 0,
    email_verify_token  VARCHAR(64)  DEFAULT NULL,
    reset_token         VARCHAR(64)  DEFAULT NULL,
    reset_token_expires DATETIME     DEFAULT NULL,
    last_login          DATETIME     DEFAULT NULL,
    -- Pagos
    stripe_customer_id  VARCHAR(100) DEFAULT NULL,
    mp_customer_id      VARCHAR(100) DEFAULT NULL,
    -- Timestamps
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE SET NULL,
    INDEX idx_email  (email),
    INDEX idx_agency (agency_id),
    INDEX idx_tier   (tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Suscripciones / Pagos ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS subscriptions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    agency_id       INT UNSIGNED DEFAULT NULL,
    plan            ENUM('pro_monthly','pro_annual','agency_monthly','agency_annual','enterprise') NOT NULL,
    status          ENUM('active','past_due','cancelled','trialing','paused') DEFAULT 'active',
    currency        ENUM('USD','ARS') DEFAULT 'USD',
    amount          DECIMAL(10,2) NOT NULL,
    -- Stripe
    stripe_subscription_id  VARCHAR(100) DEFAULT NULL,
    stripe_price_id         VARCHAR(100) DEFAULT NULL,
    -- MercadoPago
    mp_subscription_id      VARCHAR(100) DEFAULT NULL,
    mp_preapproval_id       VARCHAR(100) DEFAULT NULL,
    -- Período
    current_period_start    DATETIME DEFAULT NULL,
    current_period_end      DATETIME DEFAULT NULL,
    cancelled_at            DATETIME DEFAULT NULL,
    trial_end               DATETIME DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE SET NULL,
    FOREIGN KEY (agency_id) REFERENCES agencies(id) ON DELETE SET NULL,
    INDEX idx_user   (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Pagos individuales (tasaciones sueltas) ───────────────────
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    tasacion_code   VARCHAR(20)  DEFAULT NULL,
    gateway         ENUM('stripe','mercadopago','manual') NOT NULL,
    gateway_ref     VARCHAR(200) DEFAULT NULL,        -- payment_intent_id / pago_id
    amount          DECIMAL(10,2) NOT NULL,
    currency        ENUM('USD','ARS') DEFAULT 'USD',
    status          ENUM('pending','approved','rejected','refunded') DEFAULT 'pending',
    description     VARCHAR(300) DEFAULT NULL,
    metadata_json   JSON DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_gateway_ref (gateway_ref),
    INDEX idx_user        (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── API Tokens (para plan Pro+) ───────────────────────────────
CREATE TABLE IF NOT EXISTS api_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(80)  NOT NULL DEFAULT 'Mi token',
    token_hash  VARCHAR(64)  NOT NULL UNIQUE,         -- SHA-256 del token real
    last_used   DATETIME     DEFAULT NULL,
    expires_at  DATETIME     DEFAULT NULL,            -- NULL = no expira
    calls_count INT UNSIGNED DEFAULT 0,
    status      ENUM('active','revoked') DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Sesiones de usuario (auth propio, sin PHP sessions globales) ──
CREATE TABLE IF NOT EXISTS user_sessions (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    ip          VARCHAR(45)  DEFAULT NULL,
    user_agent  VARCHAR(300) DEFAULT NULL,
    expires_at  DATETIME     NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token   (session_token),
    INDEX idx_user    (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Vincular tasaciones existentes a usuarios ─────────────────
ALTER TABLE tasaciones
    ADD COLUMN IF NOT EXISTS user_id    INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_public  TINYINT(1)   DEFAULT 1,
    ADD INDEX IF NOT EXISTS idx_user_id (user_id);

-- ── Historial de tasaciones por usuario (JSON completo guardado) ──
-- Permite ver el historial sin recalcular; snapshot inmutable del resultado
CREATE TABLE IF NOT EXISTS user_tasaciones (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    tasacion_code   VARCHAR(20)  NOT NULL UNIQUE,    -- TA-XXXXXXXX
    title           VARCHAR(200) DEFAULT NULL,        -- "Depto 65m² Candioti Norte"
    city            VARCHAR(80)  DEFAULT NULL,
    zone            VARCHAR(80)  DEFAULT NULL,
    property_type   VARCHAR(40)  DEFAULT NULL,
    operation       VARCHAR(20)  DEFAULT NULL,
    covered_area    DECIMAL(8,1) DEFAULT NULL,
    price_suggested DECIMAL(12,2) DEFAULT NULL,      -- USD
    price_min       DECIMAL(12,2) DEFAULT NULL,
    price_max       DECIMAL(12,2) DEFAULT NULL,
    currency        VARCHAR(5)   DEFAULT 'USD',
    input_json      JSON DEFAULT NULL,               -- parámetros de entrada
    result_json     JSON DEFAULT NULL,               -- respuesta completa de valuar.php
    is_favorite     TINYINT(1)   DEFAULT 0,
    notes           TEXT         DEFAULT NULL,        -- notas privadas del usuario
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user      (user_id),
    INDEX idx_code      (tasacion_code),
    INDEX idx_city_zone (city, zone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Feature flags por tier ────────────────────────────────────
-- Define qué puede hacer cada plan (consultado en runtime)
CREATE TABLE IF NOT EXISTS tier_features (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tier        ENUM('free','pro','agency','enterprise') NOT NULL,
    feature     VARCHAR(80) NOT NULL,                 -- 'bim','consensus_ai','pdf_report', etc.
    enabled     TINYINT(1) DEFAULT 1,
    limit_val   INT DEFAULT NULL,                     -- NULL = ilimitado
    UNIQUE KEY uk_tier_feature (tier, feature)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores por defecto de features por tier
INSERT INTO tier_features (tier, feature, enabled, limit_val) VALUES
-- FREE
('free', 'tasaciones',      1,  5),
('free', 'pdf_report',      0,  NULL),
('free', 'history',         0,  NULL),
('free', 'consensus_ai',    0,  NULL),
('free', 'bim',             0,  NULL),
('free', 'api_access',      0,  NULL),
('free', 'crm_export',      0,  NULL),
('free', 'custom_branding', 0,  NULL),
-- PRO
('pro',  'tasaciones',      1,  NULL),
('pro',  'pdf_report',      1,  NULL),
('pro',  'history',         1,  NULL),
('pro',  'consensus_ai',    1,  NULL),
('pro',  'bim',             0,  NULL),
('pro',  'api_access',      1,  NULL),
('pro',  'crm_export',      0,  NULL),
('pro',  'custom_branding', 0,  NULL),
-- AGENCY
('agency', 'tasaciones',      1, NULL),
('agency', 'pdf_report',      1, NULL),
('agency', 'history',         1, NULL),
('agency', 'consensus_ai',    1, NULL),
('agency', 'bim',             1, NULL),
('agency', 'api_access',      1, NULL),
('agency', 'crm_export',      1, NULL),
('agency', 'custom_branding', 0, NULL),
-- ENTERPRISE
('enterprise', 'tasaciones',      1, NULL),
('enterprise', 'pdf_report',      1, NULL),
('enterprise', 'history',         1, NULL),
('enterprise', 'consensus_ai',    1, NULL),
('enterprise', 'bim',             1, NULL),
('enterprise', 'api_access',      1, NULL),
('enterprise', 'crm_export',      1, NULL),
('enterprise', 'custom_branding', 1, NULL)
ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), limit_val=VALUES(limit_val);

-- ── Precios de planes ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS plans (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(50) NOT NULL UNIQUE,
    name        VARCHAR(80) NOT NULL,
    tier        ENUM('free','pro','agency','enterprise') NOT NULL,
    billing     ENUM('monthly','annual','lifetime','one_time') DEFAULT 'monthly',
    price_usd   DECIMAL(8,2) DEFAULT 0.00,
    price_ars   DECIMAL(10,2) DEFAULT 0.00,
    stripe_price_id VARCHAR(100) DEFAULT NULL,
    mp_plan_id      VARCHAR(100) DEFAULT NULL,
    active      TINYINT(1) DEFAULT 1,
    sort_order  TINYINT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO plans (slug, name, tier, billing, price_usd, price_ars, sort_order) VALUES
('free',            'Free',              'free',       'lifetime',  0.00,     0.00,       0),
('pro_monthly',     'Pro Mensual',       'pro',        'monthly',   9.00,  12600.00,     1),
('pro_annual',      'Pro Anual',         'pro',        'annual',   79.00, 110600.00,     2),
('agency_monthly',  'Agencia Mensual',   'agency',     'monthly',  29.00,  40600.00,     3),
('agency_annual',   'Agencia Anual',     'agency',     'annual',  249.00, 348600.00,     4),
('enterprise',      'Enterprise',        'enterprise', 'monthly',  99.00, 138600.00,     5)
ON DUPLICATE KEY UPDATE name=VALUES(name), price_usd=VALUES(price_usd);
