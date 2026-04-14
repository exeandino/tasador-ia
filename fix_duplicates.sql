-- ============================================================
-- TasadorIA — fix_duplicates.sql
-- Ejecutar UNA VEZ en phpMyAdmin para:
--   1. Rellenar external_id vacíos desde la URL
--   2. Eliminar duplicados (quedarse con el registro más nuevo)
--   3. Agregar UNIQUE KEY para evitar futuros duplicados
-- ============================================================

-- 1. Rellenar external_id donde es NULL usando hash de la URL
UPDATE market_listings
SET external_id = CONCAT('zp_', LEFT(MD5(TRIM(SUBSTRING_INDEX(url, '?', 1))), 12))
WHERE external_id IS NULL
  AND url IS NOT NULL
  AND url != '';

-- 2. Eliminar duplicados: para cada (source, external_id) repetido,
--    conservar solo el de mayor id (el más reciente)
DELETE m1
FROM market_listings m1
INNER JOIN market_listings m2
  ON  m1.source      = m2.source
  AND m1.external_id = m2.external_id
  AND m1.id          < m2.id
WHERE m1.external_id IS NOT NULL;

-- 3. Agregar UNIQUE KEY para que ON DUPLICATE KEY UPDATE funcione en el futuro
--    (solo si no existe ya)
ALTER TABLE market_listings
  ADD UNIQUE KEY IF NOT EXISTS `uniq_source_ext` (`source`, `external_id`);

-- Verificar resultado
SELECT source, COUNT(*) as total, COUNT(external_id) as con_ext_id
FROM market_listings
GROUP BY source
ORDER BY total DESC;
