#!/bin/sh
# ============================================================
# Entrypoint: escribe /var/www/html/.env desde las variables
# de entorno del contenedor y luego arranca PHP-FPM.
#
# Por qué: PHP-FPM con clear_env=yes (default) elimina todo
# el entorno antes de ejecutar PHP, así env() devuelve null.
# Escribir el .env en disco soluciona el problema de raíz.
# ============================================================

set -e

ENV_FILE="/var/www/html/.env"

# Función auxiliar: escribe KEY=VALUE con printf para evitar
# doble expansión del shell (seguro con caracteres especiales).
w() {
  printf '%s=%s\n' "$1" "$2" >> "$ENV_FILE"
}

echo "[entrypoint] Generando $ENV_FILE ..."
: > "$ENV_FILE"   # vaciar / crear el archivo

# --- App ---
w APP_NAME                 "${APP_NAME:-Laravel}"
w APP_ENV                  "${APP_ENV:-production}"
w APP_KEY                  "${APP_KEY:-}"
w APP_DEBUG                "${APP_DEBUG:-false}"
w APP_URL                  "${APP_URL:-http://localhost}"
w APP_LOCALE               "${APP_LOCALE:-en}"
w APP_FALLBACK_LOCALE      "${APP_FALLBACK_LOCALE:-en}"
w APP_FAKER_LOCALE         "${APP_FAKER_LOCALE:-en_US}"
w APP_MAINTENANCE_DRIVER   "${APP_MAINTENANCE_DRIVER:-file}"
w BCRYPT_ROUNDS            "${BCRYPT_ROUNDS:-12}"

# --- Log ---
w LOG_CHANNEL              "${LOG_CHANNEL:-stack}"
w LOG_STACK                "${LOG_STACK:-single}"
w LOG_DEPRECATIONS_CHANNEL "${LOG_DEPRECATIONS_CHANNEL:-null}"
w LOG_LEVEL                "${LOG_LEVEL:-error}"

# --- Base de datos ---
w DB_CONNECTION            "${DB_CONNECTION:-mysql}"
w DB_HOST                  "${DB_HOST:-127.0.0.1}"
w DB_PORT                  "${DB_PORT:-3306}"
w DB_DATABASE              "${DB_DATABASE:-laravel}"
w DB_USERNAME              "${DB_USERNAME:-root}"
w DB_PASSWORD              "${DB_PASSWORD:-}"

# --- Sesión / Caché / Cola ---
w SANCTUM_STATEFUL_DOMAINS "${SANCTUM_STATEFUL_DOMAINS:-localhost}"
w SESSION_DRIVER           "${SESSION_DRIVER:-database}"
w SESSION_LIFETIME         "${SESSION_LIFETIME:-120}"
w SESSION_ENCRYPT          "${SESSION_ENCRYPT:-false}"
w SESSION_PATH             "${SESSION_PATH:-/}"
w SESSION_DOMAIN           "${SESSION_DOMAIN:-null}"
w BROADCAST_CONNECTION     "${BROADCAST_CONNECTION:-log}"
w FILESYSTEM_DISK          "${FILESYSTEM_DISK:-local}"
w QUEUE_CONNECTION         "${QUEUE_CONNECTION:-database}"
w CACHE_STORE              "${CACHE_STORE:-database}"

# --- Redis ---
w REDIS_CLIENT             "${REDIS_CLIENT:-phpredis}"
w REDIS_HOST               "${REDIS_HOST:-127.0.0.1}"
w REDIS_PASSWORD           "${REDIS_PASSWORD:-null}"
w REDIS_PORT               "${REDIS_PORT:-6379}"

# --- Correo ---
w MAIL_MAILER              "${MAIL_MAILER:-log}"
w MAIL_SCHEME              "${MAIL_SCHEME:-null}"
w MAIL_HOST                "${MAIL_HOST:-127.0.0.1}"
w MAIL_PORT                "${MAIL_PORT:-2525}"
w MAIL_USERNAME            "${MAIL_USERNAME:-null}"
w MAIL_PASSWORD            "${MAIL_PASSWORD:-null}"
w MAIL_FROM_ADDRESS        "${MAIL_FROM_ADDRESS:-hello@example.com}"
w MAIL_FROM_NAME           "${MAIL_FROM_NAME:-Laravel}"

# --- LLM / Chatbot ---
w LLM_PROVIDER             "${LLM_PROVIDER:-groq}"
w GROQ_API_KEY             "${GROQ_API_KEY:-}"
w GROQ_MODEL               "${GROQ_MODEL:-llama-3.3-70b-versatile}"

# --- WhatsApp ---
w WHATSAPP_WEBHOOK_SECRET  "${WHATSAPP_WEBHOOK_SECRET:-cambia-esto}"

echo "[entrypoint] .env generado."

# Validar APP_KEY — sin esto Laravel lanza 500 en todas las peticiones
if [ -z "${APP_KEY}" ]; then
  echo "[entrypoint] ERROR: APP_KEY está vacío. Agrega APP_KEY a las variables de entorno en EasyPanel."
  exit 1
fi

# Limpiar config cache para que Laravel lea el .env recién escrito
php artisan config:clear --quiet 2>/dev/null || true

echo "[entrypoint] Iniciando php-fpm..."
exec php-fpm
