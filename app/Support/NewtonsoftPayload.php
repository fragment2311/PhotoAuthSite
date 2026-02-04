<?php

namespace App\Support;

final class NewtonsoftPayload
{
    public static function build(string $clientId, string $validUntil, string $issuedAt, array $features): string
    {
        $clientId   = self::toUtf8Trim($clientId);
        $validUntil = self::toUtf8Trim($validUntil);
        $issuedAt   = self::toUtf8Trim($issuedAt);

        // features: ключи -> UTF-8, значения -> bool, сортировка по ключу
        $normFeatures = [];
        foreach ($features as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $k = self::toUtf8Trim($k);
            if ($k === '') {
                continue;
            }
            $normFeatures[$k] = (bool)$v;
        }

        ksort($normFeatures, SORT_STRING);

        // Порядок полей как в C# LicensePayload
        $payload = [
            'client_id'   => $clientId,
            'valid_until' => $validUntil,
            'features'    => $normFeatures,
            'issued_at'   => $issuedAt,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $err = json_last_error_msg();
            // Дадим максимально полезную ошибку
            throw new \RuntimeException('json_encode failed in payload builder: ' . $err);
        }

        return $json;
    }

    private static function toUtf8Trim(string $s): string
    {
        $s = trim($s);

        // Если строка уже валидный UTF-8 — оставляем
        if ($s === '' || mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }

        // Попытка сконвертировать из наиболее вероятных кодировок Windows
        // (актуально, если license.lic был сохранён не в UTF-8)
        $converted = @mb_convert_encoding($s, 'UTF-8', ['Windows-1251', 'UTF-8', 'ISO-8859-5']);

        // Если всё ещё невалидно — "чинить" уже нечем
        if (!mb_check_encoding($converted, 'UTF-8')) {
            // убираем невалидные байты
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $converted);
        }

        return is_string($converted) ? trim($converted) : $s;
    }
}
