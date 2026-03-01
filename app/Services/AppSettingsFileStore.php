<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class AppSettingsFileStore
{
    private string $path;

    public function __construct()
    {
        // storage/app/settings.json
        $this->path = (string) config('app.settings_file', 'settings.json');
    }

    /** Возвращает настройки с РАСШИФРОВАННЫМИ секретами (для API/логики). */
    public function loadDecrypted(): array
    {
        $data = $this->loadRaw();

        foreach ($this->secretKeys() as $k) {
            if (!empty($data[$k])) {
                try {
                    $data[$k] = Crypt::decryptString((string)$data[$k]);
                } catch (\Throwable $e) {
                    // если в файле мусор — лучше не падать админке
                    $data[$k] = '';
                }
            }
        }

        return $data;
    }

    /** Возвращает как в файле (секреты ЗАШИФРОВАНЫ). Удобно для сохранения без перезаписи паролей. */
    public function loadEncrypted(): array
    {
        return $this->loadRaw();
    }

    /**
     * Сохраняет настройки: секреты шифрует.
     * Если $keepEncryptedSecrets=true — пустые поля секретов НЕ затирают старые значения из файла.
     */
    public function save(array $plainData, bool $keepEncryptedSecrets = true): void
    {
        $current = $this->loadEncrypted();

        // если поле секрета пустое — оставляем старое зашифрованное
        foreach ($this->secretKeys() as $k) {
            $incoming = $plainData[$k] ?? null;

            if ($keepEncryptedSecrets && (is_null($incoming) || $incoming === '')) {
                if (array_key_exists($k, $current)) {
                    $plainData[$k] = $current[$k];
                }
                continue;
            }

            // если пришло непустое — шифруем
            if (!is_null($incoming) && $incoming !== '') {
                $plainData[$k] = Crypt::encryptString((string)$incoming);
            } else {
                $plainData[$k] = '';
            }
        }

        $json = json_encode($plainData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        Storage::put($this->path, $json ?: "{}");
    }

    private function loadRaw(): array
    {
        if (!Storage::exists($this->path)) {
            return [];
        }

        $raw = Storage::get($this->path);
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function secretKeys(): array
    {
        // добавляй сюда все поля, которые нельзя хранить открыто
        return [
            'db_pass',
            'photo_ftps_pass',
            'dahua_sdk_ftps_pass',
        ];
    }
}
