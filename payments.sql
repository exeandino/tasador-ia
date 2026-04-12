-- ============================================================
--  TasadorIA — payments.sql
--  Tablas para sistema de pagos y descarga de plugins
--  Mercado Pago (ARS) + Stripe (USD)
-- ============================================================

-- Compras / órdenes de pago
CREATE TABLE IF NOT EXISTS tasador_purchases (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    order_id     VARCHAR(120) NOT NULL UNIQUE,   -- MP payment_id o Stripe session_id
    gateway      ENUM('mercadopago','stripe') NOT NULL,
    plugin_slug  VARCHAR(80) NOT NULL,
    plugin_name  VARCHAR(120) NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,
    currency     CHAR(3) NOT NULL,               -- ARS o USD
    amount_usd   DECIMAL(10,2) DEFAULT NULL,     -- siempre en USD para referencia
    email        VARCHAR(180) NOT NULL,
    buyer_name   VARCHAR(120) DEFAULT NULL,
    status       ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    download_token   VARCHAR(64) DEFAULT NULL,   -- token para descargar el ZIP
    download_used    TINYINT(1) NOT NULL DEFAULT 0,
    download_count   INT NOT NULL DEFAULT 0,
    download_expires DATETIME DEFAULT NULL,      -- 72hs después de aprobado
    metadata     JSON DEFAULT NULL,              -- datos extra del gateway
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gateway_order (gateway, order_id),
    INDEX idx_token (download_token),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Precios de plugins (configurable desde admin)
CREATE TABLE IF NOT EXISTS tasador_plugin_prices (
    slug         VARCHAR(80) PRIMARY KEY,
    name         VARCHAR(120) NOT NULL,
    price_usd    DECIMAL(8,2) NOT NULL,
    active       TINYINT(1) NOT NULL DEFAULT 1,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Precios por defecto del marketplace
INSERT INTO tasador_plugin_prices (slug, name, price_usd) VALUES
('bim-materiales', 'BIM Materiales ML',      29.00),
('icc-indec',      'Actualizar por ICC',      19.00),
('ia-fotos',       'Análisis IA de Fotos',    29.00),
('apify-sync',     'Scraping Automático',     29.00),
('escrituras',     'Análisis de Escrituras',  19.00),
('wp-publish',     'WordPress Publisher',     19.00),
('ciudades-extra', 'Ciudades Extra',          19.00),
('crm-export',     'CRM Export',              29.00)
ON DUPLICATE KEY UPDATE price_usd=VALUES(price_usd);
