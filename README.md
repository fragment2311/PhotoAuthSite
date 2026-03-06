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

2. Убедиться, что в `.env`:

```env
APP_URL=http://localhost:27017
DH_USER=fragment213
APP_REPO=photoauthapp
APP_TAG=prod
AUTO_INSTALL_PHP_DEPS=true
```

3. Сборка и запуск:

```bash
docker compose up -d --build
```

4. Проверка:

Открыть `http://localhost:27017/login`

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

`compose.yaml` и `docker-compose.yml` используют образ из переменных:

`$DH_USER/$APP_REPO:$APP_TAG`

