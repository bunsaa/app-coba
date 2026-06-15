# ============================================================
# Stage 1 — Build JS assets (Node 20)
# ============================================================
FROM node:20-alpine AS node-builder

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
ENV SKIP_WAYFINDER=true
RUN npm run build

# ============================================================
# Stage 2 — PHP 8.3 FPM (production)
# ============================================================
FROM php:8.3-fpm-alpine

# 1. Install system libraries (dipisah dari php ext install agar cache lebih efisien)
RUN apk add --no-cache \
    git curl bash zip unzip \
    libzip-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libxml2-dev oniguruma-dev icu-dev

# 2. Install PHP extensions
#    JANGAN tambah: xml, fileinfo, tokenizer, ctype — sudah built-in di PHP 8.3
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring exif pcntl bcmath gd intl opcache zip

# 3. Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 4. Copy source & built assets
COPY . .
COPY --from=node-builder /app/public/build ./public/build

# 5. Install PHP dependencies
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# 6. Set permissions — hanya folder yang perlu ditulis Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# 7. Entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
