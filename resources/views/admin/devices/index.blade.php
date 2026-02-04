<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Устройства</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    :root {
      --bg: #f6f7fb;
      --.sheet: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --border: #e5e7eb;
      --ok: #16a34a;
      --no: #dc2626;
      --primary-bg: #2563eb;
      --secondary-bg: #4b5563;
      --danger-bg: #dc2626;
    }

    * { box-sizing: border-box; }

    .actions-cell {
      white-space: nowrap;
    }

    .action-btn.small {
      padding: 4px 8px;
      font-size: 0.8rem;
    }

    .action-btn.secondary {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--link);
    }

    .action-btn.secondary:hover {
      background: var(--link-hover);
      color: #fff;
    }

    body {
      margin: 0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
      background: var(--bg);
      color: var(--text);
    }

    .wrap {
      max-width: 1200px;
      margin: 120px auto 40px;
      padding: 0 16px;
    }

    h1 {
      font-size: 28px;
      margin: 0;
    }

    .sub {
      color: var(--muted);
      margin-bottom: 24px;
    }





    /* КНОПКИ ДЕЙСТВИЙ НА СТРАНИЦЕ */
    .page-actions {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 20px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .page-actions .action-btn {
      background: var(--primary-bg);
      color: white;
      padding: 10px 18px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 15px;
      transition: background .2s;
    }
    .page-actions .action-btn:hover { background: var(--link-hover); }
 

    .tbtn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: #fff;
      font-size: 13px;
      text-decoration: none;
      cursor: pointer;
      margin-right: 6px;
    }

    .tbtn.small {
      padding: 3px 8px;
      font-size: 12px;
    }

    .tbtn.secondary {
      background: #eef2ff;
      border-color: #e0e7ff;
    }

    .tbtn:hover {
      background: var(--link);
      color: #fff;
      border-color: var(--link-hover);
    }


    .empty {
      border: 1px dashed var(--border);
      border-radius: 12px;
      padding: 20px;
      background: #fafafa;
      color: var(--muted);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
    }
  </style>
  <link rel="stylesheet" href="{{ asset('css/spreadsheet.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header-buttons.css') }}">

</head>
<body>
@include('layouts.header', ['headerTitle' => 'Галерея-Проходы'])

<div class="spreadsheet page-devices">
  <div class="wrap">
    <div class="page-actions">
      <a href="{{ route('admin.devices.create') }}" class="action-btn">Добавить устройство</a>
<a href="{{ route('admin.device_pairs.create') }}" class="action-btn">Создать пару устройств</a>
    </div>

   {{-- Контроллеры --}}
<div class="spreadsheet page-devices sheet">
  <h2 >Контроллеры</h2>

  @if($controllers->count() === 0)
    <p>Список контроллеров пуст.</p>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>IP</th>
            <th>Порт</th>
            <th>Логин</th>
            <th>Локация</th>
            <th>Обновлён</th>
            <th>Активен</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody>
        @foreach($controllers as $c)
          <tr>
            <td>{{ $c->id }}</td>
            <td>{{ $c->ip ?? $c->ip_address ?? '—' }}</td>
            <td>{{ $c->port ?? '—' }}</td>
            <td>{{ $c->login ?? '—' }}</td>
            <td>{{ $c->location ?? '—' }}</td>
            <td>{{ \Illuminate\Support\Str::of($c->last_modified_at)->substr(0, 19) }}</td>

             <td>
      <span class="badge {{ $c->active ? 'yes' : 'no' }}">
        {{ $c->active ? 'Да' : 'Нет' }}
      </span>
    </td>


           <td class="actions-cell">
  <div class="tactions">
    <form method="POST"
          action="{{ route('admin.devices.toggle', ['type' => 'controller', 'id' => $c->id]) }}"
          style="display:inline">
      @csrf
      @method('PATCH')
      <button type="submit" class="action-btn-row small">
        {{ (int)$c->active === 1 ? 'Заблокировать' : 'Разблокировать' }}
      </button>
    </form>

    <a href="{{ route('admin.devices.edit', ['type' => 'controller', 'id' => $c->id]) }}"
       class="action-btn-row secondary small">
      Редактировать
    </a>
  </div>
</td>


          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>


    {{-- Камеры --}}
    <div class="sheet">
      <h2>Камеры</h2>
      @if($cameras->count() === 0)
        <div class="empty"><p>Список камер пуст.</p></div>
      @else
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>IP</th>
                <th>Порт</th>
                <th>Направление</th>
                <th>Логин</th>
                <th>Локация</th>
                <th>Обновлён</th>
                <th>Активна</th>
                <th>Действия</th>
              </tr>
            </thead>
            <tbody>
            @foreach($cameras as $cam)
              <tr>
                <td>{{ $cam->id }}</td>
                <td>{{ $cam->ip ?? $cam->ip_address ?? '—' }}</td>
                <td>{{ $cam->port ?? '—' }}</td>
                <td>{{ $cam->direction_ru ?? '—' }}</td>
                <td>{{ $cam->login ?? '—' }}</td>
                <td>{{ $cam->location ?? '—' }}</td>
                {{-- Обновлён --}}
                <td>{{ \Illuminate\Support\Str::of($cam->last_modified_at)->substr(0, 19) }}</td>
                {{-- Активна --}}
                 <td>
     <span class="badge {{ $cam->active ? 'yes' : 'no' }}">
  {{ $cam->active ? 'Да' : 'Нет' }}
</span>

    </td>

<td class="actions-cell">
  <div class="tactions">
    <form method="POST"
          action="{{ route('admin.devices.toggle', ['type' => 'camera', 'id' => $cam->id]) }}"
          style="display:inline">
      @csrf
      @method('PATCH')
      <button type="submit" class="action-btn-row small">
        {{ $cam->active ? 'Заблокировать' : 'Разблокировать' }}
      </button>
    </form>

    <a href="{{ route('admin.devices.edit', ['type' => 'camera', 'id' => $cam->id]) }}"
       class="action-btn-row secondary small">
      Редактировать
    </a>
  </div>
</td>


              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      @endif

    </div>

    {{-- Пары устройств --}}
<div class="sheet">
  <h2>Пары устройств</h2>

  @if($pairs->count() === 0)
    <div class="empty"><p>Пары устройств отсутствуют.</p></div>
  @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Контроллер (IP)</th>
            <th>Камера вход (IP)</th>
            <th>Камера выход (IP)</th>
            <th>Обновлён</th>
            <th>Активна</th>
            <th>Действия</th>
          </tr>
        </thead>

        <tbody>
        @foreach($pairs as $p)
          @php
            $ctrl = $p->controller;
            $camE = $p->entryCamera;
            $camX = $p->exitCamera;
            $pairId = $p->Id ?? $p->id; // страховка, если accessor id есть
          @endphp

          <tr>
            <td>{{ $pairId }}</td>
            <td>{{ $p->Name }}</td>
            <td>{{ $ctrl?->ip ?? $ctrl?->ip_address ?? '—' }}</td>

            <td>
              @if($camE)
                {{ $camE->ip ?? $camE->ip_address ?? '—' }}
                @if(!empty($camE->direction_ru))
                  <span class="badge">{{ $camE->direction_ru }}</span>
                @endif
              @else
                —
              @endif
            </td>

            <td>
              @if($camX)
                {{ $camX->ip ?? $camX->ip_address ?? '—' }}
                @if(!empty($camX->direction_ru))
                  <span class="badge">{{ $camX->direction_ru }}</span>
                @endif
              @else
                —
              @endif
            </td>

            <td>{{ \Illuminate\Support\Str::of($p->Last_modified_at ?? $p->Updated_at)->substr(0, 19) }}</td>

            <td>
              <span class="badge {{ $p->active ? 'yes' : 'no' }}">
                {{ $p->active ? 'Да' : 'Нет' }}
              </span>
            </td>

            <td class="actions-cell">
              <div class="tactions">
                <form method="POST"
                      action="{{ route('admin.device_pairs.toggle', ['id' => $pairId]) }}"

                      style="display:inline">
                  @csrf
                  @method('PATCH')
                  <button type="submit" class="action-btn-row small">
                    {{ $p->active ? 'Заблокировать' : 'Разблокировать' }}
                  </button>
                </form>

                <a href="{{ route('admin.device_pairs.edit', ['id' => $pairId]) }}"

                   class="action-btn-row secondary small">
                  Редактировать
                </a>
              </div>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

  </div>


</body>
</html>
