# PhotoAuthApp

Laravel-приложение для авторизации/галереи с запуском в Docker.

## Установка и первый запуск

- Windows: для установки на другом компьютере достаточно перенести `Install-PhotoAuthApp.cmd` и `Install-PhotoAuthApp.ps1` в одну папку и открыть `.cmd` двойным щелчком. Установщик предложит каталог, скачает полный проект из `fragment2311/PhotoAuthSite` и продолжит настройку. Затем выберите режим и порты. Docker Compose запустит сайт и агент; без Docker установщик создаст сайт и пул IIS на выбранном порту, затем зарегистрирует агент. При отсутствии компонентов установщик спросит разрешение: IIS включит через Windows, PHP/Python/Git установит через winget, Composer получит с официального сайта с проверкой контрольной суммы. PHP FastCGI подключается к сайту автоматически; IIS URL Rewrite установщик предложит получить через winget. Windows запросит стандартное повышение прав.
- Linux: запустите `install-photoauthapp.sh` из каталога проекта. Выберите режим и порт сайта. Docker Compose запустит сайт и агент; без Docker установщик создаст конфигурацию Nginx для обнаруженного PHP-FPM и зарегистрирует агент systemd. Если компоненты отсутствуют, установщик предложит установить их через apt-get на Debian/Ubuntu.
- После установки откройте показанный адрес `/setup/auth`, введите выданный установщиком ключ и заполните мастер в браузере.

Порт сайта выбирается в любом режиме. При запуске без Docker отдельно выбирается локальный порт агента обновлений; агент слушает только `127.0.0.1`. В Docker агент доступен только внутри сети Compose.

Подключаемая база должна уже содержать основные таблицы PhotoAuthApp (`webusers`, `role`, `passage`, `location`). Мастер проверяет доступ к базе до сохранения и не меняет существующие записи. Исходной схемы этих таблиц в репозитории пока нет, поэтому пустая база для этого выпуска не подходит.

Повторный запуск установщика сохраняет `.env`, `storage` и записи базы. Значения уже настроенных секретов в мастере не показываются.

URL по умолчанию при установке через Docker Compose: `http://localhost:27020`

## Автоустановка зависимостей при старте

В контейнере включена автоматическая проверка PHP-зависимостей при старте:

- если отсутствует `vendor/autoload.php` — выполняется `composer install`;
- если `composer.lock` новее, чем `vendor/autoload.php` — выполняется `composer install`.

Это позволяет не ставить зависимости вручную.

Отключение (опционально) через переменную окружения:

```env
AUTO_INSTALL_PHP_DEPS=false
```

## Ручной запуск через Docker Compose

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

