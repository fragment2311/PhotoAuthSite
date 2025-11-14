<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Camera extends Model
{
    protected $table = 'cameras';
    public $timestamps = false;

    protected $fillable = [
        'ip_address','Ip_address','IP_address', // на всякий случай
        'Port','Login','Password','Location',
        'Direction','Last_modified_at','IsActive'
    ];

    // Универсальный геттер по набору возможных ключей
    private function pick(array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $this->attributes) && $this->attributes[$k] !== null && $this->attributes[$k] !== '') {
                return $this->attributes[$k];
            }
        }
        return null;
    }

    public function getIpAttribute()                 { return $this->pick(['ip_address','Ip_address','IP_address']); }
    public function getPortAttribute()               { return $this->pick(['Port','port']); }
    public function getLoginAttribute()              { return $this->pick(['Login','login']); }
    public function getPasswordAttribute()           { return $this->pick(['Password','password']); }
    public function getLocationAttribute()           { return $this->pick(['Location','location']); }
    public function getActiveAttribute()             { return $this->pick(['IsActive','active']); }
    public function getLastModifiedAtAttribute()     { return $this->pick(['Last_modified_at','last_modified_at']); }
    public function getDirectionAttribute()          { return $this->pick(['Direction','direction']); }

       public function getIdAttribute()
    {
        return $this->pick(['id', 'ID', 'Id']);
    }
    
    // Русское направление
    public function getDirectionRuAttribute()
    {
        return match($this->getDirectionAttribute()) {
            'entry' => 'Вход',
            'exit'  => 'Выход',
            default => 'Другое',
        };
    }
}
