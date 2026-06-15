#!/bin/sh
set -e

echo "==> Clearing bootstrap cache (stale host files)..."
rm -f /var/www/bootstrap/cache/*.php

echo "==> Waiting for DB..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
  sleep 2
done
echo "==> DB ready."

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Caching config/routes/views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Starting PHP-FPM..."
exec php-fpm
