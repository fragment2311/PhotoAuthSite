# Device Pairs API for C# (`/api/device-pairs/active`)

Документ фиксирует текущий контракт API получения активных пар устройств с учетными данными и готовые примеры интеграции для C#.

## Endpoint

- Method/URL: `GET /api/device-pairs/active`
- Auth header: `X-Api-Token: <API_TOKEN>`
- Correlation headers: `X-Client-Id: <CLIENT_ID>`, `X-Request-Id: <UUID>`
- Middleware: `api`, `request.id`, `api.token`, `api.audit`
- Response: JSON-массив пар (без обертки `data`)

## Параметры запроса

- `format=v2` - рекомендуемый формат
- `format=v1` - legacy формат
- По умолчанию используется `v1`, если `format` не передан

Сервер также трактует как `v2` значения: `2`, `devices`, а также строки с `v2`.

## Фильтрация пар

В ответ попадают только пары, где:

- `IsActive = 1`, или
- `IsActive IS NULL`

Пары с `IsActive = 0` не возвращаются.

## Базовые поля пары (в `v1` и `v2`)

Каждый элемент массива содержит:

- `pair_id` (alias `id`)
- `pair_name` (alias `name`)
- `is_active`

## Формат `v2` (рекомендуется)

Запрос:

```http
GET /api/device-pairs/active?format=v2
X-Api-Token: <API_TOKEN>
```

Поля пары в `v2`:

- `pair_id`, `id`, `pair_name`, `name`, `is_active`
- `location_id`
- `factory_code` — код каталога фотографий; берётся из названия локации
- `comment`
- `devices[]`
- `controller`, `entry_camera`, `exit_camera` (для обратной совместимости)

### Структура `devices[]`

- `member_id`
- `id`
- `type` (`controller` | `camera`)
- `role` (`controller` | `entry_camera` | `exit_camera`)
- `name`
- `ip`
- `port`
- `login`
- `password`
- `is_active`
- `sort_order`

`devices[]` отсортирован по `sort_order`: контроллер -> входная камера -> выходная камера.

Пример `v2`:

```json
[
  {
    "pair_id": 1,
    "id": 1,
    "pair_name": "Main Gate",
    "name": "Main Gate",
    "is_active": true,
    "location_id": 3,
    "factory_code": "<FACTORY_CODE>",
    "comment": "Primary access point",
    "devices": [
      {
        "member_id": 101,
        "id": 10,
        "type": "controller",
        "role": "controller",
        "name": "Controller A",
        "ip": "192.168.1.10",
        "port": 37777,
        "login": "admin",
        "password": "<DEVICE_PASSWORD>",
        "is_active": true,
        "sort_order": 10
      },
      {
        "member_id": 102,
        "id": 11,
        "type": "camera",
        "role": "entry_camera",
        "name": "Camera Entry",
        "ip": "192.168.1.11",
        "port": 80,
        "login": "admin",
        "password": "<DEVICE_PASSWORD>",
        "is_active": true,
        "sort_order": 20
      },
      {
        "member_id": 103,
        "id": 12,
        "type": "camera",
        "role": "exit_camera",
        "name": "Camera Exit",
        "ip": "192.168.1.12",
        "port": 80,
        "login": "admin",
        "password": "<DEVICE_PASSWORD>",
        "is_active": true,
        "sort_order": 30
      }
    ],
    "controller": {
      "id": 10,
      "type": "controller",
      "name": "Controller A",
      "ip": "192.168.1.10",
      "port": 37777,
      "login": "admin",
      "password": "<DEVICE_PASSWORD>",
      "is_active": true
    },
    "entry_camera": {
      "id": 11,
      "type": "camera",
      "name": "Camera Entry",
      "ip": "192.168.1.11",
      "port": 80,
      "login": "admin",
      "password": "<DEVICE_PASSWORD>",
      "is_active": true
    },
    "exit_camera": {
      "id": 12,
      "type": "camera",
      "name": "Camera Exit",
      "ip": "192.168.1.12",
      "port": 80,
      "login": "admin",
      "password": "<DEVICE_PASSWORD>",
      "is_active": true
    }
  }
]
```

## Формат `v1` (legacy)

Запрос:

```http
GET /api/device-pairs/active?format=v1
X-Api-Token: <API_TOKEN>
```

Поля пары в `v1`:

- `pair_id`, `id`, `pair_name`, `name`, `is_active`
- `controller`
- `entry_camera`
- `exit_camera`
- `devices[]` (дополнительно, для плавной миграции клиентов)

## Как сервер формирует устройства

Сервер читает только активные строки `PairDeviceDevices`, связанные с точкой через
`IdPairDevice`. Одна строка описывает ровно одно устройство. Допустимые роли:

- `controller` — обязательный контроллер точки;
- `entry_camera` — камера, из которой SkudConsole сохраняет фото;
- `exit_camera` — необязательная дополнительная камера выхода.

Уникальные ограничения БД не позволяют назначить одно устройство двум точкам,
а также создать две строки одной роли у одной точки.

## Пароли и креды

- Поле `password` отдается уже готовым к использованию клиентом
- Если значение похоже на Laravel encrypted payload, сервер пытается расшифровать через `Crypt::decryptString()`
- Если расшифровка не удалась и это не encrypted payload, значение отдается как plain text (compatibility fallback)
- Пустые строки по `ip/login/password` нормализуются в `null`

## Ошибки сериализации пары

Если при сборке одной пары возникает исключение, вместо полного объекта пары возвращается fallback-структура:

- `warning: "decryption_error"`
- `controller: null`
- `entry_camera: null`
- `exit_camera: null`
- `devices: []`

Остальные пары в массиве при этом продолжают возвращаться.

## C# модели

```csharp
public sealed class DevicePairResponse
{
    public int pair_id { get; set; }
    public int id { get; set; }
    public string? pair_name { get; set; }
    public string? name { get; set; }
    public bool is_active { get; set; }

    public int? location_id { get; set; }
    public string? comment { get; set; }

    public List<DeviceDto>? devices { get; set; }
    public DeviceDto? controller { get; set; }
    public DeviceDto? entry_camera { get; set; }
    public DeviceDto? exit_camera { get; set; }

    public string? warning { get; set; }
}

public sealed class DeviceDto
{
    public int? member_id { get; set; }
    public int id { get; set; }
    public string? type { get; set; }
    public string? role { get; set; }
    public string? name { get; set; }
    public string? ip { get; set; }
    public int? port { get; set; }
    public string? login { get; set; }
    public string? password { get; set; }
    public bool is_active { get; set; }
    public int? sort_order { get; set; }
}
```

## C# пример запроса

```csharp
using System.Net.Http.Headers;
using System.Text.Json;

var baseUrl = "http://localhost:27017";
var apiToken = "<API_TOKEN>";

using var http = new HttpClient { BaseAddress = new Uri(baseUrl) };
http.DefaultRequestHeaders.Add("X-Api-Token", apiToken);

var response = await http.GetAsync("/api/device-pairs/active?format=v2");
response.EnsureSuccessStatusCode();

var json = await response.Content.ReadAsStringAsync();
var pairs = JsonSerializer.Deserialize<List<DevicePairResponse>>(json, new JsonSerializerOptions
{
    PropertyNameCaseInsensitive = true
});
```

## Безопасность интеграции

- Использовать только `HTTPS` в production
- Не хранить реальный `API_TOKEN` в коде приложения
- Передавать токен через защищенную конфигурацию/секреты окружения
- Ограничивать доступ по `DEVICE_PAIRS_API_IP_ALLOWLIST`, если возможно
