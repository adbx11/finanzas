-- Ejecutado automáticamente tras restaurar el dump en finanzas_dev.
-- Ajustar según necesidad.

-- Resetear password del usuario admin de desarrollo (legacy sec_usuario).
-- Hash bcrypt generado con: php -r "echo password_hash('dev123456', PASSWORD_BCRYPT);"
-- Reemplazar @dev_password_hash@ en el script shell antes de ejecutar.

UPDATE sec_usuario
SET clave = '@dev_password_hash@'
WHERE usuario = '@dev_admin_user@'
  AND (baja IS NULL OR baja = 0)
LIMIT 1;

-- Opcional: vaciar tablas de mining si se incluyeron en el dump
-- TRUNCATE TABLE mining_log;
-- TRUNCATE TABLE mining_payout_log;
-- TRUNCATE TABLE coin_transactions;
