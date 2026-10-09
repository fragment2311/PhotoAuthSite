#!/usr/bin/env sh
set -e

cd /var/www/html

AUTO_INSTALL_PHP_DEPS="${AUTO_INSTALL_PHP_DEPS:-false}"
MIGRATE_ON_START="${MIGRATE_ON_START:-false}"

# Подготовить writable-директории (особенно при volume mount)
mkdir -p \
  storage/app \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache

# Для sqlite гарантируем наличие файла БД
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  SQLITE_DB_PATH="${DB_DATABASE:-database/database.sqlite}"

  case "$SQLITE_DB_PATH" in
    /*) ;;
    *) SQLITE_DB_PATH="/var/www/html/$SQLITE_DB_PATH" ;;
  esac

  mkdir -p "$(dirname "$SQLITE_DB_PATH")"
  touch "$SQLITE_DB_PATH"
  chown www-data:www-data "$SQLITE_DB_PATH" || true
  chmod ug+rw "$SQLITE_DB_PATH" || true
fi

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
if [ ! -e public/storage ]; then
  php artisan storage:link || true
fi

# APP_KEY must be generated before the first production start.
if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is required. Generate it before the first production start." >&2
  exit 1
fi
# Кэши для продакшна
php artisan config:cache
php artisan route:cache || php artisan route:clear
php artisan view:cache

# Миграции по флагу
if [ "$MIGRATE_ON_START" = "true" ]; then
  php artisan migrate --force
fi

exec "$@"
