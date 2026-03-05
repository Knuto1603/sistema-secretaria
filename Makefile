# ============================================================
# Makefile – Comandos rápidos para Docker
# Uso: make <comando>
# ============================================================

.PHONY: up down build restart logs shell-backend shell-db migrate seed fresh

# Levantar todos los servicios
up:
	docker compose up -d

# Apagar todos los servicios
down:
	docker compose down

# Construir/reconstruir imágenes
build:
	docker compose build --no-cache

# Reiniciar un servicio específico (ej: make restart s=backend)
restart:
	docker compose restart $(s)

# Ver logs en tiempo real (ej: make logs s=backend)
logs:
	docker compose logs -f $(s)

# Entrar al contenedor del backend
shell-backend:
	docker compose exec backend sh

# Entrar a MySQL
shell-db:
	docker compose exec db mysql -u secretaria -psecret gestion_academica

# ── Comandos Laravel ─────────────────────────────────────────────────

# Ejecutar migraciones
migrate:
	docker compose exec backend php artisan migrate

# Ejecutar seeders
seed:
	docker compose exec backend php artisan db:seed

# Resetear y migrar con seeders (¡borra datos!)
fresh:
	docker compose exec backend php artisan migrate:fresh --seed

# Generar APP_KEY
key:
	docker compose exec backend php artisan key:generate

# Limpiar caches de Laravel
cache-clear:
	docker compose exec backend php artisan cache:clear
	docker compose exec backend php artisan config:clear
	docker compose exec backend php artisan route:clear
	docker compose exec backend php artisan view:clear

# Optimizar para producción
optimize:
	docker compose exec backend php artisan config:cache
	docker compose exec backend php artisan route:cache
	docker compose exec backend php artisan view:cache

# Primer despliegue completo
setup: up
	@echo "Esperando a que MySQL esté listo..."
	sleep 15
	docker compose exec backend php artisan key:generate
	docker compose exec backend php artisan migrate --seed
	@echo ""
	@echo "✓ Sistema listo"
	@echo "  Frontend: http://localhost:80"
	@echo "  Backend:  http://localhost:8000"
