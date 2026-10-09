# Инструкция по интеграции с API и системой логирования PhotoAuthApp

Документ для внешних клиентских приложений (точки прохода, сервер-регистратор, служебные интеграции).

---

## 1. Что нужно подготовить заранее

### 1.1 Данные подключения

Нужны:
- **Base URL** сервера, например: `https://example.com`
- **API токен** (заголовок `X-Api-Token`)
- (опционально, но рекомендуется) **идентификатор клиента** в заголовке `X-Client-Id`

### 1.2 Сетевые требования

- Доступ к серверу приложения по HTTP
- Таймауты клиента:
  - connect timeout: 3-5 сек
  - request timeout: 10-20 сек
- Ретраи только для сетевых сбоев и 5xx

### 1.3 Что сервер логирует автоматически

Сервер автоматически пишет в таблицу `Logs`:
- ошибки 4xx/5xx
- неуспешные авторизации API
- аудит API-запросов (включая `request_id`, endpoint, status, latency)

---

## 2. Общие правила вызова API

### 2.1 Базовый заголовок авторизации

Каждый запрос должен содержать:

```http
X-Api-Token: <ВАШ_API_TOKEN>
```

Рекомендуется также передавать:

```http
X-Client-Id: terminal-01
```

### 2.2 Корреляция запросов

Сервер поддерживает `X-Request-Id`:
- если вы передали свой `X-Request-Id` — сервер использует его
- если не передали — сервер сгенерирует сам
- в ответе всегда вернёт `X-Request-Id`

Практика для внешних приложений:
- генерировать уникальный `request_id` на каждый запрос
- хранить его в локальном логе клиента
- при обращении в поддержку передавать `request_id`

---

## 3. Контракт успешных и ошибочных ответов

### 3.1 Успешные ответы

У разных endpoint может быть разный формат успешного payload:
- часть endpoint возвращает массив
- часть endpoint возвращает объект вида `success + data`

Ориентируйтесь на конкретный endpoint ниже.

### 3.2 Единый контракт ошибок

Для ошибок используется унифицированный формат:

```json
{
  "success": false,
  "error": {
    "code": "internal_server_error",
    "message": "Внутренняя ошибка сервера",
    "request_id": "d4ac9a17-8ff4-4ebf-8b0f-1bc99b8b2a1f",
    "timestamp": "2026-03-27T09:00:00+00:00"
  }
}
```

Для валидации (422) дополнительно приходит `errors`:

```json
{
  "success": false,
  "error": {
    "code": "validation_failed",
    "message": "Ошибка валидации запроса",
    "request_id": "...",
    "timestamp": "...",
    "errors": {
      "login": ["The login field is required."]
    }
  }
}
```

---

## 4. Коды статусов и как реагировать клиенту

- **200/201** — успех
- **401** — неверный/отсутствующий `X-Api-Token`
- **403** — запрещено (например, IP не в allowlist)
- **422** — ошибка валидации параметров
- **429** — лимит запросов (если включено ограничение)
- **5xx** — внутренняя ошибка сервера

Рекомендуемая стратегия:
- 4xx: не ретраить автоматически (исправлять входные данные/токен)
- 5xx: ретраи с backoff (например 1s, 3s, 10s, 30s)
- всегда логировать `request_id`

---

## 5. Endpoint для бизнес-интеграции

## 5.1 Получить активные пары устройств

`GET /api/device-pairs/active`

Параметры:
- `format=v2` (рекомендуется)
- fallback: `format=v1`

Пример:

```bash
curl -X GET "https://example.com/api/device-pairs/active?format=v2" \
  -H "X-Api-Token: YOUR_TOKEN" \
  -H "X-Client-Id: recorder-main"
```

Успех: JSON-массив пар.

---

## 5.2 Получить настройки

`GET /api/settings`

Пример:

```bash
curl -X GET "https://example.com/api/settings" \
  -H "X-Api-Token: YOUR_TOKEN" \
  -H "X-Client-Id: terminal-01"
```

Для загрузки снимков SkudConsole использует `photo.ftps`. Поле
`photo.public_http_base` необязательно: оно задаёт базовый HTTP(S)-адрес,
по которому браузер открывает уже загруженный файл. Это не адрес FTPS-сервера.
Если поле не задано, клиент использует для обратной совместимости `http://<host>`.

---

## 5.3 Табличные настройки

- `GET /api/settings/table`
- `PUT /api/settings/table`

Пример `PUT`:

```bash
curl -X PUT "https://example.com/api/settings/table" \
  -H "Content-Type: application/json" \
  -H "X-Api-Token: YOUR_TOKEN" \
  -d "{\"ServerIp\":\"10.0.0.10\",\"DbUser\":\"app\"}"
```

---

## 5.4 Пользователи

- `GET /api/users`
- `POST /api/users/auth`

Пример авторизации пользователя:

```bash
curl -X POST "https://example.com/api/users/auth" \
  -H "Content-Type: application/json" \
  -H "X-Api-Token: YOUR_TOKEN" \
  -d "{\"login\":\"<LOGIN>\",\"password\":\"<PASSWORD>\"}"
```

---

## 6. Endpoint для диагностики логирования и уведомлений

## 6.1 Проверка статуса каналов

`GET /api/system/notifications/status`

Что возвращает:
- текущий `request_id`
- configured status для email
- параметры очереди

Пример:

```bash
curl -X GET "https://example.com/api/system/notifications/status" \
  -H "X-Api-Token: YOUR_TOKEN" \
  -H "X-Client-Id: recorder-main"
```

---

## 6.2 Тест email-уведомления

`POST /api/system/notifications/test-email`

Назначение:
- создать тестовую критическую запись в `Logs`
- поставить email-уведомление в очередь

Пример:

```bash
curl -X POST "https://example.com/api/system/notifications/test-email" \
  -H "X-Api-Token: YOUR_TOKEN" \
  -H "X-Client-Id: recorder-main"
```

Ожидаемый ответ:

```json
{
  "success": true,
  "data": {
    "request_id": "...",
    "queued": true,
    "log_id": 12345,
    "message": "Тестовая email-нотификация поставлена в обработку"
  }
}
```

---

## 7. Что передавать в запросах от внешних приложений

Минимум:
- `X-Api-Token`

Рекомендуется обязательно:
- `X-Client-Id` — уникальный ID точки или сервера-регистратора
- `X-Request-Id` — уникальный ID запроса

Пример значения `X-Client-Id`:
- `terminal-ekb-001`
- `terminal-ekb-002`
- `registrator-main`

Это позволит в `Logs` быстро выделять проблемный узел при инциденте.

---

## 8. Рекомендации по надёжности клиента

- Использовать connection pooling
- Ограничить параллелизм запросов
- Делать circuit-breaker на частые 5xx
- При 401/403 сразу останавливать ретраи и проверять токен/allowlist
- При 5xx хранить локально:
  - endpoint
  - request body (без секретов)
  - status
  - `request_id`

---

## 9. Безопасность

- Не логировать в клиенте API токен в открытом виде
- Не передавать токен в query string
- Использовать только HTTPS
- Для журналирования на клиенте маскировать чувствительные поля (пароли, ключи)

---

## 10. Чеклист запуска нового внешнего клиента

1. Получить `X-Api-Token` от администратора.
2. Назначить постоянный `X-Client-Id`.
3. Внедрить генерацию `X-Request-Id`.
4. Реализовать обработку единого error-контракта.
5. Добавить ретраи только на 5xx и сетевые ошибки.
6. Проверить `GET /api/system/notifications/status`.
7. Запустить `POST /api/system/notifications/test-email`.
8. Убедиться, что тест ушёл в журнал и уведомление пришло на email.

---

## 11. Быстрый FAQ

**Q: Что делать, если приходит 401?**
A: Проверить `X-Api-Token`, регистр и пробелы в значении.

**Q: Что делать, если приходит 403?**
A: Проверить allowlist IP на сервере и внешний IP клиента.

**Q: Что делать, если приходит 5xx?**
A: Повторить запрос с backoff, сохранить `request_id`, передать его администратору.

**Q: Можно ли использовать Telegram-уведомления?**
A: В текущем контуре отключено, используется email-канал.
