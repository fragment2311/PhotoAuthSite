<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PairDevice extends Model
{
    protected $table = 'pairdevices';
    protected $primaryKey = 'Id';

    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // если у тебя нет created_at/updated_at

    protected $fillable = [
        'Name',
        'Location',
        'Controller_id',
        'Entry_camera_id',
        'Exit_camera_id',
        'Comment',
        'IsActive',
        'Last_modified_at',
    ];

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

    public function getIdAttribute()
    {
        return $this->pick(['id', 'ID', 'Id']);
    }

    public function getNameAttribute()
    {
        return $this->pick(['Name', 'name']);
    }

    public function getActiveAttribute()
    {
        return $this->pick(['IsActive', 'active']);
    }

    public function getLastModifiedAtAttribute()
    {
        return $this->pick(['Last_modified_at', 'last_modified_at']);
    }

    public function getLocationIdAttribute()
    {
        $value = $this->pick(['Location', 'location']);
        return $value !== null ? (int) $value : null;
    }

    public function locationRef()
    {
        return $this->belongsTo(Location::class, 'Location', 'IdLocation');
    }

    // Связи
    public function controller()
    {
        return $this->belongsTo(ControllerDevice::class, 'Controller_id');
    }

    public function entryCamera()
    {
        return $this->belongsTo(Camera::class, 'Entry_camera_id');
    }

    public function exitCamera()
    {
        return $this->belongsTo(Camera::class, 'Exit_camera_id');
    }
}
