#!/usr/bin/env bash
#
# Copia la base de datos de producción a finanzas_dev para pruebas.
# Uso: ./scripts/db-sync.sh [--force]
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$SCRIPT_DIR/.env.sync"

FORCE=0
if [[ "${1:-}" == "--force" ]]; then
  FORCE=1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: No existe $ENV_FILE"
  echo "Copiá scripts/.env.sync.example a scripts/.env.sync y completá credenciales."
  exit 1
fi

# shellcheck disable=SC1090
source "$ENV_FILE"

require_var() {
  if [[ -z "${!1:-}" ]]; then
    echo "ERROR: variable $1 no definida en .env.sync"
    exit 1
  fi
}

for v in SYNC_SOURCE_HOST SYNC_SOURCE_DATABASE SYNC_SOURCE_USER SYNC_SOURCE_PASSWORD \
         SYNC_TARGET_HOST SYNC_TARGET_DATABASE SYNC_TARGET_USER SYNC_TARGET_PASSWORD \
         SYNC_DEV_ADMIN_USER SYNC_DEV_ADMIN_PASSWORD; do
  require_var "$v"
done

SYNC_SOURCE_PORT="${SYNC_SOURCE_PORT:-3306}"
SYNC_TARGET_PORT="${SYNC_TARGET_PORT:-3306}"
SYNC_COMPRESS="${SYNC_COMPRESS:-1}"
SYNC_SKIP_TABLES="${SYNC_SKIP_TABLES:-}"
SYNC_TMP_DIR="${SYNC_TMP_DIR:-$PROJECT_DIR/storage/tmp}"

mkdir -p "$SYNC_TMP_DIR"

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
DUMP_BASE="$SYNC_TMP_DIR/finanzas_${TIMESTAMP}.sql"
DUMP_FILE="$DUMP_BASE"
if [[ "$SYNC_COMPRESS" == "1" ]]; then
  DUMP_FILE="${DUMP_BASE}.gz"
fi

echo "==> Origen:  ${SYNC_SOURCE_USER}@${SYNC_SOURCE_HOST}:${SYNC_SOURCE_PORT}/${SYNC_SOURCE_DATABASE}"
echo "==> Destino: ${SYNC_TARGET_USER}@${SYNC_TARGET_HOST}:${SYNC_TARGET_PORT}/${SYNC_TARGET_DATABASE}"
echo "==> Dump:    $DUMP_FILE"

if [[ "$FORCE" != "1" ]]; then
  read -r -p "¿Continuar? Se SOBRESCRIBIRÁ la base destino. [y/N] " ans
  if [[ ! "$ans" =~ ^[Yy]$ ]]; then
    echo "Cancelado."
    exit 0
  fi
fi

DUMP_ARGS=(
  --host="$SYNC_SOURCE_HOST"
  --port="$SYNC_SOURCE_PORT"
  --user="$SYNC_SOURCE_USER"
  --single-transaction
  --quick
  --routines
  --triggers
  --set-gtid-purged=OFF
  --default-character-set=utf8mb4
  "$SYNC_SOURCE_DATABASE"
)

if [[ -n "$SYNC_SKIP_TABLES" ]]; then
  IFS=',' read -ra TABLES <<< "$SYNC_SKIP_TABLES"
  for t in "${TABLES[@]}"; do
    t="$(echo "$t" | xargs)"
    [[ -n "$t" ]] && DUMP_ARGS+=(--ignore-table="${SYNC_SOURCE_DATABASE}.${t}")
  done
fi

echo "==> Exportando..."
START=$(date +%s)

if [[ "$SYNC_COMPRESS" == "1" ]]; then
  MYSQL_PWD="$SYNC_SOURCE_PASSWORD" mysqldump "${DUMP_ARGS[@]}" | gzip > "$DUMP_FILE"
else
  MYSQL_PWD="$SYNC_SOURCE_PASSWORD" mysqldump "${DUMP_ARGS[@]}" > "$DUMP_FILE"
fi

echo "==> Recreando base destino..."
MYSQL_PWD="$SYNC_TARGET_PASSWORD" mysql \
  --host="$SYNC_TARGET_HOST" \
  --port="$SYNC_TARGET_PORT" \
  --user="$SYNC_TARGET_USER" \
  -e "DROP DATABASE IF EXISTS \`${SYNC_TARGET_DATABASE}\`; CREATE DATABASE \`${SYNC_TARGET_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> Importando..."
if [[ "$SYNC_COMPRESS" == "1" ]]; then
  gunzip -c "$DUMP_FILE" | MYSQL_PWD="$SYNC_TARGET_PASSWORD" mysql \
    --host="$SYNC_TARGET_HOST" \
    --port="$SYNC_TARGET_PORT" \
    --user="$SYNC_TARGET_USER" \
    "$SYNC_TARGET_DATABASE"
else
  MYSQL_PWD="$SYNC_TARGET_PASSWORD" mysql \
    --host="$SYNC_TARGET_HOST" \
    --port="$SYNC_TARGET_PORT" \
    --user="$SYNC_TARGET_USER" \
    "$SYNC_TARGET_DATABASE" < "$DUMP_FILE"
fi

SYNC_RESET_PASSWORD="${SYNC_RESET_PASSWORD:-0}"

if [[ "$SYNC_RESET_PASSWORD" == "1" ]]; then
  echo "==> Post-restore (password dev bcrypt)..."
  if command -v php >/dev/null 2>&1; then
    DEV_HASH="$(php -r "echo password_hash('${SYNC_DEV_ADMIN_PASSWORD}', PASSWORD_BCRYPT);")"
    POST_SQL="$SYNC_TMP_DIR/post_${TIMESTAMP}.sql"
    sed \
      -e "s|@dev_password_hash@|${DEV_HASH}|g" \
      -e "s|@dev_admin_user@|${SYNC_DEV_ADMIN_USER}|g" \
      "$SCRIPT_DIR/db-sync-post.sql" > "$POST_SQL"

    MYSQL_PWD="$SYNC_TARGET_PASSWORD" mysql \
      --host="$SYNC_TARGET_HOST" \
      --port="$SYNC_TARGET_PORT" \
      --user="$SYNC_TARGET_USER" \
      "$SYNC_TARGET_DATABASE" < "$POST_SQL"
    rm -f "$POST_SQL"
  else
    echo "WARN: php no encontrado; saltando reset de password."
  fi
else
  echo "==> Post-restore: password sin cambios (SYNC_RESET_PASSWORD=0)"
fi

END=$(date +%s)
ELAPSED=$((END - START))
SIZE="$(du -h "$DUMP_FILE" | cut -f1)"

echo "==> Listo en ${ELAPSED}s (dump: ${SIZE})"
if [[ "$SYNC_RESET_PASSWORD" == "1" ]]; then
  echo "    Usuario dev: ${SYNC_DEV_ADMIN_USER}"
  echo "    Password:    ${SYNC_DEV_ADMIN_PASSWORD}"
fi
echo ""
echo "Verificación rápida:"
MYSQL_PWD="$SYNC_TARGET_PASSWORD" mysql \
  --host="$SYNC_TARGET_HOST" \
  --port="$SYNC_TARGET_PORT" \
  --user="$SYNC_TARGET_USER" \
  "$SYNC_TARGET_DATABASE" \
  -e "SELECT 'asientos' AS tabla, COUNT(*) AS n FROM asientos
      UNION SELECT 'ingresos', COUNT(*) FROM ingresos
      UNION SELECT 'pagos', COUNT(*) FROM pagos
      UNION SELECT 'cuentas', COUNT(*) FROM cuentas;"
