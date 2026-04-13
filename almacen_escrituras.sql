-- ============================================================
-- ALMACÉN DE ESCRITURAS Y DOCUMENTOS LEGALES — TasadorIA
-- Ejecutar en phpMyAdmin o: mysql -u user -p tasador_db < almacen_escrituras.sql
-- ============================================================

-- Tabla principal del almacén (se auto-crea también desde send_email.php)
CREATE TABLE IF NOT EXISTS `escrituras_store` (
  `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tasacion_code`           VARCHAR(20)  DEFAULT NULL COMMENT 'Código TA-XXXXXXXX de la tasación asociada',
  `tipo`                    ENUM('escritura','compraventa') NOT NULL DEFAULT 'escritura',
  `direccion`               VARCHAR(300) DEFAULT NULL,
  `matricula`               VARCHAR(100) DEFAULT NULL,
  `superficie`              VARCHAR(60)  DEFAULT NULL,
  `titulares`               VARCHAR(400) DEFAULT NULL COMMENT 'Nombres de titulares / vendedor-comprador',
  `precio_declarado`        VARCHAR(80)  DEFAULT NULL,
  `irregularidades_count`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `irregularidades_alta`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `contact_name`            VARCHAR(150) DEFAULT NULL,
  `contact_email`           VARCHAR(200) DEFAULT NULL,
  `full_report`             MEDIUMTEXT   DEFAULT NULL COMMENT 'JSON completo del análisis IA',
  `notas`                   TEXT         DEFAULT NULL COMMENT 'Notas manuales del admin',
  `revisado`                TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '0=pendiente, 1=revisado',
  `created_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_code`    (`tasacion_code`),
  INDEX `idx_tipo`    (`tipo`),
  INDEX `idx_created` (`created_at`),
  INDEX `idx_irr`     (`irregularidades_alta`, `irregularidades_count`),
  INDEX `idx_revisado`(`revisado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista de resumen rápido (útil para queries en admin)
CREATE OR REPLACE VIEW `escrituras_resumen` AS
SELECT
  e.id,
  e.tasacion_code,
  e.tipo,
  e.direccion,
  e.matricula,
  e.superficie,
  e.titulares,
  e.precio_declarado,
  e.irregularidades_count,
  e.irregularidades_alta,
  e.contact_name,
  e.contact_email,
  e.revisado,
  e.created_at,
  t.price_suggested,
  t.zone   AS zone_label,
  t.city   AS city_label
FROM escrituras_store e
LEFT JOIN tasaciones t ON t.code = e.tasacion_code
ORDER BY e.created_at DESC;

-- ============================================================
-- FIN DEL ARCHIVO
-- ============================================================
