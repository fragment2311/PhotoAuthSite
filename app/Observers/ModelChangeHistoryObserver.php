<?php

namespace App\Observers;

use App\Services\ChangeHistoryService;
use Illuminate\Database\Eloquent\Model;

class ModelChangeHistoryObserver
{
    public function created(Model $model): void
    {
        app(ChangeHistoryService::class)->log($model, 'created');
    }

    public function updated(Model $model): void
    {
        if (! $model->wasChanged()) {
            return;
        }

        app(ChangeHistoryService::class)->log($model, 'updated');
    }
}

