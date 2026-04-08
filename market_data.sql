-- tasador/market_data.sql
-- Ejecutar en la BD "tasador" para agregar soporte de datos de mercado

USE tasador;

-- ──────────────────────────────────────────────────────────────────
-- Tabla principal: listings del mercado (scraping propio + zonaprop)
-- ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `market_listings` (
  `id`             bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `source`         varchar(30) NOT NULL COMMENT 'zonaprop|argenprop|properati|manual|csv',
  `external_id`    varchar(80) DEFAULT NULL COMMENT 'ID en el portal original',
  `url`            varchar(500) DEFAULT NULL,

  -- Ubicación
  `address`        varchar(255) DEFAULT NULL,
  `city`           varchar(100) DEFAULT NULL,
  `province`       varchar(100) DEFAULT NULL,
  `zone`           varchar(100) DEFAULT NULL COMMENT 'barrio/zona detectada',
  `lat`            decimal(10,7) DEFAULT NULL,
  `lng`            decimal(10,7) DEFAULT NULL,

  -- Propiedad
  `property_type`  varchar(30) DEFAULT NULL COMMENT 'departamento|casa|ph|etc',
  `operation`      varchar(20) DEFAULT NULL COMMENT 'venta|alquiler',
  `covered_area`   decimal(10,2) DEFAULT NULL,
  `total_area`     decimal(10,2) DEFAULT NULL,
  `bedrooms`       tinyint DEFAULT NULL,
  `bathrooms`      tinyint DEFAULT NULL,
  `garages`        tinyint DEFAULT NULL,
  `age_years`      smallint DEFAULT NULL,
  `floor`          tinyint DEFAULT NULL,

  -- Precio
  `price`          decimal(18,2) DEFAULT NULL,
  `currency`       varchar(10) NOT NULL DEFAULT 'USD',
  `price_usd`      decimal(18,2) DEFAULT NULL COMMENT 'convertido si era ARS',
  `price_per_m2`   decimal(10,2) DEFAULT NULL COMMENT 'calculado al importar',
  `expenses`       decimal(10,2) DEFAULT NULL,

  -- Metadatos
  `title`          varchar(255) DEFAULT NULL,
  `active`         tinyint(1) NOT NULL DEFAULT 1,
  `scraped_at`     timestamp NULL DEFAULT NULL,
  `created_at`     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `source_city`   (`source`, `city`),
  KEY `zone_type`     (`zone`, `property_type`, `operation`),
  KEY `price_m2`      (`price_per_m2`),
  KEY `coords`        (`lat`, `lng`),
  KEY `active_date`   (`active`, `scraped_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────
-- Caché de búsquedas en portales externos (Zonaprop, etc.)
-- ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `market_cache` (
  `id`          bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `query_hash`  char(64) NOT NULL COMMENT 'SHA256 de los parámetros de búsqueda',
  `source`      varchar(30) NOT NULL DEFAULT 'zonaprop',
  `query_params`longtext DEFAULT NULL COMMENT 'JSON con los parámetros',
  `result_json` longtext DEFAULT NULL COMMENT 'Resultados cacheados',
  `result_count`int DEFAULT 0,
  `avg_price_m2`decimal(10,2) DEFAULT NULL,
  `created_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`  timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `query_hash` (`query_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────────
-- Vista: precios promedio por zona (para el motor de tasación)
-- ──────────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW `market_zone_averages` AS
SELECT
  city,
  zone,
  property_type,
  operation,
  COUNT(*)                          AS listing_count,
  ROUND(AVG(price_per_m2), 0)       AS avg_price_m2,
  ROUND(MIN(price_per_m2), 0)       AS min_price_m2,
  ROUND(MAX(price_per_m2), 0)       AS max_price_m2,
  ROUND(STDDEV(price_per_m2), 0)    AS stddev_price_m2,
  currency,
  MAX(scraped_at)                   AS last_updated
FROM market_listings
WHERE active = 1
  AND price_per_m2 > 100
  AND price_per_m2 < 20000
  AND scraped_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY city, zone, property_type, operation, currency
HAVING listing_count >= 3;
