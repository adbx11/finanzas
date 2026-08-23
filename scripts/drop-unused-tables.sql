-- =============================================================================
-- DROP de tablas no usadas por ADB Finanzas v2
-- =============================================================================
-- Ejecutar SOLO tras el corte a v2 y con backup previo.
-- Destino típico: finanzas_dev / producción ya migrada.
--
-- Bloque A: seguro (AFIP, mining, junk, geo) — no las usa la app Laravel.
-- Bloque B: opcional — solo si NO vas a implementar FCI / plazos / plantillas /
--           movimientos genéricos / re-import desde sec_*.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- A) Seguras de eliminar
-- -----------------------------------------------------------------------------

-- AFIP / factura electrónica
DROP TABLE IF EXISTS `factura_items`;
DROP TABLE IF EXISTS `facturas`;
DROP TABLE IF EXISTS `puntos_de_venta`;
DROP TABLE IF EXISTS `cuits_emisores`;
DROP TABLE IF EXISTS `tipos_comprobante_rel`;
DROP TABLE IF EXISTS `tipos_comprobante`;
DROP TABLE IF EXISTS `alicuotas_iva`;
DROP TABLE IF EXISTS `condiciones_iva`;
DROP TABLE IF EXISTS `tipos_doc`;

-- Mining
DROP TABLE IF EXISTS `mining_payout_log`;
DROP TABLE IF EXISTS `mining_log`;

-- Junk / utilidades legacy no usadas
DROP TABLE IF EXISTS `C3P0TestTable`;
DROP TABLE IF EXISTS `image`;
DROP TABLE IF EXISTS `email_store_error`;
DROP TABLE IF EXISTS `email_store_sent`;
DROP TABLE IF EXISTS `fechas`;
DROP TABLE IF EXISTS `fechas_anios`;
DROP TABLE IF EXISTS `fechas_meses`;

-- Geografía (solo usada en contexto AFIP / legacy)
DROP TABLE IF EXISTS `ciudad`;
DROP TABLE IF EXISTS `provincia`;
DROP TABLE IF EXISTS `pais`;

-- -----------------------------------------------------------------------------
-- B) Opcional — descomentar si confirmás que no las retomarás
-- -----------------------------------------------------------------------------

-- Informe FCI
-- DROP TABLE IF EXISTS `fci_cotizaciones`;

-- Plazos fijos
-- DROP TABLE IF EXISTS `plazos_fijos`;

-- Movimientos genéricos / tipos (ABM legacy incompleto)
-- DROP TABLE IF EXISTS `movimientos`;
-- DROP TABLE IF EXISTS `tipos_movimiento`;

-- Plantillas email
-- DROP TABLE IF EXISTS `template`;

-- Seguridad legacy (RBAC). Conservar `sec_usuario` si aún necesitás
-- `php artisan finanzas:import-users`. El resto se puede dropear antes.
-- DROP TABLE IF EXISTS `sec_perfil_programa_funcion`;
-- DROP TABLE IF EXISTS `sec_perfil_programa`;
-- DROP TABLE IF EXISTS `sec_programa_funcion`;
-- DROP TABLE IF EXISTS `sec_usuario_perfil`;
-- DROP TABLE IF EXISTS `sec_funcion`;
-- DROP TABLE IF EXISTS `sec_programa`;
-- DROP TABLE IF EXISTS `sec_perfil`;
-- DROP TABLE IF EXISTS `sec_usuario`;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación rápida (debe devolver 0 filas para las del bloque A):
-- SHOW TABLES LIKE 'factura%';
-- SHOW TABLES LIKE 'mining%';
-- SHOW TABLES LIKE 'cuits%';
