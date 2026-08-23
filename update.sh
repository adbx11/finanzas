#!/usr/bin/env bash
# Actualiza ADB Finanzas en el servidor (git pull + deps + build + migrate + caches).
# Uso:
#   ./update.sh
#   ./update.sh --skip-frontend          # sin npm (si el build se hace en otro lado)
#   ./update.sh --skip-fpm               # no recarga PHP-FPM
#   BRANCH=main PHP_FPM_SERVICE=php8.3-fpm ./update.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

SKIP_FRONTEND=0
SKIP_FPM=0
for arg in "$@"; do
    case "$arg" in
        --skip-frontend) SKIP_FRONTEND=1 ;;
        --skip-fpm) SKIP_FPM=1 ;;
        -h|--help)
            sed -n '2,8p' "$0"
            exit 0
            ;;
        *)
            echo "Opción desconocida: $arg" >&2
            exit 1
            ;;
    esac
done

BRANCH="${BRANCH:-}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-}"

log() { printf '\n==> %s\n' "$*"; }

if [[ ! -f artisan || ! -f composer.json ]]; then
    echo "Error: ejecutá esto desde la raíz del proyecto Laravel." >&2
    exit 1
fi

if [[ -z "$PHP_FPM_SERVICE" ]]; then
    for svc in php8.3-fpm php8.2-fpm php8.1-fpm php-fpm; do
        if systemctl list-unit-files "${svc}.service" &>/dev/null \
            && systemctl is-enabled "${svc}" &>/dev/null; then
            PHP_FPM_SERVICE="$svc"
            break
        fi
        if systemctl is-active --quiet "$svc" 2>/dev/null; then
            PHP_FPM_SERVICE="$svc"
            break
        fi
    done
fi

log "Directorio: $ROOT"

log "Modo mantenimiento ON"
$PHP_BIN artisan down --retry=60 || true

cleanup() {
    log "Modo mantenimiento OFF"
    $PHP_BIN artisan up || true
}
trap cleanup EXIT

log "git pull"
if [[ -n "$BRANCH" ]]; then
    git pull --ff-only origin "$BRANCH"
else
    git pull --ff-only
fi

log "composer install"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

if [[ "$SKIP_FRONTEND" -eq 0 ]]; then
    log "npm ci && npm run build"
    if [[ -f package-lock.json ]]; then
        npm ci
    else
        npm install
    fi
    npm run build
else
    log "Frontend omitido (--skip-frontend)"
fi

log "Migraciones"
$PHP_BIN artisan migrate --force

log "Cachés"
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache 2>/dev/null || true

if [[ "$SKIP_FPM" -eq 0 && -n "${PHP_FPM_SERVICE:-}" ]]; then
    log "Reload $PHP_FPM_SERVICE"
    if systemctl is-active --quiet "$PHP_FPM_SERVICE"; then
        if sudo -n systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null; then
            :
        else
            sudo systemctl reload "$PHP_FPM_SERVICE"
        fi
    else
        echo "Aviso: $PHP_FPM_SERVICE no está activo; se omite reload." >&2
    fi
elif [[ "$SKIP_FPM" -eq 0 ]]; then
    echo "Aviso: no se detectó PHP-FPM; reload omitido (usá PHP_FPM_SERVICE=php8.3-fpm)." >&2
fi

log "Update OK"
