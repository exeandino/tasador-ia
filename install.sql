-- tasador/install.sql — v4
-- Ejecutar en cPanel → phpMyAdmin → BD: tasador

USE `tasador`;

CREATE TABLE IF NOT EXISTS `tasaciones` (
  `id`          bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        varchar(20) NOT NULL,
  `data_json`   longtext NOT NULL,
  `result_json` longtext NOT NULL,
  `zone`        varchar(60) DEFAULT NULL,
  `city`        varchar(60) DEFAULT NULL,
  `ai_score`    float DEFAULT NULL,
  `ai_summary`  text DEFAULT NULL,
  `name`        varchar(100) DEFAULT NULL,
  `email`       varchar(150) DEFAULT NULL,
  `phone`       varchar(30) DEFAULT NULL,
  `ip`          varchar(45) DEFAULT NULL,
  `created_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de leads con datos de contacto
CREATE TABLE IF NOT EXISTS `tasacion_leads` (
  `id`            bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          varchar(100) NOT NULL,
  `email`         varchar(150) NOT NULL,
  `phone`         varchar(30) DEFAULT NULL,
  `result_code`   varchar(20) DEFAULT NULL,
  `property_data` longtext DEFAULT NULL,
  `email_sent`    tinyint(1) NOT NULL DEFAULT 0,
  `contacted`     tinyint(1) NOT NULL DEFAULT 0,
  `notes`         text DEFAULT NULL,
  `created_at`    timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zone_prices` (
  `id`          bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `city_key`    varchar(60) NOT NULL,
  `zone_key`    varchar(60) NOT NULL,
  `zone_label`  varchar(150) NOT NULL,
  `price_min`   decimal(10,2) NOT NULL,
  `price_avg`   decimal(10,2) NOT NULL,
  `price_max`   decimal(10,2) NOT NULL,
  `currency`    varchar(10) NOT NULL DEFAULT 'USD',
  `updated_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `source`      varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `city_zone` (`city_key`, `zone_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings_db` (
  `key`         varchar(100) NOT NULL,
  `value`       longtext DEFAULT NULL,
  `updated_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings_db` (`key`, `value`) VALUES
('ars_usd_rate', '1000'),
('last_price_update', '2025-01-01')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Precios actualizados y calibrados Q1 2025
INSERT INTO `zone_prices` (`city_key`,`zone_key`,`zone_label`,`price_min`,`price_avg`,`price_max`,`currency`,`source`) VALUES
('santa_fe_capital','general','Santa Fe Capital (general)',580,700,860,'USD','Calibrado Q1 2025'),
('santa_fe_capital','centro','Centro / Microcentro',850,1020,1200,'USD','Zonaprop Q1 2025'),
('santa_fe_capital','la_costanera','Costanera / Universitario',850,980,1150,'USD','Zonaprop Q1 2025'),
('santa_fe_capital','candioti_norte','Candioti Norte',750,880,1050,'USD','Zonaprop Q1 2025'),
('santa_fe_capital','candioti_sur','Candioti Sur',650,780,920,'USD','Zonaprop Q1 2025'),
('santa_fe_capital','el_pozo','El Pozo / Belgrano',700,840,1000,'USD','Zonaprop Q1 2025'),
('santa_fe_capital','general_obligado','Villa del Parque',550,660,800,'USD','Zonaprop Q1 2025'),
('santa_fe_capital','alto_verde','Alto Verde',350,480,650,'USD','Zonaprop Q1 2025'),
('santa_fe_capital','sur_industrial','Zona Sur',400,520,680,'USD','Zonaprop Q1 2025'),
('buenos_aires','general','Buenos Aires CABA (general)',1800,2400,3200,'USD','CUCICBA Q1 2025'),
('buenos_aires','palermo','Palermo / Soho / Hollywood',2800,3400,4200,'USD','Zonaprop Q1 2025'),
('buenos_aires','recoleta','Recoleta / Barrio Norte',2600,3200,4000,'USD','Zonaprop Q1 2025'),
('buenos_aires','belgrano','Belgrano / R / C',2200,2750,3500,'USD','Zonaprop Q1 2025'),
('buenos_aires','nuñez','Núñez / Saavedra',1900,2350,2900,'USD','Zonaprop Q1 2025'),
('buenos_aires','villa_crespo','Villa Crespo / Chacarita',2000,2450,3000,'USD','Zonaprop Q1 2025'),
('buenos_aires','san_telmo','San Telmo / Monserrat',1800,2200,2800,'USD','Zonaprop Q1 2025'),
('buenos_aires','almagro','Almagro / Boedo / Caballito',1700,2100,2600,'USD','Zonaprop Q1 2025'),
('buenos_aires','villa_urquiza','Villa Urquiza / Devoto',1600,1950,2400,'USD','Zonaprop Q1 2025'),
('buenos_aires','liniers','Liniers / Mataderos',1100,1400,1800,'USD','Zonaprop Q1 2025'),
('puerto_madero','general','Puerto Madero (general)',3500,4800,6000,'USD','CUCICBA Q1 2025'),
('puerto_madero','pm_este','Puerto Madero Este',4200,5600,7000,'USD','CUCICBA Q1 2025'),
('puerto_madero','pm_oeste','Puerto Madero Oeste',3500,4400,5500,'USD','CUCICBA Q1 2025'),
('gba_norte','san_isidro','San Isidro / Acassuso',2200,2900,3800,'USD','Zonaprop Q1 2025'),
('gba_norte','vicente_lopez','Vicente López / Olivos',1800,2300,3000,'USD','Zonaprop Q1 2025'),
('gba_norte','tigre','Tigre / Nordelta',1200,1750,2500,'USD','Zonaprop Q1 2025'),
('gba_norte','general','GBA Norte (general)',1200,1700,2500,'USD','Zonaprop Q1 2025'),
('rosario','general','Rosario (general)',900,1200,1600,'USD','Zonaprop Q1 2025'),
('cordoba','general','Córdoba Capital (general)',900,1200,1600,'USD','Zonaprop Q1 2025'),
('cordoba','nueva_cordoba','Nueva Córdoba',1400,1750,2200,'USD','Zonaprop Q1 2025')
ON DUPLICATE KEY UPDATE price_min=VALUES(price_min), price_avg=VALUES(price_avg), price_max=VALUES(price_max), updated_at=NOW(), source=VALUES(source);

-- ═══════════════════════════════════════════════════════════════
-- MARKETPLACE DE PLUGINS — v5
-- ═══════════════════════════════════════════════════════════════

-- Catálogo de plugins disponibles para venta (el dueño del sitio configura esto)
CREATE TABLE IF NOT EXISTS `tasador_plugin_prices` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(80)  NOT NULL,
  `name`         VARCHAR(120) NOT NULL,
  `description`  TEXT         DEFAULT NULL,
  `icon`         VARCHAR(10)  DEFAULT '🔌',
  `price_usd`    DECIMAL(8,2) NOT NULL DEFAULT 0,
  `active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `has_zip`      TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order`   TINYINT      NOT NULL DEFAULT 99,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plugins instalados en el sistema
CREATE TABLE IF NOT EXISTS `tasador_plugins` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(80)  NOT NULL,
  `name`         VARCHAR(120) NOT NULL,
  `version`      VARCHAR(20)  NOT NULL DEFAULT '1.0.0',
  `author`       VARCHAR(120) DEFAULT NULL,
  `description`  TEXT         DEFAULT NULL,
  `requires`     VARCHAR(20)  DEFAULT '5.0',
  `active`       TINYINT(1)   NOT NULL DEFAULT 0,
  `settings`     JSON         DEFAULT NULL,
  `installed_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compras / pagos de plugins
CREATE TABLE IF NOT EXISTS `tasador_purchases` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`          VARCHAR(100) NOT NULL,
  `plugin_slug`       VARCHAR(80)  NOT NULL,
  `plugin_name`       VARCHAR(120) NOT NULL,
  `gateway`           ENUM('mercadopago','stripe','manual') NOT NULL DEFAULT 'mercadopago',
  `gateway_payment_id`VARCHAR(200) DEFAULT NULL,
  `buyer_email`       VARCHAR(150) NOT NULL,
  `buyer_name`        VARCHAR(120) DEFAULT NULL,
  `price_usd`         DECIMAL(8,2) NOT NULL,
  `price_local`       DECIMAL(12,2) DEFAULT NULL,
  `currency_local`    VARCHAR(10)  DEFAULT 'ARS',
  `status`            ENUM('pending','approved','rejected','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `download_token`    CHAR(64)     DEFAULT NULL,
  `download_expires`  DATETIME     DEFAULT NULL,
  `download_count`    SMALLINT     NOT NULL DEFAULT 0,
  `download_used`     TINYINT(1)   NOT NULL DEFAULT 0,
  `ip`                VARCHAR(45)  DEFAULT NULL,
  `webhook_raw`       LONGTEXT     DEFAULT NULL,
  `created_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `download_token` (`download_token`),
  KEY `buyer_email` (`buyer_email`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos por defecto del catálogo (el dueño puede editar precios y descripciones)
INSERT INTO `tasador_plugin_prices` (`slug`,`name`,`description`,`icon`,`price_usd`,`active`,`sort_order`) VALUES
('bim-materiales', 'BIM Materiales ML',    'Actualización automática de precios de construcción desde MercadoLibre. Scraping inteligente con mapeo de materiales y ajuste por ICC INDEC.',                   '🏗', 29.00, 1, 1),
('icc-indec',      'ICC INDEC Automático', 'Sincronización automática con el Índice del Costo de la Construcción del INDEC. Ajuste masivo de costos por zona con un clic.',                                '📊', 19.00, 1, 2),
('ia-fotos',       'IA Fotos Pro',         'Análisis avanzado de fotos con Claude Vision / GPT-4o. Detección de reformas, materiales premium, estado real y ajuste automático ±15%.',                    '📸', 29.00, 1, 3),
('apify-sync',     'Apify Sync',           'Scraping mensual automático de Zonaprop, Argenprop y Properati vía Apify. Mantiene los datos de mercado actualizados sin intervención manual.',                '🤖', 29.00, 1, 4),
('escrituras',     'Gestión Documental',   'Panel de escrituras y boletos. Seguimiento de estado legal, alertas de vencimiento y generación de informes para escribanos.',                                 '📋', 19.00, 1, 5),
('wp-publish',     'WP Publish',           'Publica propiedades tasadas directamente en WordPress/Houzez con un clic. Sincronización bidireccional y templates configurables.',                             '🌐', 19.00, 1, 6),
('ciudades-extra',  'Ciudades Extra LATAM', 'Paquete extendido de ciudades: Uruguay, Chile, Colombia, México y Miami/Florida con zonas, precios de mercado y extractores específicos.',                   '🗺', 19.00, 1, 7),
('crm-export',     'CRM Export Pro',       'Exportación avanzada de leads y tasaciones a Hubspot, Salesforce, Google Sheets, Mailchimp y Zoho. Webhooks configurables por evento.',                      '📤', 29.00, 1, 8)
ON DUPLICATE KEY UPDATE
  `name`=VALUES(`name`), `description`=VALUES(`description`),
  `icon`=VALUES(`icon`), `sort_order`=VALUES(`sort_order`);
