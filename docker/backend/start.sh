#!/bin/sh
# ============================================================
# Entrypoint – genera .env desde variables de entorno del
# contenedor y arranca PHP-FPM.
#
# Por qué existe esto:
#   PHP-FPM con clear_env=yes (default) limpia el entorno
#   antes de ejecutar PHP, así que env() en Laravel devuelve
#   null aunque EasyPanel haya inyectado las variables.
#   La solución definitiva es escribir el .env en disco al
#   arrancar el contenedor, antes de que PHP-FPM empiece.
# ============================================================

set -e

ENV_FILE="/var/www/html/.env"

echo "[entrypoint] Escribiendo .env desde variables de entorno del contenedor..."

cat > "$ENV_FILE" <<EOF
APP_NAME=${APP_NAME:-Laravel}
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY:-}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}

APP_LOCALE=${APP_LOCALE:-en}
APP_FALLBACK_LOCALE=${APP_FALLBACK_LOCALE:-en}
APP_FAKER_LOCALE=${APP_FAKER_LOCALE:-en_US}
APP_MAINTENANCE_DRIVER=${APP_MAINTENANCE_DRIVER:-file}
BCRYPT_ROUNDS=${BCRYPT_ROUNDS:-12}

LOG_CHANNEL=${LOG_CHANNEL:-stack}
LOG_STACK=${LOG_STACK:-single}
LOG_DEPRECATIONS_CHANNEL=${LOG_DEPRECATIONS_CHANNEL:-null}
LOG_LEVEL=${LOG_LEVEL:-error}

DB_CONNECTION=${DB_CONNECTION:-mysql}
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-laravel}
DB_USERNAME=${DB_USERNAME:-root}
DB_PASSWORD=${DB_PASSWORD:-}

SANCTUM_STATEFUL_DOMAINS=${SANCTUM_STATEFUL_DOMAINS:-localhost}

SESSION_DRIVER=${SESSION_DRIVER:-database}
SESSION_LIFETIME=${SESSION_LIFETIME:-120}
SESSION_ENCRYPT=${SESSION_ENCRYPT:-false}
SESSION_PATH=${SESSION_PATH:-/}
SESSION_DOMAIN=${SESSION_DOMAIN:-null}

BROADCAST_CONNECTION=${BROADCAST_CONNECTION:-log}
FILESYSTEM_DISK=${FILESYSTEM_DISK:-local}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}

CACHE_STORE=${CACHE_STORE:-database}

REDIS_CLIENT=${REDIS_CLIENT:-phpredis}
REDIS_HOST=${REDIS_HOST:-127.0.0.1}
REDIS_PASSWORD=${REDIS_PASSWORD:-null}
REDIS_PORT=${REDIS_PORT:-6379}

MAIL_MAILER=${MAIL_MAILER:-log}
MAIL_SCHEME=${MAIL_SCHEME:-null}
MAIL_HOST=${MAIL_HOST:-127.0.0.1}
MAIL_PORT=${MAIL_PORT:-2525}
MAIL_USERNAME=${MAIL_USERNAME:-null}
MAIL_PASSWORD=${MAIL_PASSWORD:-null}
MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-hello@example.com}
MAIL_FROM_NAME="${MAIL_FROM_NAME:-Laravel}"

LLM_PROVIDER=${LLM_PROVIDER:-groq}
GROQ_API_KEY=${GROQ_API_KEY:-}
GROQ_MODEL=${GROQ_MODEL:-llama-3.3-70b-versatile}

WHATSAPP_WEBHOOK_SECRET=${WHATSAPP_WEBHOOK_SECRET:-cambia-esto}
EOF

echo "[entrypoint] .env generado correctamente."

# Limpiar config cache para que Laravel lea el .env recién escrito
php artisan config:clear --quiet 2>/dev/null || true

echo "[entrypoint] Iniciando PHP-FPM..."
exec php-fpm
