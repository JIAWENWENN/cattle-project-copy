#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY is required. Run: php artisan key:generate --show"
  exit 1
fi

php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan storage:link --force 2>/dev/null || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
  php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ -n "${PORT:-}" ]; then
  sed -i "s/listen 8080;/listen ${PORT};/" /etc/nginx/http.d/default.conf
fi

exec "$@"
