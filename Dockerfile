FROM composer:2 AS composer-builder
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --ignore-platform-reqs --no-scripts

FROM node:22-alpine AS node-builder
WORKDIR /build
COPY package.json ./
RUN npm install
COPY resources ./resources
COPY vite.config.js ./
COPY --from=composer-builder /app/vendor ./vendor
RUN npm run build

FROM dunglas/frankenphp
WORKDIR /app
RUN install-php-extensions \
    pcntl \
    zip \
    pdo_pgsql \
    mbstring \
    opcache \
    exif \
    fileinfo \
    ctype \
    xml \
    tokenizer \
    redis

COPY . .

COPY --from=composer-builder /app/vendor ./vendor
COPY --from=node-builder /build/public/build ./public/build

RUN php artisan package:discover --ansi && \
    php artisan vendor:publish --tag=laravel-assets --ansi --force && \
    php artisan storage:link

ENTRYPOINT ["php", "artisan", "octane:frankenphp"]
