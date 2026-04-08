-- TasadorIA v5 — Actualización de tabla tasaciones
-- Agregar nuevas columnas para los campos expandidos
-- Ejecutar en phpMyAdmin → BD tasador → SQL

ALTER TABLE `tasaciones`
  ADD COLUMN IF NOT EXISTS `ambientes`    tinyint(4) DEFAULT NULL AFTER `city`,
  ADD COLUMN IF NOT EXISTS `bedrooms`     tinyint(4) DEFAULT NULL AFTER `ambientes`,
  ADD COLUMN IF NOT EXISTS `bathrooms`    tinyint(4) DEFAULT NULL AFTER `bedrooms`,
  ADD COLUMN IF NOT EXISTS `garages`      tinyint(4) DEFAULT NULL AFTER `bathrooms`,
  ADD COLUMN IF NOT EXISTS `floor`        tinyint(4) DEFAULT NULL AFTER `garages`,
  ADD COLUMN IF NOT EXISTS `has_elevator` tinyint(1) DEFAULT 0    AFTER `floor`,
  ADD COLUMN IF NOT EXISTS `expensas_ars` decimal(12,2) DEFAULT NULL AFTER `has_elevator`,
  ADD COLUMN IF NOT EXISTS `escritura`    varchar(30) DEFAULT NULL AFTER `expensas_ars`,
  ADD COLUMN IF NOT EXISTS `tiene_deuda`  tinyint(1) DEFAULT 0    AFTER `escritura`,
  ADD COLUMN IF NOT EXISTS `deuda_usd`    decimal(12,2) DEFAULT NULL AFTER `tiene_deuda`,
  ADD COLUMN IF NOT EXISTS `price_suggested` decimal(14,2) DEFAULT NULL AFTER `deuda_usd`,
  ADD COLUMN IF NOT EXISTS `price_ppm2`   decimal(10,2) DEFAULT NULL AFTER `price_suggested`,
  ADD COLUMN IF NOT EXISTS `address`      varchar(255) DEFAULT NULL AFTER `price_ppm2`,
  ADD COLUMN IF NOT EXISTS `lat`          decimal(10,7) DEFAULT NULL AFTER `address`,
  ADD COLUMN IF NOT EXISTS `lng`          decimal(10,7) DEFAULT NULL AFTER `lat`;

-- Verificar que quedó bien
DESCRIBE tasaciones;
