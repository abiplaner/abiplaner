# Abiplaner als Schulserver (Weg C): ein Container, Daten im Volume /data.
#
# Bauen (im Projektordner):
#   docker build -f docker/schule/Dockerfile -t abiplaner .
# Starten: siehe docker/schule/compose.yaml

# --- 1. PHP-Abhängigkeiten -------------------------------------------------
FROM dunglas/frankenphp:1-php8.5-alpine AS php-basis
RUN install-php-extensions gd zip intl exif bcmath pcntl opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-basis AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && sh docker/build-tcpdf-fonts.sh

# --- 2. Oberfläche (Vite/Tailwind) -----------------------------------------
FROM node:22-alpine AS oberflaeche
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
# Tailwind liest auch die Paginierungs-Views aus vendor.
COPY --from=composer /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# --- 3. Laufzeit -----------------------------------------------------------
FROM php-basis AS laufzeit
LABEL org.opencontainers.image.title="Abiplaner" \
      org.opencontainers.image.licenses="AGPL-3.0-or-later"

WORKDIR /app
COPY --from=composer /app /app
COPY --from=oberflaeche /app/public/build /app/public/build
COPY docker/schule/php.ini /usr/local/etc/php/conf.d/abiplaner.ini
COPY docker/schule/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/schule/start.sh /usr/local/bin/abiplaner-start

# Alles Veränderliche liegt in /data (Datenbank, Sicherungen, storage,
# Schlüssel) — ein Update tauscht nur das Image aus.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=database \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    ABIPLANER_DATENBANK=/data/abiplaner.sqlite \
    LARAVEL_STORAGE_PATH=/data/storage \
    XDG_DATA_HOME=/data/caddy \
    XDG_CONFIG_HOME=/data/caddy-config \
    TZ=Europe/Berlin

RUN chmod +x /usr/local/bin/abiplaner-start \
    && rm -rf /app/tests /app/storage/logs/* /app/database/*.sqlite* \
    && adduser -D -u 1000 abiplaner \
    && mkdir -p /data && chown -R abiplaner:abiplaner /data /app/bootstrap/cache

USER abiplaner
VOLUME /data
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1

CMD ["abiplaner-start"]
