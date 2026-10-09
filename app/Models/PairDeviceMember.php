<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PairDeviceMember extends Model
{
    protected $table = 'PairDeviceDevices';
    protected $primaryKey = 'Id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'IdPairDevice',
        'IdController',
        'IdCamera',
        'Role',
        'SortOrder',
        'IsActive',
    ];

    public $timestamps = false;

    public function controller()
    {
        return $this->belongsTo(ControllerDevice::class, 'IdController', 'Id');
    }

    public function camera()
    {
        return $this->belongsTo(Camera::class, 'IdCamera', 'Id');
    }

    public function getDeviceTypeAttribute(): ?string
    {
        if (!empty($this->attributes['IdController'])) {
            return 'controller';
        }

        if (!empty($this->attributes['IdCamera'])) {
            return 'camera';
        }

        return null;
    }

    public function getDeviceAttribute()
    {
        if (!empty($this->attributes['IdController'])) {
            return $this->controller;
        }

        return $this->camera;
    }
}

