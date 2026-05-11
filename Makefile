# ============================================================
# Makefile – Comandos rápidos para Docker
# Uso: make <comando>
# ============================================================

.PHONY: up down build restart logs shell-backend shell-db migrate seed fresh \
        local-up local-down local-db local-setup

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

# ── Desarrollo local (DB + Redis en Docker, servidores nativos) ──────

# Levantar solo infraestructura local
local-up:
	docker compose -f docker-compose.local.yml up -d
	@echo ""
	@echo "Infraestructura lista:"
	@echo "  MySQL → localhost:3306  (user: secretaria / pass: secret)"
	@echo "  Redis → localhost:6379"
	@echo ""
	@echo "Ahora levanta los servidores:"
	@echo "  cd backend  && php artisan serve    → http://localhost:8000"
	@echo "  cd frontend && ng serve             → http://localhost:4200"

# Apagar infraestructura local
local-down:
	docker compose -f docker-compose.local.yml down

# Entrar a MySQL local
local-db:
	docker compose -f docker-compose.local.yml exec db mysql -u secretaria -psecret gestion_academica

# Primera configuración del entorno local (solo la primera vez)
local-setup: local-up
	@echo "Esperando a que MySQL esté listo..."
	sleep 12
	cd backend && cp -n .env.example .env || true
	cd backend && php artisan key:generate
	cd backend && php artisan migrate --seed
	@echo ""
	@echo "Listo. Levanta los servidores con:"
	@echo "  cd backend  && php artisan serve"
	@echo "  cd frontend && ng serve"

# ── Primer despliegue completo ────────────────────────────────────────

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
