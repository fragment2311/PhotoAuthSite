@php
    $actionRaw = (string) data_get($lastChange ?? null, 'Action', '');
    $actionLabel = match ($actionRaw) {
        'created' => 'добавление',
        'updated' => 'изменение',
        default => $actionRaw !== '' ? $actionRaw : '—',
    };

    $actorLabel = trim((string) (
        data_get($lastChange ?? null, 'actor.Login')
        ?? data_get($lastChange ?? null, 'ActorLogin')
        ?? ''
    ));
    if ($actorLabel === '') {
        $actorLabel = 'Неизвестный пользователь';
    }

    $changedAt = data_get($lastChange ?? null, 'ChangedAt');
    $changedAtLabel = $changedAt
        ? $changedAt->timezone(config('app.timezone'))->format('d.m.Y H:i:s')
        : '—';

    $hasHistory = !empty($lastChange);
@endphp

@if(! $hasHistory)
    <div class="last-change-inline" aria-label="Последнее изменение элемента">
        Последнее действие: история отсутствует
    </div>
@else
    <div class="last-change-inline" aria-label="Последнее изменение элемента">
        Последнее действие: {{ $actionLabel }} · Кто: {{ $actorLabel }} · Когда: {{ $changedAtLabel }}
    </div>
@endif

