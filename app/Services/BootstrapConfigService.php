<?php

namespace App\Services;

class BootstrapConfigService
{
    public function __construct(private readonly AppSettingsFileStore $store)
    {
    }

    /**
     * @return array<int, string>
     */
    public function missingCriticalFields(): array
    {
        $s = $this->store->loadDecrypted();

        $requiredSettings = [
            'db_host' => 'DB host',
            'db_port' => 'DB port',
            'db_name' => 'DB name',
            'db_user' => 'DB user',
        ];

        $missing = [];

        foreach ($requiredSettings as $key => $label) {
            $value = trim((string) ($s[$key] ?? ''));
            if ($value === '') {
                $missing[] = $label;
            }
        }

        $apiToken = trim((string) env('DEVICE_PAIRS_API_TOKEN', ''));
        if ($apiToken === '') {
            $missing[] = 'DEVICE_PAIRS_API_TOKEN';
        }

        return $missing;
    }

    public function isConfigured(): bool
    {
        return $this->missingCriticalFields() === [];
    }

    public function isValidSetupKey(?string $provided): bool
    {
        $provided = trim((string) $provided);
        $expected = $this->expectedSetupKey();

        if ($provided === '' || $expected === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    public function expectedSetupKey(): string
    {
        $fromEnv = trim((string) env('SETUP_ACCESS_KEY', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $appKey = trim((string) env('APP_KEY', ''));
        if ($appKey === '') {
            return '';
        }

        if (str_starts_with($appKey, 'base64:')) {
            $appKey = substr($appKey, 7);
        }

        return hash('sha256', $appKey . '|setup-access');
    }

    public function hasMasterPassword(): bool
    {
        $settings = $this->store->loadDecrypted();
        $hash = trim((string) ($settings['setup_master_password_hash'] ?? ''));

        return $hash !== '';
    }

    public function verifyMasterPassword(?string $password): bool
    {
        $password = (string) $password;
        if ($password === '') {
            return false;
        }

        $settings = $this->store->loadDecrypted();
        $hash = trim((string) ($settings['setup_master_password_hash'] ?? ''));
        if ($hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }
}

