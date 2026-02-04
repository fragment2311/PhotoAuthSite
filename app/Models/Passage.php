<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Passage extends Model
{
    protected $table = 'passage';
    protected $primaryKey = 'IdPassage';
    public $timestamps = false;

    protected $casts = [
        'AccessTime' => 'datetime',
    ];

    public function person()
    {
        return $this->belongsTo(MergedVisitControl::class, 'IdMergedVisitControl', 'idmergedvisitcontrol');
    }
}