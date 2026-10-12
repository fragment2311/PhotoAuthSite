# Полная инструкция для клиента: установка, запуск, настройка и эксплуатация PhotoAuthApp

Документ покрывает запуск «с нуля» и типовые вопросы эксплуатации.

Поддерживаемый сценарий:
- база ещё не настроена;
- мастер-пароль ещё не создан;
- параметры приложения ещё не заполнены.

---

## 0) Быстрые ответы на частые вопросы

### Можно ли изменить IP и порт без изменения PHP-кода?
Да. В режиме с исходниками меняется `ports` в `compose.yaml` и `APP_URL` в `.env`.
В режиме «только image» — меняется параметр `-p` и `-e APP_URL=...` в `docker run`.

### Как зайти в настройку, если ничего ещё не настроено?
Для новой установки откройте `/setup/auth` и введите ключ, который показал единый установщик. Старый способ через URL с `setup_key` оставлен для совместимости:

```text
http://<IP_ИЛИ_ДОМЕН>:<ПОРТ>/setup/settings?setup_key=<ВАШ_SETUP_ACCESS_KEY>
```

### Что обязательно задать до первого запуска?
1) `SETUP_ACCESS_KEY`.
2) `APP_URL`.
3) `APP_KEY` (сгенерировать через `php artisan key:generate --show` внутри временного контейнера).
4) Параметры MySQL: `REMOTE_DB_HOST`, `REMOTE_DB_PORT`, `REMOTE_DB_DATABASE`, `REMOTE_DB_USERNAME`, `REMOTE_DB_PASSWORD`.
5) Для image-only первого старта: `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`.
6) При необходимости — внешний IP/порт (`ports` в compose или `-p` в docker run).

---

## 1) Требования к окружению

1. Docker Desktop (Windows/macOS) **или** Docker Engine + Docker Compose plugin (Linux).
2. Доступ к интернету для загрузки базовых образов (при первой сборке).
3. Свободный внешний порт (по умолчанию `27017`).
4. Доступ к целевой БД (если используете внешнюю MySQL/MariaDB).

Проверка:

```bash
docker --version
docker compose version
```

---

## 2) Запуск в двух режимах

Ниже два корректных сценария. Для клиентов при продаже обычно используется **режим A.

### 2.A) Режим A (рекомендуется для клиента): только Docker image, без исходников


1. Скачать образ:

```bash
docker pull fragment213/photoauthapp:prod
```

2. Сгенерировать `APP_KEY`:

```bash
docker run --rm fragment213/photoauthapp:prod php artisan key:generate --show
```

3. Запустить контейнер с параметрами через `-e`.

Пример (Windows PowerShell):

```powershell
$APP_URL = "http://localhost:27017"
$APP_KEY = "base64:Сгенерированный ключ"
$SETUP_ACCESS_KEY = "Случайные символы"
$API_TOKEN = "Случайные символы"
$REMOTE_DB_HOST = "192.168.9.10" 
$REMOTE_DB_PORT = "5432" 
$REMOTE_DB_DATABASE = "skud"
$REMOTE_DB_USERNAME = "ctrluser" 
$REMOTE_DB_PASSWORD = "strong_password_here"
$SESSION_DRIVER = "file"
$CACHE_STORE = "file"
$QUEUE_CONNECTION = "sync"

docker rm -f photoauthapp 2>$null

docker run -d --name photoauthapp `
  -p 27017:27017 `
  -e APP_ENV=production `
  -e APP_DEBUG=false `
  -e APP_URL=$APP_URL `
  -e APP_KEY=$APP_KEY `
  -e SETUP_ACCESS_KEY=$SETUP_ACCESS_KEY `
  -e API_TOKEN=$API_TOKEN `
  -e DB_CONNECTION=remote_mysql `
  -e REMOTE_DB_HOST=$REMOTE_DB_HOST `
  -e REMOTE_DB_PORT=$REMOTE_DB_PORT `
  -e REMOTE_DB_DATABASE=$REMOTE_DB_DATABASE `
  -e REMOTE_DB_USERNAME=$REMOTE_DB_USERNAME `
  -e REMOTE_DB_PASSWORD=$REMOTE_DB_PASSWORD `
  -e SESSION_DRIVER=$SESSION_DRIVER `
  -e CACHE_STORE=$CACHE_STORE `
  -e QUEUE_CONNECTION=$QUEUE_CONNECTION `
  fragment213/photoauthapp:prod
```

Проверка, что ключ реально применился:

```bash
docker exec photoauthapp printenv SETUP_ACCESS_KEY
```


Важно:
- `SETUP_ACCESS_KEY` нужен, когда БД/мастер-пароля ещё нет.
- После изменения `.env` перезапускайте контейнер.

---

## 3) Изменение IP и порта без правки кода

Меняется публикация порта контейнера в `compose.yaml`.

Текущий формат:

```yaml
ports:
  - "27017:27017"
```

Где:
- слева — внешний порт хоста;
- справа — внутренний порт приложения в контейнере (сейчас `27017`).

Примеры:

### Только сменить внешний порт

```yaml
ports:
  - "8080:27017"
```

Доступ: `http://localhost:8080`

### Привязать к конкретному IP интерфейса

```yaml
ports:
  - "192.168.1.50:8080:27017"
```

### Открыть на всех интерфейсах

```yaml
ports:
  - "0.0.0.0:8080:27017"
```

После смены порта/IP обязательно обновите `APP_URL` в `.env`, например:

```env
APP_URL=http://192.168.1.50:8080
```

Применение изменений:

```bash
docker compose down
docker compose up -d --build
```

Примечание: без правки инфраструктурных файлов можно менять внешний IP/порт. Внутренний порт процесса внутри контейнера сейчас фиксирован.

---

## 3.1) Запуск без редактирования файлов (image-only)

Если исходников нет или нельзя редактировать [`.env`](.env)/[`compose.yaml`](compose.yaml),
запускайте контейнер через параметры `docker run -e ...`.

### Шаг 1. Получить образ

```bash
docker pull fragment213/photoauthapp:prod
```

### Шаг 2. Сгенерировать APP_KEY (один раз)

```bash
docker run --rm fragment213/photoauthapp:prod php artisan key:generate --show
```

Скопируйте строку вида `base64:...`.

### Шаг 3. Запустить контейнер с нужным IP/портом и env

Пример (Linux/macOS):

```bash
docker run -d --name photoauthapp \
  -p 192.168.1.50:8080:27017 \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e APP_URL=http://192.168.1.50:8080 \
  -e APP_KEY='base64:ВСТАВЬТЕ_КЛЮЧ' \
  -e SETUP_ACCESS_KEY='СЛОЖНЫЙ_SETUP_КЛЮЧ' \
  -e API_TOKEN='ДЛИННЫЙ_СЛУЧАЙНЫЙ_ТОКЕН' \
  -e SESSION_DRIVER=file \
  -e CACHE_STORE=file \
  -e QUEUE_CONNECTION=sync \
  fragment213/photoauthapp:prod
```

Пример (Windows PowerShell):

```powershell
docker run -d --name photoauthapp `
  -p 192.168.1.50:8080:27017 `
  -e APP_ENV=production `
  -e APP_DEBUG=false `
  -e APP_URL=http://192.168.1.50:8080 `
  -e APP_KEY="base64:ВСТАВЬТЕ_КЛЮЧ" `
  -e SETUP_ACCESS_KEY="СЛОЖНЫЙ_SETUP_КЛЮЧ" `
  -e API_TOKEN="ДЛИННЫЙ_СЛУЧАЙНЫЙ_ТОКЕН" `
  -e SESSION_DRIVER=file `
  -e CACHE_STORE=file `
  -e QUEUE_CONNECTION=sync `
  fragment213/photoauthapp:prod
```

После запуска открывайте:

```text
http://localhost:27017/setup/settings?setup_key=fakmflakjnfoiudwhd29
```

Это полностью обходится без ручного редактирования [`.env`](.env) и [`compose.yaml`](compose.yaml).

### Управление контейнером в этом режиме

```bash
docker logs -f photoauthapp
docker restart photoauthapp
docker stop photoauthapp
docker rm -f photoauthapp
```

### Настройка переменных контейнера отдельными командами

Ниже вариант, когда вы сначала задаёте значения по одной команде, а потом запускаете контейнер.

#### Windows PowerShell

```powershell
$APP_ENV = "production"
$APP_DEBUG = "false"
$APP_URL = "http://192.168.1.50:8080" - пример!!!
$APP_KEY = "base64:ВСТАВЬТЕ_КЛЮЧ"
$SETUP_ACCESS_KEY = "СЛОЖНЫЙ_SETUP_КЛЮЧ"
$API_TOKEN = "ДЛИННЫЙ_СЛУЧАЙНЫЙ_ТОКЕН"
$SESSION_DRIVER = "file"
$CACHE_STORE = "file"
$QUEUE_CONNECTION = "sync"

docker rm -f photoauthapp 2>$null

docker run -d --name photoauthapp `
  -p 192.168.1.50:8080:27017 `
  -e APP_ENV=$APP_ENV `
  -e APP_DEBUG=$APP_DEBUG `
  -e APP_URL=$APP_URL `
  -e APP_KEY=$APP_KEY `
  -e SETUP_ACCESS_KEY=$SETUP_ACCESS_KEY `
  -e API_TOKEN=$API_TOKEN `
  -e SESSION_DRIVER=$SESSION_DRIVER `
  -e CACHE_STORE=$CACHE_STORE `
  -e QUEUE_CONNECTION=$QUEUE_CONNECTION `
  fragment213/photoauthapp:prod
```

#### Windows cmd

```cmd
set APP_ENV=production
set APP_DEBUG=false
set APP_URL=http://192.168.1.50:8080
set APP_KEY=base64:ВСТАВЬТЕ_КЛЮЧ
set SETUP_ACCESS_KEY=СЛОЖНЫЙ_SETUP_КЛЮЧ
set API_TOKEN=ДЛИННЫЙ_СЛУЧАЙНЫЙ_ТОКЕН
set SESSION_DRIVER=file
set CACHE_STORE=file
set QUEUE_CONNECTION=sync

docker rm -f photoauthapp

docker run -d --name photoauthapp -p 192.168.1.50:8080:27017 -e APP_ENV=%APP_ENV% -e APP_DEBUG=%APP_DEBUG% -e APP_URL=%APP_URL% -e APP_KEY=%APP_KEY% -e SETUP_ACCESS_KEY=%SETUP_ACCESS_KEY% -e API_TOKEN=%API_TOKEN% -e SESSION_DRIVER=%SESSION_DRIVER% -e CACHE_STORE=%CACHE_STORE% -e QUEUE_CONNECTION=%QUEUE_CONNECTION% fragment213/photoauthapp:prod
```

Важно:
- эти переменные задаются в вашей текущей shell-сессии, а затем передаются в контейнер;
- при удалении и новом создании контейнера их нужно передать снова.
- если не передать `SESSION_DRIVER=file` в image-only режиме, возможен `500` с ошибкой `no such table: sessions`.

---

## 4) Сборка и запуск (только если есть исходники)

### Вариант A (рекомендуется): локальная сборка

```bash
docker compose up -d --build
```

### Вариант B: запуск готового образа (registry)

```bash
docker compose -f compose.yaml -f compose.tag.yml up -d
```

Проверка статуса:

```bash
docker compose ps
```

Проверка логов приложения:

```bash
docker compose logs -f app
```

---

## 5) Первый вход и первичная настройка (сценарий «всё пусто»)

Если БД не настроена и мастер-пароля нет:

1. Откройте:

```text
http://localhost:27017/setup/settings?setup_key=<ВАШ_SETUП_ACCESS_KEY>
```

Подставьте ваш `SETUP_ACCESS_KEY`:
- из `.env` (режим с исходниками),
- или переданный через `-e SETUP_ACCESS_KEY=...` (image-only режим).

2. Заполните ключевые параметры в UI:
- параметры БД: `db_host`, `db_port`, `db_name`, `db_user`, `db_pass`;
- пути к галерее и фото;
- настройки FTP/FTPS (если используете);
- создайте мастер-пароль (рекомендуется обязательно).

3. Если токен пар устройств пустой — сгенерируйте в настройках.

4. Сохраните настройки.

Ожидаемый результат:
- приложение перестаёт возвращать на первичную авторизацию;
- вход в setup далее возможен по мастер-паролю.

---

## 6) Эксплуатация: базовые команды

Запуск:

```bash
docker compose up -d
```

Остановка:

```bash
docker compose down
```

Перезапуск:

```bash
docker compose restart
```

Пересборка после обновления:

```bash
docker compose up -d --build
```

Логи приложения:

```bash
docker compose logs -f app
```

Вход в контейнер:

```bash
docker compose exec app sh
```

---

## 7) Обновление версии приложения

Типовой безопасный порядок:

1. Сделать резервную копию `.env` и важных данных (см. раздел 8).
2. Обновить исходники/образ.
3. Пересобрать и перезапустить:

```bash
docker compose down
docker compose up -d --build
```

4. Проверить логи и доступность URL.

---

## 8) Резервное копирование (рекомендуется обязательно)

Минимум, что нужно бэкапить:
1. `.env`.
2. Данные из `storage` (включая настройки/лицензию/логи).
3. Папку пользовательских загрузок `public/uploads` (если используются).

Рекомендация для постоянства данных между пересозданиями контейнера:
- использовать volume/bind-mount для `storage` и `public/uploads`.

---

## 9) Сетевая доступность извне

Если сервис не открывается с других машин:
1. Проверьте `ports` в `compose.yaml` (IP/порт привязки).
2. Проверьте firewall ОС (разрешить входящий TCP на нужный порт).
3. Для облака — проверьте Security Group/ACL.
4. Проверьте, что обращаетесь к корректному `APP_URL`.

---

## 10) Безопасность после первого запуска

1. Смените `SETUP_ACCESS_KEY` на сложный и храните в защищённом месте.
2. Убедитесь, что мастер-пароль задан и известен только администратору.
3. Не публикуйте `.env` в git/архивах/чатах.
4. Ограничьте сетевой доступ (VPN, allowlist IP, reverse proxy).

---

## 11) FAQ / Устранение проблем

### Ошибка «порт занят» при запуске
Если запуск через compose — измените внешний порт в `compose.yaml`, например `8080:27017`, затем перезапустите.
Если image-only запуск через `docker run` — измените параметр `-p`, например `-p 8080:27017`, и пересоздайте контейнер.

### Изменили `.env`, но ничего не поменялось
Это относится к режиму с исходниками/compose. Перезапустите контейнер:

```bash
docker compose down
docker compose up -d --build
```

### Не пускает на `/setup/settings`
Проверьте:
1) есть `setup_key` в URL;
2) он полностью совпадает с `SETUP_ACCESS_KEY`:
   - из `.env` (режим с исходниками),
   - или переданным через `-e SETUP_ACCESS_KEY=...` (image-only);
3) контейнер перезапущен после изменения `.env`.

### Постоянно редиректит на setup/auth
Значит не заполнены критичные поля первичной настройки (обычно параметры БД и API-токен).

### 403 при setup-эндпоинтах
Неверный/отсутствующий `setup_key` или нет подтверждённой setup-сессии.

### Страница недоступна по IP из локальной сети
Проверьте:
- привязку `ports` к нужному IP/`0.0.0.0`;
- firewall Windows/Linux;
- роутер/маршрутизацию между подсетями.

### Нужно быстро проверить, что контейнер жив

```bash
docker compose ps
docker compose logs --tail=100 app
```

---

## 12) Чек-лист запуска

- [ ] Установлен Docker и работает `docker compose`.
- [ ] Выбран режим запуска:
  - image-only (`docker run -e ...`) **или**
  - с исходниками (`.env` + `docker compose`).
- [ ] Если режим с исходниками: создан `.env` из `.env.example`.
- [ ] Если image-only: переданы `APP_KEY`, `SETUP_ACCESS_KEY`, `APP_URL` через `-e`.
- [ ] Выполнен запуск (через `docker run` или `docker compose up -d --build`).
- [ ] Открыт URL setup с `setup_key`.
- [ ] Заполнены и сохранены настройки в UI.
- [ ] Задан мастер-пароль.
- [ ] Проверен обычный вход в приложение.

---

## 13) API для внешней авторизации приложений

Все API-методы ниже защищены заголовком `X-Api-Token`.

Требования:
- задать `API_TOKEN` в окружении контейнера;
- передавать его в каждом запросе как `X-Api-Token: <ВАШ_API_TOKEN>`.

Базовый URL:

```text
http://<IP_ИЛИ_ДОМЕН>:<ПОРТ>/api
```

### 13.1) Получить список пользователей

`GET /api/users`

Параметры:
- `only_active=1|0` (по умолчанию `1`)

Пример:

```bash
curl -H "X-Api-Token: <ВАШ_API_TOKEN>" "http://localhost:27017/api/users?only_active=1"
```

Успешный ответ (`200`):

```json
{
  "count": 1,
  "users": [
    {
      "id": 1,
      "login": "admin",
      "password": "$2y$10$...",
      "password_hash": "$2y$10$...",
      "is_active": true,
      "role": {
        "id": 1,
        "name": "Администратор"
      },
      "last_update": "2026-03-12 16:20:00"
    }
  ]
}
```

> Поля `password` и `password_hash` содержат один и тот же bcrypt-хеш.

### 13.2) Проверить логин/пароль (авторизация)

`POST /api/users/auth`

Тело запроса (`application/json`):

```json
{
  "login": "admin",
  "password": "secret123"
}
```

Пример:

```bash
curl -X POST "http://localhost:27017/api/users/auth" \
  -H "X-Api-Token: <ВАШ_API_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"login":"admin","password":"secret123"}'
```

Успешный ответ (`200`):

```json
{
  "authorized": true,
  "user": {
    "id": 1,
    "login": "admin",
    "password": "$2y$10$...",
    "password_hash": "$2y$10$...",
    "is_active": true,
    "role": {
      "id": 1,
      "name": "Администратор"
    },
    "last_update": "2026-03-12 16:20:00"
  }
}
```

Ошибки:
- `401` — неверный логин/пароль или невалидный `X-Api-Token`;
- `403` — пользователь неактивен или запрос с IP вне allowlist (если настроен);
- `422` — не переданы обязательные поля `login`/`password`;
- `500` — не задан `API_TOKEN` на сервере.

### 13.3) API таблицы `settings` (чтение/запись + аудит)

Новые методы работают напрямую с таблицей `settings` (как на вашей схеме БД).

Поддерживаются поля аудита:
- `idwebusers` (id пользователя, который менял значение; аналог `useredit`),
- `changed_at` (дата/время изменения),
- `useredit` (синоним `idwebusers` в ответе для совместимости).

Рабочие поля таблицы, которые читает/пишет API:
- `ServerIp`
- `DbUser`
- `DbPassword`
- `TerminalIp`
- `TerminalUser`
- `TerminalPassword`
- `IPCam`
- `LoginCam`
- `PassCam`

#### Получить записи таблицы `settings`

`GET /api/settings/table`

Пример:

```bash
curl -H "X-Api-Token: <ВАШ_API_TOKEN>" "http://localhost:27017/api/settings/table"
```

Ответ (`200`):

```json
{
  "count": 1,
  "settings": [
    {
      "id": 1,
      "ServerIp": "192.168.9.10",
      "DbUser": "ctrluser",
      "DbPassword": "secret",
      "TerminalIp": "192.168.9.20",
      "TerminalUser": "terminal",
      "TerminalPassword": "secret",
      "IPCam": "192.168.9.30",
      "LoginCam": "admin",
      "PassCam": "secret",
      "idwebusers": 2,
      "useredit": 2,
      "changed_at": "2026-03-13 09:40:00"
    }
  ]
}
```

#### Записать значение в таблицу `settings`

`PUT /api/settings/table`

Тело (`application/json`):

```json
{
  "id": 1,
  "ServerIp": "192.168.9.10",
  "DbUser": "ctrluser",
  "DbPassword": "strong_password_here",
  "TerminalIp": "192.168.9.20",
  "TerminalUser": "terminal",
  "TerminalPassword": "terminal_password",
  "IPCam": "192.168.9.30",
  "LoginCam": "admin",
  "PassCam": "cam_password",
  "idwebusers": 2
}
```

Пример:

```bash
curl -X PUT "http://localhost:27017/api/settings/table" \
  -H "X-Api-Token: <ВАШ_API_TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"id":1,"ServerIp":"192.168.9.10","DbUser":"ctrluser","DbPassword":"strong_password_here","TerminalIp":"192.168.9.20","TerminalUser":"terminal","TerminalPassword":"terminal_password","IPCam":"192.168.9.30","LoginCam":"admin","PassCam":"cam_password","idwebusers":2}'
```

Ответ (`200`):

```json
{
  "saved": true,
  "setting": {
    "id": 1,
    "ServerIp": "192.168.9.10",
    "DbUser": "ctrluser",
    "DbPassword": "strong_password_here",
    "TerminalIp": "192.168.9.20",
    "TerminalUser": "terminal",
    "TerminalPassword": "terminal_password",
    "IPCam": "192.168.9.30",
    "LoginCam": "admin",
    "PassCam": "cam_password",
    "idwebusers": 2,
    "useredit": 2,
    "changed_at": "2026-03-13 09:45:00"
  }
}
```

Ошибки:
- `401` — невалидный/отсутствующий `X-Api-Token`;
- `422` — некорректный payload или `idwebusers` не найден в таблице `webusers`;
- `500` — `API_TOKEN` не задан на сервере.
