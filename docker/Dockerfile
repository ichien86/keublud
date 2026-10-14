# Lingkungan ujicoba lokal (bukan image produksi).
FROM php:8.4-cli-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev libpng-dev postgresql-client \
 && docker-php-ext-install pdo_pgsql zip intl gd pcntl \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# File RBA bisa ±25 MB dengan puluhan sheet.
RUN printf 'upload_max_filesize=64M\npost_max_size=64M\nmemory_limit=1024M\nmax_execution_time=300\n' > /usr/local/etc/php/conf.d/blud.ini

WORKDIR /app
