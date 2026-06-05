# syntax=docker/dockerfile:1

# ---- PHP dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --ignore-platform-reqs --no-scripts

# ---- Front-end assets ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources ./resources
# Tailwind v4 imports flux.css and scans vendor blade files (@source), so vendor must be present.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---- Runtime ----
FROM php:8.3-cli-alpine AS runtime
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN apk add --no-cache ca-certificates \
    && install-php-extensions pdo_mysql bcmath gd intl zip opcache pcntl

WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Ensure the framework's writable directories exist, then run as a non-root user.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && addgroup -g 1000 app \
    && adduser -u 1000 -G app -s /bin/sh -D app \
    && chown -R app:app storage bootstrap/cache
USER app

ENV PORT=8080
EXPOSE 8080

# Cache config/routes/views, apply migrations, then serve on the host-provided port.
CMD ["sh", "-c", "php artisan config:cache && php artisan view:cache && php artisan migrate --force --seed && php artisan serve --host=0.0.0.0 --port=${PORT}"]
