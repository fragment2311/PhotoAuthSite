<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class UpdateAgentClient
{
    public function request(string $method, string $path, array $payload = []): array
    {
        $url = rtrim((string) config('update.agent_url'), '/');
        $token = (string) config('update.agent_token');

        if ($url === '' || strlen($token) < 32) {
            throw new RuntimeException('Обновление не настроено на сервере.');
        }

        $request = Http::acceptJson()
            ->withToken($token)
            ->connectTimeout(3)
            ->timeout(12);

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url . $path),
            'POST' => $request->post($url . $path, $payload),
            default => throw new RuntimeException('Недопустимая операция обновления.'),
        };

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
