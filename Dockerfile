FROM node:20 AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_sqlite zip gd mbstring bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer install --optimize-autoloader --no-dev --no-interaction --no-scripts

# Storage persisten dikelola lewat Railway Volume (mount path: /data)

CMD sh -c "mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chmod -R 775 storage bootstrap/cache && mkdir -p /data/public /data/database && touch /data/database/database.sqlite && rm -rf storage/app/public && ln -sfn /data/public storage/app/public && php artisan config:clear && php artisan migrate --force && (php artisan db:seed --force || true) && (php artisan storage:link || true) && php artisan serve --host=0.0.0.0 --port=\${PORT:-8080}"
