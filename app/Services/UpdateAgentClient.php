<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class UpdateAgentClient
{
    public function request(string $method, string $path, array $payload = []): array
    {
        $runtime = app(UpdateRuntime::class);
        $url = $runtime->agentUrl();
        $token = (string) config('update.agent_token');

        if ($url === '' || strlen($token) < 32) {
            throw new RuntimeException('Для обнаруженного режима ' . $runtime->mode() . ' не настроен агент обновления.');
        }

        $request = Http::acceptJson()
            ->withToken($token)
            ->connectTimeout(3)
            ->timeout(12);

        try {
            $response = match (strtoupper($method)) {
                'GET' => $request->get($url . $path),
                'POST' => $request->post($url . $path, $payload),
                default => throw new RuntimeException('Недопустимая операция обновления.'),
            };
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            $mode = $runtime->mode();
            $platform = $runtime->platform();
            $where = $mode === 'docker' ? 'агент в Docker Compose' : 'агент ОС на ' . $platform;
            throw new RuntimeException('Не удалось связаться с ' . $where . '. Проверьте, что агент установлен и запущен.', 0, $exception);
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new RuntimeException('Сервис обновления вернул некорректный ответ.');
        }

        return [
            'status' => $response->status(),
            'data' => $data,
        ];
    }
}
