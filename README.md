# Sistema de Secretaría Académica — FII UNP

Sistema web para la gestión de trámites académicos de la Facultad de Ingeniería Industrial de la Universidad Nacional de Piura.

## Tecnologías

| Capa | Tecnología |
|------|-----------|
| Frontend | Angular 19 + Tailwind CSS |
| Backend | Laravel 12 + PHP 8.2 |
| Base de datos | MySQL 8.0 |
| Cache / Colas | Redis 7 |
| Servidor web | Nginx |
| Contenedores | Docker + Docker Compose |

## Estructura del repositorio

```
sistema-secretaria/
├── backend/          # API REST (Laravel)
├── frontend/         # SPA (Angular)
├── docker/           # Dockerfiles y configuraciones de Nginx
├── docker-compose.yml
├── Makefile          # Comandos rápidos
└── .env.docker.example
```

## Requisitos

- Docker >= 24
- Docker Compose >= 2

## Inicio rápido

```bash
# 1. Copiar y configurar variables de entorno
cp .env.docker.example .env.docker
# Editar .env.docker: APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, GROQ_API_KEY

# 2. Levantar todos los servicios
docker compose up -d --build

# 3. Generar clave de aplicación y ejecutar migraciones
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --seed
```

El sistema estará disponible en:
- Frontend: http://localhost:80
- Backend API: http://localhost:8000/api

## Comandos útiles

```bash
make up              # Levantar servicios
make down            # Apagar servicios
make migrate         # Ejecutar migraciones
make seed            # Ejecutar seeders
make fresh           # Reset completo (borra datos)
make logs s=backend  # Ver logs de un servicio
make shell-backend   # Entrar al contenedor backend
```

## Módulos

- **Autenticación** — Login por roles: developer, administrativo, estudiante
- **Periodos académicos** — CRUD con activación exclusiva
- **Programación académica** — Carga de horarios vía Excel
- **Solicitudes de cupo extra** — Flujo completo de solicitud y aprobación
- **Gestión de usuarios** — Administrativos y estudiantes con activación OTP
- **Configuración** — Tipos de solicitud, periodos, roles

## Variables de entorno

Copiar `.env.docker.example` como `.env.docker` y completar los valores marcados como `OBLIGATORIO` antes del primer despliegue.
