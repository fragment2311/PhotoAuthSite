#!/usr/bin/env sh
set -e

cd /var/www/html

# Установить зависимости, если vendor отсутствует
if [ ! -d vendor ]; then
  composer install --no-dev --prefer-dist --no-progress --no-interaction
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
