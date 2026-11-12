# syntax=docker/dockerfile:1.7
#
# Dockerfile multietapa de Proyecto Rotor (ByteBlade).
# Un solo archivo produce las dos imágenes de la aplicación:
#   --target app  -> Laravel 12 sobre PHP-FPM   (API)
#   --target web  -> Next.js 16 en modo standalone (interfaz)
# compose.yaml las construye con "docker compose up --build".

ARG PHP_VERSION=8.3
ARG NODE_VERSION=22

# ──────────────────────────────────────────────────────────────────────────────
# Etapa 1 · dependencias PHP de producción (sin herramientas de desarrollo)
# ──────────────────────────────────────────────────────────────────────────────
FROM composer:2 AS php-vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
# --ignore-platform-reqs: esta etapa solo descarga paquetes; las extensiones reales
# (bcmath, pdo_pgsql…) se instalan en la imagen final.
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader --ignore-platform-reqs
COPY backend/ ./
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

# ──────────────────────────────────────────────────────────────────────────────
# Etapa 2 · API (PHP-FPM). Imagen final del servicio "app"
# ──────────────────────────────────────────────────────────────────────────────
FROM php:${PHP_VERSION}-fpm-alpine AS app
COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql bcmath opcache redis \
    && rm -f /usr/local/bin/install-php-extensions
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-rotor.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-rotor.conf

WORKDIR /var/www/html
COPY --from=php-vendor /app ./
COPY docker/entrypoint.sh /usr/local/bin/rotor-entrypoint
COPY docker/wait-for-db.php /usr/local/bin/wait-for-db.php
RUN chmod +x /usr/local/bin/rotor-entrypoint \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
                storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 9000
HEALTHCHECK --interval=15s --timeout=5s --start-period=60s --retries=5 \
    CMD php -r '$s=@fsockopen("127.0.0.1",9000,$e,$m,2); exit($s?0:1);'
ENTRYPOINT ["rotor-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]

# ──────────────────────────────────────────────────────────────────────────────
# Etapa 3 · dependencias de Node (capa cacheable mientras no cambie package-lock)
# ──────────────────────────────────────────────────────────────────────────────
FROM node:${NODE_VERSION}-alpine AS web-deps
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund

# ──────────────────────────────────────────────────────────────────────────────
# Etapa 4 · compilación de Next.js
# NEXT_PUBLIC_API_BASE_URL vacío = el navegador llama al mismo origen (/api/...),
# así la misma imagen sirve para cualquier dominio detrás del proxy.
# ──────────────────────────────────────────────────────────────────────────────
FROM web-deps AS web-build
COPY frontend/ ./
ENV NEXT_TELEMETRY_DISABLED=1 \
    NEXT_PUBLIC_API_BASE_URL=""
RUN npm run build

# ──────────────────────────────────────────────────────────────────────────────
# Etapa 5 · interfaz (Next.js standalone). Imagen final del servicio "web"
# ──────────────────────────────────────────────────────────────────────────────
FROM node:${NODE_VERSION}-alpine AS web
WORKDIR /app
ENV NODE_ENV=production NEXT_TELEMETRY_DISABLED=1 PORT=3000 HOSTNAME=0.0.0.0
COPY --from=web-build --chown=node:node /app/public ./public
COPY --from=web-build --chown=node:node /app/.next/standalone ./
COPY --from=web-build --chown=node:node /app/.next/static ./.next/static
USER node
EXPOSE 3000
HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 \
    CMD wget -qO- http://127.0.0.1:3000/ >/dev/null || exit 1
CMD ["node", "server.js"]
