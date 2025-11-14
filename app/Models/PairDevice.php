<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PairDevice extends Model
{
    // Явное имя таблицы
    protected $table = 'pairdevices';

    // Первичный ключ
    protected $primaryKey = 'Id';

     public $incrementing = true;
    protected $keyType = 'int';

    // Нет created_at, только Updated_at
    const CREATED_AT = null;
    const UPDATED_AT = 'Updated_at';

    protected $fillable = [
        'Name',
        'Controller_id',
        'Entry_camera_id',
        'Exit_camera_id',
        'Comment',
        'IsActive',
    ];

    protected $casts = [
        'IsActive' => 'boolean',
    ];

    // --- Связи ---

    // Контроллер
    public function controller()
    {
        return $this->belongsTo(ControllerDevice::class, 'Controller_id');
    }

    // Камера входа
    public function entryCamera()
    {
        return $this->belongsTo(Camera::class, 'Entry_camera_id');
    }

    // Камера выхода
    public function exitCamera()
    {
        return $this->belongsTo(Camera::class, 'Exit_camera_id');
    }
}
