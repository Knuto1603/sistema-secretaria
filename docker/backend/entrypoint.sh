#!/bin/sh
# Copia plantillas desde la imagen al volumen (si no existen ya)
if [ -d /var/www/html/storage-image/plantillas ]; then
  mkdir -p /var/www/html/storage/app/plantillas
  for f in /var/www/html/storage-image/plantillas/*; do
    dest="/var/www/html/storage/app/plantillas/$(basename "$f")"
    if [ ! -f "$dest" ]; then
      cp "$f" "$dest"
    fi
  done
  chown -R www-data:www-data /var/www/html/storage/app/plantillas
fi

exec "$@"
