# Настройка Telegram бота для PhotoAuthApp

## 1. Конфигурация в Laravel

### 1.1 Файл конфигурации services.php
Добавим настройки Telegram в `config/services.php`:

```php
<?php

return [
    // ... другие настройки
    
    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    ],
    
    // ... остальные настройки
];
```

### 1.2 Переменные окружения .env
В файле `.env` добавим следующие переменные:

```
# Telegram настройки
TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=8340634038:AAE-Fkstk8zpfkk_bybwRHH4P5ivX6yQo4I
TELEGRAM_CHAT_ID=-1001234567890
```

## 2. Получение данных для настройки

### 2.1 Создание бота
Для создания бота нужно:
1. Написать @BotFather в Telegram
2. Выполнить команду `/newbot`
3. Ввести имя бота (например, PhotoAuthApp Bot)
4. Ввести username бота (например, photoauthapp_bot)
5. Скопировать токен, который будет использоваться в `TELEGRAM_BOT_TOKEN`

### 2.2 Получение chat_id
Для получения chat_id нужно:
1. Написать своему боту в Telegram
2. Перейти по адресу: `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates`
3. Найти в ответе поле `message.chat.id`

## 3. Структура конфигурации

### 3.1 Основные параметры
- `enabled`: включение/отключение отправки в Telegram
- `bot_token`: токен бота для авторизации
- `chat_id`: ID чата для отправки сообщений

### 3.2 Дополнительные параметры (опционально)
- `timeout`: таймаут отправки (по умолчанию 10 секунд)
- `max_retries`: количество попыток отправки
- `levels`: массив уровней, которые нужно отправлять

## 4. Пример использования в коде

```php
// В любом месте кода
use App\Services\TelegramLogger;

$telegram = new TelegramLogger();
$telegram->send('Произошла ошибка в системе', 'error', [
    'exception' => $exception->getMessage(),
    'file' => $exception->getFile(),
    'line' => $exception->getLine(),
]);
```

## 5. Уровни ошибок и фильтрация

### 5.1 Стандартные уровни
- `emergency`: чрезвычайные ситуации
- `alert`: серьезные ошибки
- `critical`: критические ошибки
- `error`: ошибки
- `warning`: предупреждения
- `notice`: замечания
- `info`: информационные сообщения
- `debug`: отладочные сообщения

### 5.2 Фильтрация по уровням
В конфигурации можно указать, какие уровни отправлять:
```php
'telegram' => [
    'enabled' => env('TELEGRAM_ENABLED', false),
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    'levels' => ['error', 'warning', 'critical', 'emergency'],
],
```

## 6. Безопасность

### 6.1 Хранение токена
- Токен хранится в переменных окружения
- Не должен быть внесен в репозиторий
- Должен быть защищен от доступа посторонних

### 6.2 Ограничения доступа
- Только авторизованные пользователи могут изменять настройки
- Все изменения логгируются
- Используются проверки на корректность данных

## 7. Тестирование конфигурации

### 7.1 Проверка наличия настроек
```php
// Проверка, что все необходимые параметры установлены
if (!config('services.telegram.enabled') || 
    !config('services.telegram.bot_token') || 
    !config('services.telegram.chat_id')) {
    // Логируем ошибку или используем fallback
}
```

### 7.2 Тестовое сообщение
При запуске приложения можно отправить тестовое сообщение:
```php
$telegram = new TelegramLogger();
$telegram->send('PhotoAuthApp успешно запущен', 'info');