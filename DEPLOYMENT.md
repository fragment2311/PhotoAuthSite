# Развёртывание PhotoAuthApp на сервере

## Принятые решения

- Используется существующий `compose.yaml`, без второго production Compose-файла.
- MySQL остаётся внешней и не запускается в Docker Compose.
- Внешний порт приложения — `27020`; внутренний Apache-порт остаётся `27017`.
- Redis, storage и cache остаются в именованных Docker volumes.
- Агент обновления включается отдельным Compose-профилем и не публикует порт наружу.
- Фильтры безопасности production: `APP_DEBUG=false`, зависимости не устанавливаются, ключ не генерируется, миграции не запускаются автоматически.
## Что нужно заранее

- Linux-сервер с Docker Engine и Docker Compose plugin;
- OpenSSL для создания случайного секрета агента;
- внешний MySQL, доступный с сервера Docker;
- открытый входящий TCP-порт `27020` или настроенный reverse proxy;
- исходный код проекта без локального файла `.env`.

MySQL не запускается этим Compose-файлом. В `REMOTE_DB_HOST` и `DB_HOST` укажите IP-адрес или DNS-имя внешнего MySQL. `127.0.0.1` внутри контейнера означает сам контейнер, а не хост-сервер.

## Первый запуск

```bash
cd /opt/photoauthapp
cp .env.example.production .env.production
nano .env.production
```

В `.env.production` обязательно задайте:

- `APP_URL` — внешний адрес приложения;
- `REMOTE_DB_*` и дублирующие `DB_*` — параметры внешней MySQL;
- `APP_KEY` — уникальный ключ Laravel;
- `UPDATE_AGENT_TOKEN` — случайный секрет не короче 32 символов для связи приложения с агентом обновления;
- при необходимости параметры почты, Telegram и фото-хранилища.

Сгенерируйте токен агента и вставьте его в `.env.production`:

```bash
openssl rand -hex 32
```

Сгенерируйте `APP_KEY` на сервере и вставьте результат в `.env.production`:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Проверьте итоговую конфигурацию без запуска контейнеров:

```bash
docker compose --profile updates --env-file .env.production -f compose.yaml config --quiet
```

Соберите и запустите приложение:

```bash
docker compose --profile updates --env-file .env.production -f compose.yaml up -d --build
docker compose --profile updates --env-file .env.production -f compose.yaml ps
docker compose --env-file .env.production -f compose.yaml exec app php artisan migrate --force
```

Приложение будет доступно по адресу `http://SERVER_IP:27020` (или по `APP_URL`). Redis, Laravel storage и cache сохраняются в Docker volumes и переживают пересоздание контейнеров.

## Обновление из админки

После входа под ролью «Администратор» откройте «Настройки». Блок «Обновление PhotoAuthApp» автоматически проверит `origin/main`, покажет текущую и доступную версии, сообщения коммитов и файлы миграций. Для запуска нажмите «Установить обновление»; если есть миграции, появится отдельное предупреждение.

Установка выполняется внутренним Compose-сервисом `updater`; порт сервиса не публикуется. Веб-контейнер не получает Docker socket. При обычном обновлении пересобирается только `app`, именованные тома и `.env.production` не удаляются. Если найдены миграции, агент предварительно сохраняет структуру MySQL без строк и останавливает приложение на время резервной копии и миграции. Полный дамп данных не создаётся. Подтверждённые миграции могут менять записи; схема без строк не позволяет вернуть такие изменения автоматически.

Не удаляйте том `update_agent_data`: в нём хранятся статусы заданий и снимки схемы. Не запускайте `docker compose down -v`, если требуется сохранить постоянные данные. Полные шаги настройки и поведение при сбое описаны в [UPDATE.md](UPDATE.md).

## Проверка и журналы

```bash
docker compose --env-file .env.production -f compose.yaml logs --tail=100 app
docker compose --env-file .env.production -f compose.yaml exec app php artisan about
```

## Важное

- `.env.production` содержит пароли и ключи: он исключён из Git.
- Не включайте `APP_DEBUG=true` на сервере.
- Не включайте `AUTO_INSTALL_PHP_DEPS` и `MIGRATE_ON_START`: зависимости уже собраны в образе, миграции запускаются отдельной подтверждаемой операцией агента.
- Для HTTPS используйте внешний Nginx/Caddy/reverse proxy и направьте его на `127.0.0.1:27020` либо ограничьте доступ портом firewall.
- Бэкапы MySQL выполняются средствами сервера MySQL. Для резервной копии файлов приложения сохраняйте Docker volume `app_storage`.
