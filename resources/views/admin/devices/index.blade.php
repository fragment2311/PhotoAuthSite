<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Устройства</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    :root {
      --bg: #f6f7fb;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --link: #2563eb;
      --link-hover: #1d4ed8;
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

    /* КНОПКИ В ШАПКЕ */
    header {
      position: fixed;
      height: 80px;
      top: 0; left: 0; right: 0;
      background: var(--card);
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      z-index: 1000;
    }

    header h1 { font-size: 2rem; margin: 0; }

    header .btn-group { display: flex; gap: 1rem; }

    header .btn {
      display: inline-block;
      padding: 0.7rem 1.4rem;
      font-size: 1.1rem;
      border-radius: 6px;
      text-decoration: none;
      color: white;
      border: 2px solid transparent;
      cursor: pointer;
      transition: .2s;
    }

    .btn-primary { background: var(--primary-bg); border-color: var(--primary-bg); }
    .btn-primary:hover { background: var(--link-hover); border-color: var(--link-hover); }
    .btn-secondary { background: var(--secondary-bg); border-color: var(--secondary-bg); }
    .btn-secondary:hover { background: var(--muted); border-color: var(--muted); }
    .btn-danger { background: var(--danger-bg); border-color: var(--danger-bg); }
    .btn-danger:hover { background: #b91c2a; border-color: #b91c2a; }

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
    .actions-cell {
      white-space: nowrap;
    }

    .action-btn-row {
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

    .action-btn-row.small {
      padding: 3px 8px;
      font-size: 12px;
    }

    .action-btn-row.secondary {
      background: #eef2ff;
      border-color: #e0e7ff;
    }

    .action-btn-row:hover {
      background: var(--link);
      color: #fff;
      border-color: var(--link-hover);
    }

    /* КАРТОЧКИ */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 24px;
      box-shadow: 0 2px 6px rgba(0,0,0,.04);
    }

    .card h2 { font-size: 20px; margin: 0 0 12px; }

    .table-wrap {
      width: 100%;
      overflow: auto;
      border-radius: 10px;
      border: 1px solid var(--border);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
    }

    th, td { padding: 10px 12px; text-align: left; white-space: nowrap; }

    thead th {
      position: sticky;
      top: 0;
      background: #f9fafb;
      font-weight: 600;
      font-size: 13px;
      border-bottom: 1px solid var(--border);
    }

    tbody tr + tr td { border-top: 1px solid var(--border); }

    .badge {
      padding: 3px 8px;
      border-radius: 999px;
      font-size: 12px;
      border: 1px solid var(--border);
      display: inline-block;
    }

    .yes { color: var(--ok); border-color: #bbf7d0; background: #f0fdf4; }
    .no  { color: var(--no); border-color: #fecaca; background: #fef2f2; }

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
</head>
<body>
  <header>
    <h1>
      <a href="{{ route('gallery',['dir'=>'entrance_photos']) }}"
         style="text-decoration:none;color:inherit;">
        Галерея-Устройства
      </a>
    </h1>
    <div class="btn-group">
      <a href="{{ route('gallery', ['dir' => 'entrance_photos']) }}" class="btn btn-primary">В галерею</a>
      <a href="{{ route('admin.devices.index') }}" class="btn btn-primary">Устройства</a>
      <a href="{{ route('admin.users') }}" class="btn btn-primary">Список пользователей</a>
      <a href="{{ route('admin.register') }}" class="btn btn-primary">Новый пользователь</a>
      <form method="POST" action="{{ route('logout') }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-danger">Выйти</button>
      </form>
    </div>
  </header>

  <div class="wrap">
    <div class="page-actions">
      <a href="{{ route('admin.devices.create') }}" class="action-btn">Добавить устройство</a>
<a href="{{ route('admin.device_pairs.create') }}" class="action-btn">Создать пару устройств</a>
    </div>

    {{-- Контроллеры --}}
    <div class="card">
      <h2>Контроллеры</h2>
      @if($controllers->count() === 0)
        <div class="empty">
          <p>Список контроллеров пуст.</p>
        </div>
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
                {{-- Обновлён --}}
                <td>{{ \Illuminate\Support\Str::of($c->last_modified_at)->substr(0, 19) }}</td>
                {{-- Активен --}}
                <td>
                  <span class="badge {{ (int)$c->active === 1 ? 'yes' : 'no' }}">
                    {{ (int)$c->active === 1 ? 'Да' : 'Нет' }}
                  </span>
                </td>
                <td class="actions-cell">
                  {{-- Заблокировать / Разблокировать --}}
                  <form method="POST"
                        action="{{ route('admin.devices.toggle', ['type' => 'controller', 'id' => $c->id]) }}"
                        style="display:inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="action-btn-row small">
                      {{ (int)$c->active === 1 ? 'Заблокировать' : 'Разблокировать' }}
                    </button>
                  </form>

                  {{-- Редактировать --}}
                  <a href="{{ route('admin.devices.edit', ['type' => 'controller', 'id' => $c->id]) }}"
                     class="action-btn-row secondary small">
                    Редактировать
                  </a>
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      @endif

    </div>

    {{-- Камеры --}}
    <div class="card">
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
                  <span class="badge {{ (int)$cam->active === 1 ? 'yes' : 'no' }}">
                    {{ (int)$cam->active === 1 ? 'Да' : 'Нет' }}
                  </span>
                </td>
                <td class="actions-cell">
                  {{-- Заблокировать / Разблокировать --}}
                  <form method="POST"
                        action="{{ route('admin.devices.toggle', ['type' => 'camera', 'id' => $cam->id]) }}"
                        style="display:inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="action-btn-row small">
                      {{ (int)$cam->active === 1 ? 'Заблокировать' : 'Разблокировать' }}
                    </button>
                  </form>

                  {{-- Редактировать --}}
                  <a href="{{ route('admin.devices.edit', ['type' => 'camera', 'id' => $cam->id]) }}"
                     class="action-btn-row secondary small">
                    Редактировать
                  </a>
                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      @endif

    </div>

           {{-- Пары устройств --}}
    <div class="card">
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
              @endphp
              <tr>
                <td>{{ $p->Id }}</td>
                <td>{{ $p->Name }}</td>

                {{-- Контроллер --}}
                <td>{{ $ctrl?->ip ?? $ctrl?->ip_address ?? '—' }}</td>

                {{-- Камера вход --}}
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

                {{-- Камера выход --}}
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

                {{-- Обновлён --}}
                <td>{{ \Illuminate\Support\Str::of($p->Updated_at)->substr(0, 19) }}</td>

                {{-- Активна --}}
                <td>
                  <span class="badge {{ (int)$p->IsActive === 1 ? 'yes' : 'no' }}">
                    {{ (int)$p->IsActive === 1 ? 'Да' : 'Нет' }}
                  </span>
                </td>

                {{-- Действия --}}
                <td class="actions-cell">
                  {{-- Блокировка / разблокировка --}}
                  <form method="POST"
      action="{{ route('admin.device_pairs.toggle', ['id' => $p->Id]) }}"
      style="display:inline">

                    @csrf
                    @method('PATCH')
                    <button type="submit" class="action-btn-row small">
                      {{ (int)$p->IsActive === 1 ? 'Заблокировать' : 'Разблокировать' }}
                    </button>
                  </form>

                  {{-- Редактировать --}}
                 <a href="{{ route('admin.device_pairs.edit', ['id' => $p->Id]) }}"
   class="action-btn-row secondary small">
    Редактировать
</a>


                </td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>


</body>
</html>
