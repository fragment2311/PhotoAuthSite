# ===== Stage 1: vendor (Composer on PHP 8.4) =====
FROM php:8.4-cli-bookworm AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip \
    libzip-dev libicu-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) zip intl gd \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-scripts --no-progress --no-interaction --prefer-dist \
    --ignore-platform-req=ext-ftp

COPY . .
RUN composer dump-autoload --optimize

# ===== Stage 2: runtime (PHP 8.4 + Apache, apt + mc) =====
FROM php:8.4-apache-bookworm

# APT и инструменты (включая midnight commander)
RUN apt-get update && apt-get install -y --no-install-recommends \
    mc nano less vim-tiny htop procps lsof iputils-ping net-tools \
    ca-certificates curl unzip tzdata \
    libzip-dev libicu-dev libsqlite3-dev default-mysql-client \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && rm -rf /var/lib/apt/lists/*

# PHP-расширения
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) pdo_mysql pdo_sqlite zip intl gd bcmath opcache ftp

# Redis extension (phpredis)
RUN pecl install redis \
 && docker-php-ext-enable redis

# Apache
RUN a2enmod rewrite headers expires \
 && echo 'Listen 27017' >> /etc/apache2/ports.conf

# VHost
COPY deploy/000-default.conf /etc/apache2/sites-available/000-default.conf
RUN a2ensite 000-default && apache2ctl -t

# PHP runtime settings
COPY docker/php/conf.d/ /usr/local/etc/php/conf.d/

# Composer в runtime (для автоустановки зависимостей при старте)
COPY --from=vendor /usr/local/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint

# >>> гарантируем наличие нужных директорий перед правами
RUN set -eux; \
    mkdir -p \
      storage/app \
      storage/framework/cache \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      bootstrap/cache; \
    chmod +x /usr/local/bin/app-entrypoint; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rwX storage bootstrap/cache

ENV APACHE_RUN_USER=www-data APACHE_RUN_GROUP=www-data
EXPOSE 27017
ENTRYPOINT ["app-entrypoint"]
CMD ["apache2-foreground"]
