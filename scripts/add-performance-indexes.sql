-- =============================================================================
-- ADB Finanzas — índices de performance (listados + informes)
-- Ejecutar contra finanzas / finanzas_dev.
-- Idempotente a mano: si un índice ya existe, MySQL errorá 1061; omití esa línea.
-- =============================================================================

-- 1) Crítico: filtros a.fecha / BETWEEN / <= en Dashboard, Balance, Mayor, Evolución, etc.
ALTER TABLE `asientos`
  ADD INDEX `asientos_fecha` (`fecha`);

-- Opcional (listados ordenados por fecha+id):
-- ALTER TABLE `asientos` ADD INDEX `asientos_fecha_id` (`fecha`, `id`);

-- 2) Crítico: CotizacionService::getRateForDate (id_moneda + fecha <= ? ORDER BY fecha DESC)
ALTER TABLE `cotizaciones`
  ADD INDEX `cotizaciones_moneda_fecha` (`id_moneda`, `fecha`);

-- Opcional: el KEY `id_moneda` queda cubierto por el prefijo del compuesto; se puede eliminar:
-- ALTER TABLE `cotizaciones` DROP INDEX `id_moneda`;

-- 3) Alto: listados ingresos/pagos por período
ALTER TABLE `ingresos`
  ADD INDEX `ingresos_fecha` (`fecha`);

ALTER TABLE `pagos`
  ADD INDEX `pagos_fecha` (`fecha`);

-- 4) Alto: tarjetas / gastos por concepto + fecha
ALTER TABLE `pagos`
  ADD INDEX `pagos_concepto_fecha` (`id_cuenta_concepto`, `fecha`);

-- Opcional: ingresos por concepto + fecha
-- ALTER TABLE `ingresos` ADD INDEX `ingresos_concepto_fecha` (`id_cuenta_concepto`, `fecha`);

-- 5) Medio: LIKE/equality por código de cuenta
ALTER TABLE `cuentas`
  ADD INDEX `cuentas_codigo` (`codigo`);

-- 6) Medio: Mayor / saldos por cuenta (join items → asiento)
ALTER TABLE `asiento_items`
  ADD INDEX `asiento_items_cuenta_asiento` (`id_cuenta`, `id_asiento`);

-- Opcional: filtros por moneda en items
-- ALTER TABLE `asiento_items` ADD INDEX `asiento_items_moneda` (`id_moneda`);

-- 7) Medio: crypto balances / último precio por coin+ts
-- (ya existe KEY `coin`; este compuesto ayuda a ORDER BY ts)
ALTER TABLE `coin_transactions`
  ADD INDEX `coin_transactions_coin_ts` (`coin`, `ts`);

-- =============================================================================
-- Verificación
-- =============================================================================
SELECT
  TABLE_NAME,
  INDEX_NAME,
  GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'asientos',
    'asiento_items',
    'cotizaciones',
    'ingresos',
    'pagos',
    'cuentas',
    'coin_transactions'
  )
  AND INDEX_NAME IN (
    'asientos_fecha',
    'cotizaciones_moneda_fecha',
    'ingresos_fecha',
    'pagos_fecha',
    'pagos_concepto_fecha',
    'cuentas_codigo',
    'asiento_items_cuenta_asiento',
    'coin_transactions_coin_ts'
  )
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;
