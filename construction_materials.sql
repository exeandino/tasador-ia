-- ============================================================
--  TasadorIA — construction_materials.sql
--  Precios de materiales de construcción Argentina
--  Valores en ARS y USD · Tipo de cambio referencia: $1.450/USD
--  Actualizado: abril 2026
--  Fuente: UECARA, distribuidoras, relevamiento propio
-- ============================================================

CREATE TABLE IF NOT EXISTS construction_materials (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    region       VARCHAR(60)    NOT NULL DEFAULT '',    -- '' = nacional, 'santa_fe_capital' = regional
    category     VARCHAR(40)    NOT NULL,
    material     VARCHAR(120)   NOT NULL,
    unit         VARCHAR(20)    NOT NULL,
    price_ars    DECIMAL(12,2)  NOT NULL,
    price_usd    DECIMAL(10,4)  NOT NULL,
    qty_per_m2   DECIMAL(8,4)   NOT NULL DEFAULT 0,    -- cantidad por m² construido
    supplier     VARCHAR(100)   DEFAULT NULL,
    source_url   VARCHAR(255)   DEFAULT NULL,
    is_local     TINYINT(1)     NOT NULL DEFAULT 0,
    active       TINYINT(1)     NOT NULL DEFAULT 1,
    updated_at   DATE           NOT NULL DEFAULT (CURDATE()),
    notes        VARCHAR(255)   DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

TRUNCATE TABLE construction_materials;

-- ============================================================
--  NACIONALES (region = '')
-- ============================================================

-- ESTRUCTURA
INSERT INTO construction_materials (region, category, material, unit, price_ars, price_usd, qty_per_m2, notes) VALUES
('', 'estructura', 'Cemento Portland 50kg (CPN 40)', 'bolsa',  15200.00,  10.48, 4.50, 'Por m² de estructura incluye losa+columnas+fundaciones'),
('', 'estructura', 'Hierro Ø8mm × 12m (ADN 420)', 'barra',    24800.00,  17.10, 0.45, NULL),
('', 'estructura', 'Hierro Ø12mm × 12m (ADN 420)', 'barra',   55500.00,  38.28, 0.12, NULL),
('', 'estructura', 'Arena gruesa para hormigón', 'm3',          46000.00,  31.72, 0.09, 'Arena para mezcla de fundación'),
('', 'estructura', 'Piedra partida 6/20', 'm3',                 62000.00,  42.76, 0.07, NULL),
('', 'estructura', 'Block de hormigón 20×20×40', 'unidad',       980.00,   0.68, 8.00, 'Alternativa a ladrillo en muros estructurales'),

-- MAMPOSTERÍA
('', 'mamposteria', 'Ladrillo cerámico hueco 8×18×33 (millar)', 'millar', 118000.00, 81.38, 0.10, NULL),
('', 'mamposteria', 'Ladrillo macizo refractario 24×12×6 (millar)', 'millar', 185000.00, 127.59, 0.02, 'Para hogares y parrillas'),
('', 'mamposteria', 'Arena fina para revoque', 'm3',              38000.00,  26.21, 0.09, NULL),
('', 'mamposteria', 'Cal hidratada 30kg', 'bolsa',                 8200.00,   5.66, 1.80, NULL),
('', 'mamposteria', 'Yeso fino 40kg', 'bolsa',                    12500.00,   8.62, 0.85, NULL),
('', 'mamposteria', 'Adhesivo cerámico Klaukol flex 30kg', 'bolsa', 14800.00, 10.21, 0.45, NULL),

-- CUBIERTA
('', 'cubierta', 'Teja colonial cerámica 16×26cm', 'unidad',     3200.00,   2.21, 18.00, '18 tejas por m² de cubierta inclinada'),
('', 'cubierta', 'Teja cerámica esmaltada 20×30cm', 'unidad',    4500.00,   3.10, 14.00, NULL),
('', 'cubierta', 'Losa de hormigón (encofrado+armado)', 'm2',   85000.00,  58.62,  1.00, 'Costo completo losa incluye mano de obra'),
('', 'cubierta', 'Membrana asfáltica 4mm c/aluminio', 'm2',     18500.00,  12.76,  1.10, 'Con solape del 10%'),
('', 'cubierta', 'Aislante térmico EPS 50mm', 'm2',             12000.00,   8.28,  1.00, NULL),
('', 'cubierta', 'Perfil C galvanizado 100×50mm', 'm',           8200.00,   5.66,  0.80, 'Estructura de techo metálico'),

-- INSTALACIONES ELÉCTRICAS
('', 'inst_electricas', 'Cable 2,5mm² IRAM (rollo 100m)', 'rollo', 185000.00, 127.59, 0.018, '1,8m por m² promedio'),
('', 'inst_electricas', 'Cable 4mm² IRAM (rollo 100m)', 'rollo',   245000.00, 168.97, 0.006, NULL),
('', 'inst_electricas', 'Cañería corrugada Ø20mm (m)', 'm',          980.00,   0.68, 3.20, NULL),
('', 'inst_electricas', 'Tablero eléctrico 24 polos', 'unidad',   125000.00,  86.21, 0.015, '1 tablero cada ~65m²'),
('', 'inst_electricas', 'Disyuntor termo 20A 2P Schneider', 'unidad', 28000.00, 19.31, 0.06, NULL),
('', 'inst_electricas', 'Tomacorriente 2P+T IRAM Bticino', 'unidad', 18500.00, 12.76, 0.50, NULL),
('', 'inst_electricas', 'Llave térmica 10A Schneider', 'unidad',   12800.00,   8.83, 0.30, NULL),

-- INSTALACIONES SANITARIAS
('', 'inst_sanitarias', 'Caño PVC 4" × 3m (desagüe)', 'unidad',  28500.00,  19.66, 0.08, NULL),
('', 'inst_sanitarias', 'Caño PVC 2" × 3m (desagüe)', 'unidad',  15200.00,  10.48, 0.12, NULL),
('', 'inst_sanitarias', 'Caño PPR 20mm × 3m (agua fría/caliente)', 'unidad', 12800.00, 8.83, 0.25, NULL),
('', 'inst_sanitarias', 'Inodoro Ferrum Ecoline c/mochila', 'unidad', 285000.00, 196.55, 0.012, '1 cada 80m²'),
('', 'inst_sanitarias', 'Lavatorio Ferrum 45×35', 'unidad',       185000.00, 127.59, 0.012, NULL),
('', 'inst_sanitarias', 'Ducha Longvie 6000W', 'unidad',          145000.00, 100.00, 0.010, NULL),
('', 'inst_sanitarias', 'Grifería baño FV Rapid cromada', 'unidad', 95000.00, 65.52, 0.025, NULL),
('', 'inst_sanitarias', 'Termotanque Rheem 50L eléctrico', 'unidad', 380000.00, 262.07, 0.010, NULL),

-- INSTALACIONES GAS
('', 'inst_gas', 'Caño de cobre 1/2" × 3m', 'unidad',            58000.00,  40.00, 0.12, NULL),
('', 'inst_gas', 'Cocina Longvie 4 hornallas (incl. instalación)', 'unidad', 420000.00, 289.66, 0.008, NULL),
('', 'inst_gas', 'Calefacción a gas tiro balanceado 5000 kcal', 'unidad', 380000.00, 262.07, 0.008, '1 cada 60m²'),
('', 'inst_gas', 'Regulador de gas GNC', 'unidad',                 42000.00,  28.97, 0.010, NULL),

-- TERMINACIONES — PISOS
('', 'terminaciones', 'Porcelanato rectificado 60×60 (1ª calidad)', 'm2', 28500.00, 19.66, 0.60, NULL),
('', 'terminaciones', 'Porcelanato madera símil roble 20×120', 'm2', 35000.00, 24.14, 0.60, NULL),
('', 'terminaciones', 'Cerámica baño/cocina 33×33 (1ª)', 'm2',    18500.00,  12.76, 0.12, NULL),
('', 'terminaciones', 'Parquet flotante 8mm (m² c/inst.)', 'm2',  32000.00,  22.07, 0.25, NULL),
('', 'terminaciones', 'Microcemento alisado (material+mano de obra)', 'm2', 45000.00, 31.03, 0.15, NULL),

-- TERMINACIONES — REVESTIMIENTOS Y PINTURA
('', 'terminaciones', 'Pintura látex lavable interior 10L', 'lata', 46000.00, 31.72, 0.12, '1 lata = 8-10m² dos manos'),
('', 'terminaciones', 'Pintura exterior impermeabilizante 10L', 'lata', 68000.00, 46.90, 0.06, NULL),
('', 'terminaciones', 'Revestimiento cerámico pared baño 30×60', 'm2', 24000.00, 16.55, 0.12, NULL),
('', 'terminaciones', 'Masilla plástica interior 30kg', 'balde',  28500.00,  19.66, 0.10, NULL),

-- TERMINACIONES — CARPINTERÍA
('', 'terminaciones', 'Puerta placa interior 80×200 c/marco', 'unidad', 185000.00, 127.59, 0.04, '1 cada 25m²'),
('', 'terminaciones', 'Puerta PVC exterior batiente 90×200', 'unidad', 320000.00, 220.69, 0.010, NULL),
('', 'terminaciones', 'Ventana aluminio DVH 100×110', 'unidad',   285000.00, 196.55, 0.020, NULL),
('', 'terminaciones', 'Ventana aluminio simple 100×110', 'unidad', 165000.00, 113.79, 0.020, NULL),

-- HONORARIOS Y PERMISOS
('', 'honorarios', 'Visado planos municipales (promedio)', 'gestión', 180000.00, 124.14, 0.008, 'Variable según municipio'),
('', 'honorarios', 'Dirección de obra arquitecto (%)', 'servicio', 0, 0, 0, '8-12% del costo total, cotizar por separado');

-- ============================================================
--  REGIONALES — Santa Fe Capital (precios locales verificados)
-- ============================================================
INSERT INTO construction_materials (region, category, material, unit, price_ars, price_usd, qty_per_m2, is_local, supplier, notes) VALUES
('santa_fe_capital', 'estructura',   'Cemento Portland Holcim 50kg',         'bolsa',   14800.00,  10.21, 4.50, 1, 'Distribuidoras zona Norte SF', NULL),
('santa_fe_capital', 'estructura',   'Arena gruesa Río Paraná',               'm3',      42000.00,  28.97, 0.09, 1, 'Areneras San José del Rincón', 'Precio puesto en obra'),
('santa_fe_capital', 'mamposteria',  'Ladrillo cerámico Alberdi 8×18×33 (millar)', 'millar', 112000.00, 77.24, 0.10, 1, 'Ladrillera Alberdi S.A.', NULL),
('santa_fe_capital', 'terminaciones','Porcelanato cerámico Alberdi 60×60',   'm2',      26500.00,  18.28, 0.60, 1, 'Ferretería El Constructor SF', NULL),
('santa_fe_capital', 'terminaciones','Pintura látex Tersuave 10L',           'lata',    44000.00,  30.34, 0.12, 1, 'Casa del Pintor SF', NULL),
('santa_fe_capital', 'inst_sanitarias','Inodoro FV Florencia plus',          'unidad',  295000.00, 203.45, 0.012, 1, 'Sanitarios del Litoral', NULL),
('santa_fe_capital', 'cubierta',     'Membrana asfáltica Confalt 4mm',       'm2',      17800.00,  12.28, 1.10, 1, 'Materiales La Cúpula SF', NULL);

-- ============================================================
--  REGIONALES — Rosario
-- ============================================================
INSERT INTO construction_materials (region, category, material, unit, price_ars, price_usd, qty_per_m2, is_local, notes) VALUES
('rosario', 'estructura',   'Cemento Portland Loma Negra 50kg',   'bolsa',  14500.00,  10.00, 4.50, 1, NULL),
('rosario', 'mamposteria',  'Ladrillo cerámico Vista Alegre (millar)', 'millar', 108000.00, 74.48, 0.10, 1, NULL),
('rosario', 'terminaciones','Pintura Sinteplast Recuplast 10L',   'lata',   47000.00,  32.41, 0.12, 1, NULL);
