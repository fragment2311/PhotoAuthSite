<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Webuser extends Authenticatable
{
    protected $connection = 'remote_mysql';
    protected $table = 'webusers';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'Login',
        'Password',
        'IdRole',
        'IsActive',    // <-- добавлено
        'LastUpdate',
    ];

    // Указать, где хранится пароль
    public function getAuthPassword()
    {
        return $this->Password;
    }

    // связь с ролью
    public function role()
    {
        return $this->belongsTo(Role::class, 'IdRole', 'ID');
    }
}
