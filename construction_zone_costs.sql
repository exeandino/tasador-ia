-- ============================================================
--  TasadorIA — construction_zone_costs.sql
--  Costos de construcción por ciudad / zona / calidad
--  Valores en USD/m² · Actualizado abril 2026
--  Fuente: CAC, UOCRA, INDEC-ICCV, relevamiento propio
-- ============================================================

CREATE TABLE IF NOT EXISTS construction_zone_costs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    city         VARCHAR(60)   NOT NULL DEFAULT '',   -- key de zones.php, vacío = nacional
    zone         VARCHAR(60)   NOT NULL DEFAULT '',   -- key de zona, vacío = toda la ciudad
    quality      VARCHAR(20)   NOT NULL DEFAULT 'estandar',
    cost_usd_m2  DECIMAL(8,2)  NOT NULL,
    labor_pct    DECIMAL(5,2)  NOT NULL DEFAULT 38.00,
    notes        VARCHAR(255)  DEFAULT NULL,
    updated_at   DATE          NOT NULL DEFAULT (CURDATE()),
    UNIQUE KEY uk_city_zone_quality (city, zone, quality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Truncar para re-importar limpio
TRUNCATE TABLE construction_zone_costs;

-- ============================================================
--  NACIONAL (fallback genérico Argentina)
-- ============================================================
INSERT INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
('', '', 'economica',  550.00, 40.00, 'Construcción sin terminaciones finas, materiales básicos'),
('', '', 'estandar',   950.00, 38.00, 'Terminaciones medias, materiales estándar de mercado'),
('', '', 'calidad',   1350.00, 36.00, 'Materiales de primera, buenas terminaciones'),
('', '', 'premium',   2100.00, 34.00, 'Alta gama, arquitectura de diseño, domótica');

-- ============================================================
--  SANTA FE CAPITAL
-- ============================================================
INSERT INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
-- Ciudad general
('santa_fe_capital', '', 'economica',  530.00, 40.00, 'Santa Fe — promedio ciudad'),
('santa_fe_capital', '', 'estandar',   920.00, 39.00, 'Santa Fe — promedio ciudad'),
('santa_fe_capital', '', 'calidad',   1280.00, 37.00, 'Santa Fe — promedio ciudad'),
('santa_fe_capital', '', 'premium',   1900.00, 35.00, 'Santa Fe — promedio ciudad'),
-- Costanera / Universitario (zona premium, mayor demanda de mano de obra)
('santa_fe_capital', 'la_costanera',  'economica',  570.00, 40.00, NULL),
('santa_fe_capital', 'la_costanera',  'estandar',   990.00, 38.00, NULL),
('santa_fe_capital', 'la_costanera',  'calidad',   1380.00, 36.00, NULL),
('santa_fe_capital', 'la_costanera',  'premium',   2100.00, 34.00, NULL),
-- Centro / Microcentro (costo logístico mayor, obra en altura)
('santa_fe_capital', 'centro',        'economica',  560.00, 41.00, 'Obra en edificio, costo logístico mayor'),
('santa_fe_capital', 'centro',        'estandar',   960.00, 39.00, NULL),
('santa_fe_capital', 'centro',        'calidad',   1340.00, 37.00, NULL),
('santa_fe_capital', 'centro',        'premium',   2000.00, 35.00, NULL),
-- Candioti Norte
('santa_fe_capital', 'candioti_norte','economica',  545.00, 40.00, NULL),
('santa_fe_capital', 'candioti_norte','estandar',   940.00, 38.00, NULL),
('santa_fe_capital', 'candioti_norte','calidad',   1310.00, 36.00, NULL),
('santa_fe_capital', 'candioti_norte','premium',   1980.00, 34.00, NULL),
-- Candioti Sur
('santa_fe_capital', 'candioti_sur',  'economica',  540.00, 40.00, NULL),
('santa_fe_capital', 'candioti_sur',  'estandar',   930.00, 38.00, NULL),
('santa_fe_capital', 'candioti_sur',  'calidad',   1300.00, 36.00, NULL),
('santa_fe_capital', 'candioti_sur',  'premium',   1960.00, 34.00, NULL),
-- El Pozo / Belgrano
('santa_fe_capital', 'el_pozo',       'economica',  510.00, 41.00, 'Zona en desarrollo'),
('santa_fe_capital', 'el_pozo',       'estandar',   890.00, 39.00, NULL),
('santa_fe_capital', 'el_pozo',       'calidad',   1240.00, 37.00, NULL),
('santa_fe_capital', 'el_pozo',       'premium',   1850.00, 35.00, NULL),
-- Villa del Parque / Gral Obligado
('santa_fe_capital', 'general_obligado','economica', 510.00, 41.00, NULL),
('santa_fe_capital', 'general_obligado','estandar',  890.00, 40.00, NULL),
('santa_fe_capital', 'general_obligado','calidad',  1230.00, 38.00, NULL),
('santa_fe_capital', 'general_obligado','premium',  1830.00, 36.00, NULL),
-- Alto Verde / Colastiné (obra en zona de islas, sobrecosto logístico +15%)
('santa_fe_capital', 'alto_verde',    'economica',  620.00, 42.00, 'Sobrecosto logístico isla +15%'),
('santa_fe_capital', 'alto_verde',    'estandar',  1060.00, 40.00, NULL),
('santa_fe_capital', 'alto_verde',    'calidad',   1480.00, 38.00, NULL),
('santa_fe_capital', 'alto_verde',    'premium',   2200.00, 36.00, NULL),
-- Zona Sur / Industrial (materiales más baratos, mano de obra disponible)
('santa_fe_capital', 'sur_industrial','economica',  490.00, 41.00, NULL),
('santa_fe_capital', 'sur_industrial','estandar',   860.00, 39.00, NULL),
('santa_fe_capital', 'sur_industrial','calidad',   1200.00, 37.00, NULL),
('santa_fe_capital', 'sur_industrial','premium',   1780.00, 35.00, NULL);

-- ============================================================
--  BUENOS AIRES (CABA)
-- ============================================================
INSERT INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
-- Ciudad general
('buenos_aires', '', 'economica',  620.00, 38.00, 'CABA — promedio ciudad'),
('buenos_aires', '', 'estandar',  1050.00, 36.00, NULL),
('buenos_aires', '', 'calidad',   1480.00, 34.00, NULL),
('buenos_aires', '', 'premium',   2400.00, 32.00, NULL),
-- Palermo
('buenos_aires', 'palermo',      'economica',  650.00, 37.00, NULL),
('buenos_aires', 'palermo',      'estandar',  1100.00, 35.00, NULL),
('buenos_aires', 'palermo',      'calidad',   1550.00, 33.00, NULL),
('buenos_aires', 'palermo',      'premium',   2500.00, 31.00, NULL),
-- Recoleta
('buenos_aires', 'recoleta',     'economica',  660.00, 37.00, 'Zona premium, mano obra calificada'),
('buenos_aires', 'recoleta',     'estandar',  1120.00, 35.00, NULL),
('buenos_aires', 'recoleta',     'calidad',   1600.00, 33.00, NULL),
('buenos_aires', 'recoleta',     'premium',   2600.00, 31.00, NULL),
-- Belgrano
('buenos_aires', 'belgrano',     'economica',  635.00, 37.00, NULL),
('buenos_aires', 'belgrano',     'estandar',  1070.00, 35.00, NULL),
('buenos_aires', 'belgrano',     'calidad',   1500.00, 33.00, NULL),
('buenos_aires', 'belgrano',     'premium',   2420.00, 31.00, NULL),
-- Núñez / Saavedra
('buenos_aires', 'nuñez',        'economica',  620.00, 38.00, NULL),
('buenos_aires', 'nuñez',        'estandar',  1040.00, 36.00, NULL),
('buenos_aires', 'nuñez',        'calidad',   1460.00, 34.00, NULL),
('buenos_aires', 'nuñez',        'premium',   2350.00, 32.00, NULL),
-- Villa Crespo / Almagro
('buenos_aires', 'villa_crespo', 'economica',  600.00, 39.00, NULL),
('buenos_aires', 'villa_crespo', 'estandar',  1010.00, 37.00, NULL),
('buenos_aires', 'villa_crespo', 'calidad',   1420.00, 35.00, NULL),
('buenos_aires', 'villa_crespo', 'premium',   2280.00, 33.00, NULL),
-- San Telmo / La Boca
('buenos_aires', 'san_telmo',    'economica',  590.00, 40.00, 'Construcción histórica, posible refuerzo estructural'),
('buenos_aires', 'san_telmo',    'estandar',   995.00, 38.00, NULL),
('buenos_aires', 'san_telmo',    'calidad',   1400.00, 36.00, NULL),
('buenos_aires', 'san_telmo',    'premium',   2250.00, 34.00, NULL),
-- Caballito / Flores
('buenos_aires', 'caballito',    'economica',  610.00, 39.00, NULL),
('buenos_aires', 'caballito',    'estandar',  1030.00, 37.00, NULL),
('buenos_aires', 'caballito',    'calidad',   1440.00, 35.00, NULL),
('buenos_aires', 'caballito',    'premium',   2300.00, 33.00, NULL);

-- ============================================================
--  PUERTO MADERO
-- ============================================================
INSERT INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
('puerto_madero', '', 'economica',  780.00, 36.00, 'Puerto Madero — sin económica real, mínimo clase media alta'),
('puerto_madero', '', 'estandar',  1350.00, 34.00, NULL),
('puerto_madero', '', 'calidad',   1900.00, 32.00, NULL),
('puerto_madero', '', 'premium',   2900.00, 30.00, 'Torres premium con domótica y materiales importados'),
('puerto_madero', 'pm_este',    'economica',  800.00, 36.00, NULL),
('puerto_madero', 'pm_este',    'estandar',  1400.00, 34.00, NULL),
('puerto_madero', 'pm_este',    'calidad',   1980.00, 32.00, NULL),
('puerto_madero', 'pm_este',    'premium',   3100.00, 30.00, NULL),
('puerto_madero', 'pm_oeste',   'economica',  760.00, 36.00, NULL),
('puerto_madero', 'pm_oeste',   'estandar',  1300.00, 34.00, NULL),
('puerto_madero', 'pm_oeste',   'calidad',   1820.00, 32.00, NULL),
('puerto_madero', 'pm_oeste',   'premium',   2800.00, 30.00, NULL);

-- ============================================================
--  GBA NORTE
-- ============================================================
INSERT INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
('gba_norte', '', 'economica',  580.00, 40.00, 'GBA Norte — promedio'),
('gba_norte', '', 'estandar',   990.00, 38.00, NULL),
('gba_norte', '', 'calidad',   1380.00, 36.00, NULL),
('gba_norte', '', 'premium',   2150.00, 34.00, NULL),
('gba_norte', 'san_isidro',  'economica',  610.00, 39.00, NULL),
('gba_norte', 'san_isidro',  'estandar',  1040.00, 37.00, NULL),
('gba_norte', 'san_isidro',  'calidad',   1450.00, 35.00, NULL),
('gba_norte', 'san_isidro',  'premium',   2250.00, 33.00, NULL),
('gba_norte', 'vicente_lopez','economica', 605.00, 39.00, NULL),
('gba_norte', 'vicente_lopez','estandar', 1030.00, 37.00, NULL),
('gba_norte', 'vicente_lopez','calidad',  1430.00, 35.00, NULL),
('gba_norte', 'vicente_lopez','premium',  2200.00, 33.00, NULL),
('gba_norte', 'nordelta',    'economica',  640.00, 39.00, 'Country/barrio cerrado, logística interna'),
('gba_norte', 'nordelta',    'estandar',  1090.00, 37.00, NULL),
('gba_norte', 'nordelta',    'calidad',   1520.00, 35.00, NULL),
('gba_norte', 'nordelta',    'premium',   2550.00, 33.00, NULL);

-- ============================================================
--  ROSARIO
-- ============================================================
INSERT INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
('rosario', '', 'economica',  560.00, 40.00, 'Rosario — promedio ciudad'),
('rosario', '', 'estandar',   970.00, 38.00, NULL),
('rosario', '', 'calidad',   1340.00, 36.00, NULL),
('rosario', '', 'premium',   2050.00, 34.00, NULL),
('rosario', 'centro_pichincha','economica', 590.00, 39.00, NULL),
('rosario', 'centro_pichincha','estandar', 1010.00, 37.00, NULL),
('rosario', 'centro_pichincha','calidad',  1400.00, 35.00, NULL),
('rosario', 'centro_pichincha','premium',  2150.00, 33.00, NULL),
('rosario', 'fisherton',     'economica',  580.00, 39.00, 'Barrio residencial oeste'),
('rosario', 'fisherton',     'estandar',   990.00, 37.00, NULL),
('rosario', 'fisherton',     'calidad',   1380.00, 35.00, NULL),
('rosario', 'fisherton',     'premium',   2100.00, 33.00, NULL);

-- ============================================================
--  CÓRDOBA
-- ============================================================
INSERT INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
('cordoba', '', 'economica',  545.00, 40.00, 'Córdoba — promedio ciudad'),
('cordoba', '', 'estandar',   950.00, 38.00, NULL),
('cordoba', '', 'calidad',   1310.00, 36.00, NULL),
('cordoba', '', 'premium',   2000.00, 34.00, NULL),
('cordoba', 'nueva_cordoba', 'economica',  575.00, 39.00, 'Alta densidad edilicia'),
('cordoba', 'nueva_cordoba', 'estandar',   990.00, 37.00, NULL),
('cordoba', 'nueva_cordoba', 'calidad',   1380.00, 35.00, NULL),
('cordoba', 'nueva_cordoba', 'premium',   2100.00, 33.00, NULL),
('cordoba', 'cerro_rosas',   'economica',  565.00, 39.00, 'Zona verde premium'),
('cordoba', 'cerro_rosas',   'estandar',   980.00, 37.00, NULL),
('cordoba', 'cerro_rosas',   'calidad',   1360.00, 35.00, NULL),
('cordoba', 'cerro_rosas',   'premium',   2080.00, 33.00, NULL);

-- ============================================================
--  ÍNDICE: para búsquedas rápidas
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_czc_city_quality ON construction_zone_costs (city, quality);
