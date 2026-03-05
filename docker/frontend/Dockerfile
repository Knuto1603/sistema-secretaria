# ============================================================
# Frontend – Angular 19 (Build multi-etapa)
# Etapa 1: Compilar con Node
# Etapa 2: Servir archivos estáticos con Nginx
# ============================================================

# --- Etapa 1: Build ---
FROM node:20-alpine AS builder

WORKDIR /app

# Copiar manifiestos de dependencias primero (optimiza cache)
COPY frontend/package.json frontend/package-lock.json ./

# Instalar dependencias exactas
RUN npm ci

# Copiar el resto del código fuente
COPY frontend/ .

# Compilar para producción
# El output quedará en dist/frontend/browser/
RUN npm run build

# --- Etapa 2: Servir ---
FROM nginx:1.27-alpine

# Copiar build compilado al directorio de Nginx
COPY --from=builder /app/dist/frontend/browser /usr/share/nginx/html

# Copiar configuración personalizada de Nginx (SPA routing)
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
