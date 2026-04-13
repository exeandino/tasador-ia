-- ─────────────────────────────────────────────────────────────────────────────
-- TasadorIA — construction.sql
-- Módulo: Costo de Construcción + Mini BIM
--
-- Importar: mysql -u user -p tasador_db < construction.sql
-- ─────────────────────────────────────────────────────────────────────────────

-- ── 1. Costos base por ciudad/zona/calidad ────────────────────────────────────
CREATE TABLE IF NOT EXISTS construction_zone_costs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  city          VARCHAR(100) NOT NULL DEFAULT '',        -- 'santa_fe_capital' | '' = todas
  zone          VARCHAR(100) NOT NULL DEFAULT '',        -- '' = toda la ciudad
  quality       VARCHAR(20)  NOT NULL DEFAULT 'estandar',
                                                         -- 'economica' | 'estandar' | 'calidad' | 'premium'
  cost_usd_m2   DECIMAL(10,2) NOT NULL DEFAULT 900.00,  -- costo en USD por m² cubierto
  labor_pct     DECIMAL(5,2)  NOT NULL DEFAULT 40.00,   -- % mano de obra sobre costo total
  notes         TEXT,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_city_zone_quality (city, zone, quality)
);

-- ── 2. Materiales de construcción ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS construction_materials (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  category      VARCHAR(50)  NOT NULL,
  -- 'estructura' | 'mamposteria' | 'cubierta' | 'instalaciones' | 'terminaciones' | 'varios'
  material      VARCHAR(120) NOT NULL,
  unit          VARCHAR(20)  NOT NULL,     -- 'm3' | 'bolsa' | 'millar' | 'unidad' | 'kg' | 'm2' | 'barra'
  price_ars     DECIMAL(12,2) DEFAULT 0,  -- precio en ARS
  price_usd     DECIMAL(10,4) DEFAULT 0,  -- precio en USD (calculado o manual)
  qty_per_m2    DECIMAL(10,4) DEFAULT 0,  -- cantidad por m² de construcción cubierta (calidad estándar)
  supplier      VARCHAR(150) DEFAULT '',  -- nombre corralón/proveedor
  region        VARCHAR(100) DEFAULT '',  -- ciudad o región
  source_url    VARCHAR(300) DEFAULT '',  -- URL del precio (MercadoLibre, corralón, etc.)
  is_local      TINYINT(1)   DEFAULT 0,  -- 1 = precio local verificado, 0 = precio referencia nacional
  active        TINYINT(1)   DEFAULT 1,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_category (category),
  INDEX idx_region   (region)
);

-- ─────────────────────────────────────────────────────────────────────────────
-- DATOS DEFAULT — Costos base Argentina 2025/2026
-- Fuente: INDEC, Cámara Argentina de la Construcción, relevamiento propio
-- Tipo de cambio referencia: 1 USD = 1.450 ARS
-- ─────────────────────────────────────────────────────────────────────────────

-- Costos nacionales (aplican a toda Argentina como fallback)
INSERT IGNORE INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
-- Económica: sin arquitecto, mano de obra informal, materiales básicos
('', '', 'economica', 650,  42, 'Calidad básica, mano de obra informal, sin terminaciones premium'),
-- Estándar: calidad media, mano de obra formal
('', '', 'estandar',  980,  40, 'Calidad media, terminaciones standard, mixto formal/informal'),
-- Calidad: buen nivel, todo formal, buenos materiales
('', '', 'calidad',   1350, 38, 'Buena calidad, mano de obra certificada, materiales de 1ra'),
-- Premium: arquitectura de autor, materiales importados, domótica
('', '', 'premium',   2100, 35, 'Calidad premium, materiales de alta gama, diseño de autor');

-- Santa Fe Capital (ajuste regional +5% respecto nacional)
INSERT IGNORE INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
('santa_fe_capital', '', 'economica', 680,  42, 'Santa Fe — calidad básica'),
('santa_fe_capital', '', 'estandar',  1020, 40, 'Santa Fe — calidad media'),
('santa_fe_capital', '', 'calidad',   1400, 38, 'Santa Fe — buena calidad'),
('santa_fe_capital', '', 'premium',   2200, 35, 'Santa Fe — premium');

-- Buenos Aires CABA (+25% respecto nacional)
INSERT IGNORE INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
('buenos_aires_caba', '', 'economica', 820,  42, 'CABA — calidad básica'),
('buenos_aires_caba', '', 'estandar',  1220, 40, 'CABA — calidad media'),
('buenos_aires_caba', '', 'calidad',   1680, 38, 'CABA — buena calidad'),
('buenos_aires_caba', '', 'premium',   2600, 35, 'CABA — premium');

-- Rosario (+8%)
INSERT IGNORE INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
('rosario', '', 'economica', 700,  42, 'Rosario — básica'),
('rosario', '', 'estandar',  1060, 40, 'Rosario — estándar'),
('rosario', '', 'calidad',   1460, 38, 'Rosario — calidad'),
('rosario', '', 'premium',   2270, 35, 'Rosario — premium');

-- Córdoba (+6%)
INSERT IGNORE INTO construction_zone_costs (city, zone, quality, cost_usd_m2, labor_pct, notes) VALUES
('cordoba', '', 'economica', 690,  42, 'Córdoba — básica'),
('cordoba', '', 'estandar',  1040, 40, 'Córdoba — estándar'),
('cordoba', '', 'calidad',   1430, 38, 'Córdoba — calidad'),
('cordoba', '', 'premium',   2220, 35, 'Córdoba — premium');

-- ─────────────────────────────────────────────────────────────────────────────
-- MATERIALES — Precios Santa Fe Capital (corralones locales + MercadoLibre)
-- Relevamiento abril 2025. Fuentes: Corralon El Tala SF, Corocon SF, ML.
-- ARS/USD = 1.450
-- ─────────────────────────────────────────────────────────────────────────────

INSERT IGNORE INTO construction_materials
  (category, material, unit, price_ars, price_usd, qty_per_m2, supplier, region, source_url, is_local) VALUES

-- ── ESTRUCTURA ──────────────────────────────────────────────────────────────
('estructura', 'Cemento Portland Normal 50kg', 'bolsa',
  12500, 8.62, 4.5,
  'Corralón local SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/cemento-portland', 0),

('estructura', 'Hormigón premix H-21 (c/bomba)', 'm3',
  215000, 148.28, 0.12,
  'Premix Santa Fe', 'santa_fe_capital',
  '', 1),

('estructura', 'Hierro en barra Ø8mm × 12m', 'barra',
  19500, 13.45, 0.45,
  'Acería Santa Fe', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/hierro-8mm', 0),

('estructura', 'Hierro en barra Ø10mm × 12m', 'barra',
  29000, 20.00, 0.20,
  'Acería Santa Fe', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/hierro-10mm', 0),

('estructura', 'Hierro en barra Ø12mm × 12m', 'barra',
  41500, 28.62, 0.10,
  'Acería Santa Fe', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/hierro-12mm', 0),

('estructura', 'Malla sima 15×15mm panel 2.1×4.3m', 'unidad',
  37000, 25.52, 0.12,
  'Corralón local SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/malla-sima', 0),

('estructura', 'Caño de hierro para viga hormigón 12cm', 'ml',
  4800, 3.31, 0.50,
  'Corralón local SF', 'santa_fe_capital',
  '', 1),

-- ── MAMPOSTERÍA ─────────────────────────────────────────────────────────────
('mamposteria', 'Ladrillo cerámico macizo 25×12×8cm (millar)', 'millar',
  92000, 63.45, 0.10,
  'Cerámica El Litoral', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/ladrillo-ceramico', 1),

('mamposteria', 'Ladrillo cerámico hueco 18×18×33cm (millar)', 'millar',
  135000, 93.10, 0.06,
  'Cerámica El Litoral', 'santa_fe_capital',
  '', 1),

('mamposteria', 'Bloque hormigón 20×20×40cm', 'unidad',
  520, 0.36, 12.5,
  'Corralón local SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/bloque-hormigon', 0),

('mamposteria', 'Cal hidratada 30kg', 'bolsa',
  6200, 4.28, 1.8,
  'Corralón local SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/cal-hidratada', 0),

('mamposteria', 'Arena fina lavada (m³)', 'm3',
  38000, 26.21, 0.09,
  'Arenera El Bajo SF', 'santa_fe_capital',
  '', 1),

('mamposteria', 'Arena gruesa (m³)', 'm3',
  32000, 22.07, 0.07,
  'Arenera El Bajo SF', 'santa_fe_capital',
  '', 1),

('mamposteria', 'Canto rodado/ripio (m³)', 'm3',
  30000, 20.69, 0.06,
  'Arenera El Bajo SF', 'santa_fe_capital',
  '', 1),

('mamposteria', 'Yeso en polvo 25kg', 'bolsa',
  5800, 4.00, 0.40,
  'Corralón local SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/yeso-25kg', 0),

('mamposteria', 'Membrana asfáltica 40kg/10m²', 'rollo',
  52000, 35.86, 0.10,
  'Impermeabilizantes SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/membrana-asfaltica', 0),

-- ── CUBIERTA ────────────────────────────────────────────────────────────────
('cubierta', 'Teja colonial/española unidad', 'unidad',
  2600, 1.79, 18.0,
  'Tejar Litoral', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/teja-colonial', 1),

('cubierta', 'Teja de hormigón esmaltada', 'unidad',
  3200, 2.21, 12.0,
  'Tejar Litoral', 'santa_fe_capital',
  '', 1),

('cubierta', 'Chapa acanalada galvanizada Nº25 (3m)', 'unidad',
  29500, 20.34, 0.33,
  'Chapas y Perfiles SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/chapa-acanalada', 0),

('cubierta', 'Chapa prepintada color (3m)', 'unidad',
  38000, 26.21, 0.33,
  'Chapas y Perfiles SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/chapa-prepintada', 0),

('cubierta', 'Lana de vidrio aislante 50mm (m²)', 'm2',
  4800, 3.31, 1.00,
  'Corralón local SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/lana-vidrio', 0),

('cubierta', 'Viga de madera de quebracho 15×15cm (ml)', 'ml',
  9500, 6.55, 0.40,
  'Maderas Litoral SF', 'santa_fe_capital',
  '', 1),

-- ── INSTALACIONES ────────────────────────────────────────────────────────────
('instalaciones', 'Caño PVC sanitario Ø110mm (barra 3m)', 'unidad',
  8200, 5.66, 0.15,
  'Plomería y Sanitarios SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/cano-pvc-110', 0),

('instalaciones', 'Caño PVC de presión Ø32mm (barra 6m)', 'unidad',
  4200, 2.90, 0.20,
  'Plomería y Sanitarios SF', 'santa_fe_capital',
  '', 1),

('instalaciones', 'Cable eléctrico 2.5mm² (rollo 100m)', 'rollo',
  38000, 26.21, 0.025,
  'Materiales Eléctricos SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/cable-2-5mm', 0),

('instalaciones', 'Cable eléctrico 4mm² (rollo 100m)', 'rollo',
  58000, 40.00, 0.008,
  'Materiales Eléctricos SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/cable-4mm', 0),

('instalaciones', 'Caño conduit eléctrico Ø20mm (barra 3m)', 'unidad',
  950, 0.66, 0.60,
  'Corralón local SF', 'santa_fe_capital',
  '', 0),

('instalaciones', 'Artefacto sanitario (inodoro estándar)', 'unidad',
  85000, 58.62, 0.025,
  'Cerámicas y Sanitarios SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/inodoro', 0),

('instalaciones', 'Pileta de cocina acero inox 1 poceta', 'unidad',
  65000, 44.83, 0.014,
  'Cerámicas y Sanitarios SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/pileta-cocina', 0),

('instalaciones', 'Termotanque eléctrico 80L', 'unidad',
  145000, 100.00, 0.007,
  'Termotanques SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/termotanque-80l', 0),

-- ── TERMINACIONES ────────────────────────────────────────────────────────────
('terminaciones', 'Azulejo blanco 25×35cm (caja 1.74m²)', 'caja',
  13500, 9.31, 0.35,
  'Cerámicas El Litoral', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/azulejo-25x35', 0),

('terminaciones', 'Porcelanato rectificado 60×60cm (m²)', 'm2',
  24000, 16.55, 0.60,
  'Cerámicas El Litoral', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/porcelanato-60x60', 0),

('terminaciones', 'Pintura látex interior 10L', 'lata',
  39000, 26.90, 0.12,
  'Pinturerías Rex SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/pintura-latex-10l', 0),

('terminaciones', 'Pintura exterior/frente 4L', 'lata',
  28000, 19.31, 0.05,
  'Pinturerías Rex SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/pintura-exterior', 0),

('terminaciones', 'Puerta placa interior 0.80×2.05m', 'unidad',
  68000, 46.90, 0.025,
  'Carpintería El Norte SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/puerta-placa', 0),

('terminaciones', 'Ventana aluminio corrediza 1.5×1.1m', 'unidad',
  145000, 100.00, 0.022,
  'Aluminios El Litoral SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/ventana-aluminio', 0),

('terminaciones', 'Cerámica piso antideslizante 35×35cm (m²)', 'm2',
  11500, 7.93, 0.75,
  'Cerámicas El Litoral', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/ceramica-piso', 0),

('terminaciones', 'Pegamento para cerámica 30kg', 'bolsa',
  7500, 5.17, 0.25,
  'Corralón local SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/pegamento-ceramica', 0),

('terminaciones', 'Pastina para juntas 2kg', 'bolsa',
  2800, 1.93, 0.06,
  'Corralón local SF', 'santa_fe_capital',
  '', 0),

-- ── VARIOS ───────────────────────────────────────────────────────────────────
('varios', 'Tablero eléctrico 12 circuitos (c/llaves)', 'unidad',
  85000, 58.62, 0.010,
  'Materiales Eléctricos SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/tablero-12-circuitos', 0),

('varios', 'Grifería monocomando (cocina/baño)', 'unidad',
  48000, 33.10, 0.025,
  'Cerámicas y Sanitarios SF', 'santa_fe_capital',
  'https://www.mercadolibre.com.ar/griferia-monocomando', 0),

('varios', 'Hormigón para contrapiso H-8 (m³)', 'm3',
  130000, 89.66, 0.08,
  'Premix Santa Fe', 'santa_fe_capital',
  '', 1),

('varios', 'Barrera de vapor (rollo 50m²)', 'rollo',
  12000, 8.28, 0.02,
  'Corralón local SF', 'santa_fe_capital',
  '', 0);

-- ─────────────────────────────────────────────────────────────────────────────
-- VERIFICAR INSTALACIÓN
-- ─────────────────────────────────────────────────────────────────────────────
-- SELECT COUNT(*) as costos FROM construction_zone_costs;
-- SELECT COUNT(*) as materiales FROM construction_materials;
-- SELECT category, COUNT(*) as items FROM construction_materials GROUP BY category;
