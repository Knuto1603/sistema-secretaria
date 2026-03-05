# ============================================================
# Gateway – Angular SPA + proxy a la API (un solo servicio)
# Etapa 1: Compilar Angular con Node
# Etapa 2: Nginx sirve el SPA y proxea /api al backend
# ============================================================

# --- Etapa 1: Build Angular ---
FROM node:20-alpine AS builder

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .

# API_URL como ruta relativa: el gateway proxea /api → nginx-api
ARG API_URL=/api
RUN sed -i "s|apiUrl:.*|apiUrl: '${API_URL}'|g" src/environments/environment.ts

RUN npm run build

# --- Etapa 2: Gateway Nginx ---
FROM nginx:1.27-alpine

COPY --from=builder /app/dist/frontend/browser /usr/share/nginx/html
COPY docker/gateway/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
