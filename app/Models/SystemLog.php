<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $connection = 'remote_mysql';
    protected $table = 'Logs';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'Message',
        'EventDate',
        'LogType',
        'IPAddress',
    ];

    protected $casts = [
        'EventDate' => 'datetime',
    ];
}

