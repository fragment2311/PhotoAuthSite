<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeHistory extends Model
{
    protected $table = 'change_histories';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'Objective',
        'ObjectiveId',
        'Action',
        'ActorWebuserId',
        'ActorLogin',
        'ChangedAt',
        'DetailsJson',
    ];

    protected $casts = [
        'ChangedAt' => 'datetime',
        'DetailsJson' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(Webuser::class, 'ActorWebuserId', 'Id');
    }
}

