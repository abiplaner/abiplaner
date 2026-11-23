FROM php:8.5-fpm-alpine

# System-Abhängigkeiten installieren
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    oniguruma-dev \
    sqlite \
    bash \
    nodejs \
    npm \
    tzdata

# PHP Extensions installieren
# Hinweis: pdo und opcache sind ab den 8.5-Images bereits im Core enthalten und
# dürfen nicht erneut via docker-php-ext-install gebaut werden (sonst Fehler
# "install-modules: can't stat modules/*"). pdo_mysql zieht pdo als Abhängigkeit.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

# PCOV für Code-Coverage. pcov.enabled=1 als Default, damit der Treiber beim
# Modul-Load verfügbar ist (PHPUnit 13 / artisan test verlangt einen aktiven
# Line-Coverage-Treiber, sobald ein <coverage>-Block in phpunit.xml existiert).
# PCOV instrumentiert nur, wenn Coverage tatsächlich angefordert wird -> im
# Normallauf praktisch kein Overhead.
RUN apk add --no-cache --virtual .pcov-build-deps $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .pcov-build-deps \
    && { \
        echo "pcov.enabled=1"; \
        echo "pcov.directory=/var/www/html/app"; \
    } > /usr/local/etc/php/conf.d/pcov.ini

# Composer installieren
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# User für Laravel erstellen
RUN addgroup -g 1000 www && \
    adduser -D -u 1000 -G www www

# Arbeitsverzeichnis setzen
WORKDIR /var/www

# Berechtigungen setzen
RUN chown -R www:www /var/www

# Wechsel zum www User
USER www

# Container starten
CMD ["php-fpm"]
