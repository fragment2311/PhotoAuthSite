#!/usr/bin/env sh
set -e

cd /var/www/html

AUTO_INSTALL_PHP_DEPS="${AUTO_INSTALL_PHP_DEPS:-true}"

# Установить PHP-зависимости автоматически, если их нет
# или если composer.lock новее, чем vendor/autoload.php
if [ "$AUTO_INSTALL_PHP_DEPS" = "true" ]; then
  NEED_COMPOSER_INSTALL="false"

  if [ ! -f vendor/autoload.php ]; then
    NEED_COMPOSER_INSTALL="true"
  elif [ -f composer.lock ] && [ composer.lock -nt vendor/autoload.php ]; then
    NEED_COMPOSER_INSTALL="true"
  fi

  if [ "$NEED_COMPOSER_INSTALL" = "true" ]; then
    composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader
  fi
fi

# Создать storage-link при необходимости
if [ ! -L public/storage ]; then
  php artisan storage:link || true
fi

# Сгенерировать ключ, если пустой
if [ -f .env ] && [ -z "$(grep -E '^APP_KEY=' .env | cut -d= -f2-)" ]; then
  php artisan key:generate --force
fi

# Кэши для продакшна
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Миграции по флагу
if [ "$MIGRATE_ON_START" = "true" ]; then
  php artisan migrate --force
fi

exec "$@"
