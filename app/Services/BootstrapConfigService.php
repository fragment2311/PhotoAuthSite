<?php

namespace App\Services;

class BootstrapConfigService
{
    private const DEFAULT_MASTER_LOGIN = 'MASTER';

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

        $apiToken = trim((string) env('API_TOKEN', ''));
        if ($apiToken === '') {
            $missing[] = 'API_TOKEN';
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

    public function getMasterLogin(): string
    {
        $settings = $this->store->loadDecrypted();
        $login = trim((string) ($settings['setup_master_login'] ?? ''));

        if ($login === '') {
            return self::DEFAULT_MASTER_LOGIN;
        }

        return $login;
    }

    public function isMasterLogin(?string $login): bool
    {
        $rawLogin = trim((string) $login);
        if ($rawLogin === '') {
            // Обратная совместимость: мастер-пароль без логина.
            return true;
        }

        $provided = mb_strtolower($rawLogin);
        $expected = mb_strtolower($this->getMasterLogin());

        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, $provided);
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

    public function verifyMasterCredentials(?string $login, ?string $password): bool
    {
        if (!$this->isMasterLogin($login)) {
            return false;
        }

        return $this->verifyMasterPassword($password);
    }
}

