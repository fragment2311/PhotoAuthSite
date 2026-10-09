<?php

namespace App\Services;

use App\Models\ChangeHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ChangeHistoryService
{
    public function log(Model $model, string $action): void
    {
        $user = Auth::user();

        $actorId = null;
        $actorLogin = 'system';

        if ($user) {
            $rawId = data_get($user, 'Id') ?? data_get($user, 'id');
            $actorId = is_numeric($rawId) ? (int) $rawId : null;

            $actorLogin = (string) (
                data_get($user, 'Login')
                ?? data_get($user, 'login')
                ?? data_get($user, 'name')
                ?? 'system'
            );
        }

        $objectiveId = $model->getAttribute($model->getKeyName()) ?? $model->getKey();
        if ($objectiveId === null) {
            return;
        }

        ChangeHistory::create([
            'Objective'      => $this->resolveObjective($model),
            'ObjectiveId'    => (string) $objectiveId,
            'Action'         => $action,
            'ActorWebuserId' => $actorId,
            'ActorLogin'     => trim($actorLogin) !== '' ? $actorLogin : 'system',
            'ChangedAt'      => now(),
            'DetailsJson'    => null,
        ]);

        app(ErrorNotificationService::class)->log('info', 'Изменение данных', [
            'objective' => $this->resolveObjective($model),
            'objective_id' => (string) $objectiveId,
            'action' => $action,
            'actor_webuser_id' => $actorId,
            'actor_login' => trim($actorLogin) !== '' ? $actorLogin : 'system',
        ]);
    }

    public function latestFor(string $objective, string|int $objectiveId): ?ChangeHistory
    {
        return ChangeHistory::query()
            ->with('actor')
            ->where('Objective', $objective)
            ->where('ObjectiveId', (string) $objectiveId)
            ->orderByDesc('ChangedAt')
            ->orderByDesc('Id')
            ->first();
    }

    private function resolveObjective(Model $model): string
    {
        return class_basename($model);
    }
}

