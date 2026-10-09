# PhotoAuthApp

Laravel-приложение для авторизации/галереи с запуском в Docker.

URL по умолчанию: `http://localhost:27017`

## Автоустановка зависимостей при старте

В контейнере включена автоматическая проверка PHP-зависимостей при старте:

- если отсутствует `vendor/autoload.php` — выполняется `composer install`;
- если `composer.lock` новее, чем `vendor/autoload.php` — выполняется `composer install`.

Это позволяет не ставить зависимости вручную.

Отключение (опционально) через переменную окружения:

```env
AUTO_INSTALL_PHP_DEPS=false
```

## Быстрый запуск через Docker Compose

1. Скопировать env:

```bash
cp .env.example .env
```

2. Минимально проверить/заполнить в `.env`:

```env
APP_URL=http://localhost:27017
APP_KEY=

DB_CONNECTION=remote_mysql
REMOTE_DB_HOST=127.0.0.1
REMOTE_DB_PORT=3306
REMOTE_DB_DATABASE=laravel
REMOTE_DB_USERNAME=root
REMOTE_DB_PASSWORD=

REDIS_HOST=redis
REDIS_PORT=6379

AUTO_INSTALL_PHP_DEPS=true
MIGRATE_ON_START=false

DH_USER=fragment213
APP_REPO=photoauthapp
APP_TAG=prod
```

3. Сборка и запуск:

```bash
docker compose -f compose.yaml up -d --build
```

4. Проверка:

- открыть `http://localhost:27017/login`;
- проверить статус контейнеров:

```bash
docker compose -f compose.yaml ps
```

`APP_KEY` можно оставить пустым — он будет автоматически сгенерирован при первом старте.

## Оптимизации производительности (включено)

- Кэш и сессии можно перевести на Redis через `.env`:

```env
CACHE_STORE=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

- В `compose.yaml` добавлен сервис Redis для локального/контейнерного запуска.
- Для отчета по проходам добавлено:
  - ограничение периода (по умолчанию до 62 дней),
  - кэш справочников (отделы/должности),
  - кэш расчета дневных часов (используется и в просмотре, и в экспорте).
- Для генерации миниатюр добавлена блокировка через cache lock (защита от stampede при одновременных запросах).

Тюнинг в `config/worktime.php`:

- `report_max_days`
- `lookup_cache_ttl_seconds`
- `report_cache_ttl_seconds`

## Работа с Docker Hub

### Сборка образа

```bash
docker compose -f compose.build.yaml build
```

### Публикация образа

```bash
docker compose -f compose.build.yaml push
```

### Запуск опубликованного образа

```bash
docker compose -f compose.yaml up -d
```

`compose.yaml` использует образ из переменных:

`$DH_USER/$APP_REPO:$APP_TAG`

