<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AppSetting extends Model
{
    protected $connection = 'remote_mysql';

    protected $table = 'settings';

    public $timestamps = false;

    protected $fillable = [
        'ServerIp',
        'DbUser',
        'DbPassword',
        'TerminalIp',
        'TerminalUser',
        'TerminalPassword',
        'IPCam',
        'LoginCam',
        'PassCam',
        'IdWebusers',
        'ChangedAt',
    ];

    protected $casts = [
        'ChangedAt' => 'datetime',
    ];

    private const SECRET_FIELDS = [
        'DbPassword',
        'TerminalPassword',
        'PassCam',
    ];

    public function setAttribute($key, $value)
    {
        if (in_array((string) $key, self::SECRET_FIELDS, true)) {
            $value = $this->encryptSecret($value);
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);

        if (in_array((string) $key, self::SECRET_FIELDS, true)) {
            return $this->decryptSecret($value);
        }

        return $value;
    }

    private function encryptSecret(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;
        if ($string === '') {
            return '';
        }

        return Crypt::encryptString($string);
    }

    private function decryptSecret(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;
        if ($string === '') {
            return '';
        }

        try {
            return Crypt::decryptString($string);
        } catch (\Throwable) {
            return $string;
        }
    }
}
