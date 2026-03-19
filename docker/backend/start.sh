#!/bin/sh
# ============================================================
# Entrypoint: escribe .env desde el entorno del contenedor
# (captura TODO lo que EasyPanel inyecta) y arranca php-fpm.
# ============================================================

set -e

ENV_FILE="/var/www/html/.env"

echo "[entrypoint] Generando $ENV_FILE desde el entorno del contenedor..."

# Volcar TODAS las variables de entorno al .env
# Esto captura tanto las vars manuales (MAIL_*, APP_*, etc.)
# como las que EasyPanel inyecta automáticamente (DB_*, etc.)
printenv > "$ENV_FILE"

echo "[entrypoint] .env generado ($(wc -l < "$ENV_FILE") variables)."

# Validar APP_KEY — sin esto Laravel lanza 500 en todas las peticiones
if [ -z "${APP_KEY}" ]; then
  echo "[entrypoint] ERROR: APP_KEY no está definido en las variables de entorno de EasyPanel."
  exit 1
fi

# Limpiar config cache para que Laravel lea el .env recién escrito
php artisan config:clear --quiet 2>/dev/null || true

echo "[entrypoint] Iniciando php-fpm..."
exec php-fpm
