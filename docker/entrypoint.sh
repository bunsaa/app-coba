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

echo "==> Ensuring storage directories exist..."
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/framework/cache
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

echo "==> Caching config/routes/views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache || echo "==> view:cache skipped (non-fatal)"

echo "==> Starting Nginx + PHP-FPM via supervisord..."
exec supervisord -c /etc/supervisord.conf
