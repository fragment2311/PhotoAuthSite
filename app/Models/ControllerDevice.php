<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControllerDevice extends Model
{
    protected $table = 'controllers';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'ip_address', 'Ip_address', 'IP_address',
        'Port', 'Login', 'Password', 'Location',
        'Last_modified_at', 'IsActive',
    ];

    // --- Сервисный метод выбора значения из нескольких возможных ключей ---
    private function pick(array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $this->attributes)
                && $this->attributes[$k] !== null
                && $this->attributes[$k] !== '') {
                return $this->attributes[$k];
            }
        }
        return null;
    }

    // Нормализованные геттеры
    public function getIpAttribute()
    {
        return $this->pick(['ip_address', 'Ip_address', 'IP_address']);
    }

    public function getPortAttribute()
    {
        return $this->pick(['Port', 'port']);
    }

    public function getLoginAttribute()
    {
        return $this->pick(['Login', 'login']);
    }

    public function getPasswordAttribute()
    {
        return $this->pick(['Password', 'password']);
    }

    public function getLocationAttribute()
    {
        return $this->pick(['Location', 'location']);
    }

    public function getActiveAttribute()
    {
        return $this->pick(['IsActive', 'active']);
    }

    public function getLastModifiedAtAttribute()
    {
        return $this->pick(['Last_modified_at', 'last_modified_at']);
    }

    public function getIdAttribute()
    {
        return $this->pick(['id', 'ID', 'Id']);
    }

    // Связь с парами
    public function devicePairs()
    {
        return $this->hasManyThrough(
            PairDevice::class,
            PairDeviceMember::class,
            'IdController',
            'Id',
            'Id',
            'IdPairDevice'
        );
    }

    public function pairMembers()
    {
        return $this->hasMany(PairDeviceMember::class, 'IdController', 'Id');
    }

    public function pairedWith()
    {
        return $this->devicePairs();
    }
}
