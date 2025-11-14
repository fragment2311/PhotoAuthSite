# ===== Stage 1: vendor (Composer) =====
FROM composer:2 AS vendor
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-scripts --no-progress --no-interaction --prefer-dist \
    --ignore-platform-req=ext-ftp

COPY . .
RUN composer dump-autoload --optimize

# ===== Stage 2: runtime (PHP 8.2 + Apache, apt + mc) =====
FROM php:8.2-apache-bookworm

# APT и инструменты (включая midnight commander)
RUN apt-get update && apt-get install -y --no-install-recommends \
    mc nano less vim-tiny htop procps lsof iputils-ping net-tools \
    ca-certificates curl unzip tzdata \
    libzip-dev libicu-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && rm -rf /var/lib/apt/lists/*

# PHP-расширения
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) pdo_mysql intl gd bcmath opcache ftp

# Apache
RUN a2enmod rewrite headers expires \
 && echo 'Listen 8082' >> /etc/apache2/ports.conf

# VHost
COPY deploy/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN a2ensite 000-default && apache2ctl -t

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html

# >>> гарантируем наличие нужных директорий перед правами
RUN set -eux; \
    mkdir -p \
      storage/app \
      storage/framework/cache \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

ENV APACHE_RUN_USER=www-data APACHE_RUN_GROUP=www-data
EXPOSE 8082
CMD ["apache2-foreground"]
