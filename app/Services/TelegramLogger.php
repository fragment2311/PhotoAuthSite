<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramLogger
{
    private string $botToken;
    private string $chatId;
    private array $levels;
    private bool $enabled;
    
    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
        $this->chatId = config('services.telegram.chat_id', '');
        $this->enabled = config('services.telegram.enabled', false);
        $rawLevels = (string) config('services.telegram.levels', 'error,critical,fatal,emergency,alert');

        $this->levels = array_values(array_filter(array_map(
            static fn (string $item): string => mb_strtolower(trim($item)),
            explode(',', $rawLevels)
        )));

        if ($this->levels === []) {
            $this->levels = ['error', 'critical', 'fatal', 'emergency', 'alert'];
        }
    }
    
    /**
     * Отправляет сообщение в Telegram
     *
     * @param string $message Текст сообщения
     * @param string $level Уровень ошибки (error, warning, info)
     * @param array $context Дополнительная информация
     * @return bool Успешность отправки
     */
    public function send(string $message, string $level = 'error', array $context = []): bool
    {
        $level = mb_strtolower(trim($level));

        // Проверяем, включена ли отправка в Telegram
        if (!$this->enabled || empty($this->botToken) || empty($this->chatId)) {
            return false;
        }

        if (!in_array($level, $this->levels, true)) {
            return false;
        }
        
        try {
            // Формируем сообщение с информацией об окружении
            $fullMessage = $this->formatMessage($message, $level, $context);
            
            // Отправляем сообщение через Telegram API
            $response = Http::timeout(10)
                ->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                    'chat_id' => $this->chatId,
                    'text' => $fullMessage,
                    'parse_mode' => 'HTML',
                ]);
                
            return $response->successful();
        } catch (\Exception $e) {
            // Логируем ошибку отправки в случае неудачи
            Log::error('Telegram logger error: ' . $e->getMessage());
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->botToken !== '' && $this->chatId !== '';
    }
    
    /**
     * Форматирует сообщение для отправки в Telegram
     */
    private function formatMessage(string $message, string $level, array $context): string
    {
        $levelIcon = match($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'critical' => '🚨',
            'emergency' => '💥',
            default => '❗',
        };
        
        $formattedMessage = "<b>{$levelIcon} [{$level}]</b>\n";
        $formattedMessage .= "<pre>" . htmlspecialchars($message) . "</pre>\n";
        
        // Добавляем информацию об окружении
        $formattedMessage .= "\n<b>Environment:</b>\n";
        $formattedMessage .= "App: " . config('app.name', 'PhotoAuthApp') . "\n";
        $formattedMessage .= "Version: " . config('app.version', 'unknown') . "\n";
        $formattedMessage .= "Environment: " . app()->environment() . "\n";
        
        // Добавляем дополнительную информацию из контекста
        if (!empty($context)) {
            $formattedMessage .= "\n<b>Context:</b>\n";
            foreach ($context as $key => $value) {
                $formattedMessage .= "{$key}: " . (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) . "\n";
            }
        }
        
        // Добавляем timestamp
        $formattedMessage .= "\n<b>Time:</b> " . now()->format('Y-m-d H:i:s T');
        
        return $formattedMessage;
    }
}
