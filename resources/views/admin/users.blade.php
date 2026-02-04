{{-- resources/views/admin/users.blade.php --}}
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Список пользователей</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    :root {
      --bg: #f6f7fb;
      --card: #ffffff;
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



    .wrap {
      max-width: 1200px;
      margin: 120px auto 40px;
      padding: 0 16px;
    }

    /* === ШАПКА === */
    /* === ШАПКА (как в index) === */
/* ==== HEADER (единый) ==== */








    /* Кнопка-иконка маленькая */
    .icon-btn {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: inline-flex;
      justify-content: center;
      align-items: center;
      font-size: 18px;
      background: var(--secondary-bg);
      color: #fff;
      text-decoration: none;
      border: none;
      cursor: pointer;
      transition: 0.2s;
    }
    .icon-btn:hover {
      background: var(--muted);
    }



    .card h2 {
      font-size: 20px;
      margin: 0 0 12px;
    }



    .yes { background: #f0fdf4; border-color: #bbf7d0; color: var(--ok); }
    .no  { background: #fef2f2; border-color: #fecaca; color: var(--no); }

    .empty {
      padding: 20px;
      border-radius: 12px;
      border: 1px dashed var(--border);
      text-align: center;
      background: #fafafa;
      color: var(--muted);
    }

    .actions-cell form {
      display: inline;
    }

    /* Базовый стиль кнопок */



  </style>
    <link rel="stylesheet" href="{{ asset('css/spreadsheet.css') }}">

<link rel="stylesheet"
      href="{{ asset('css/header-buttons.css') }}?v={{ filemtime(public_path('css/header-buttons.css')) }}">
</head>
<body>
  @php use Illuminate\Support\Facades\Auth; @endphp

@include('layouts.header', ['headerTitle' => 'Галерея-Проходы'])


  <div class="wrap">

  <div class="page-actions">
    <a href="{{ route('admin.register') }}" class="action-btn">Добавить пользователя</a>
  </div>

  <div class="spreadsheet">
    <div class="sheet">
      <h2 class="sheet-title">Список пользователей</h2>

      @if($users->isEmpty())
        <div class="empty">Пользователей нет.</div>
      @else
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>Роль</th>
                <th>Активность</th>
                <th>Последнее обновление</th>
                <th class="actions-cell">Действия</th>
              </tr>
            </thead>

            <tbody>
              @foreach($users as $user)
                <tr>
                  <td>{{ $user->Id }}</td>
                  <td>{{ $user->Login }}</td>
                  <td>{{ $user->role->NameRole ?? '—' }}</td>
                  <td>
                    @if($user->IsActive)
                      <span class="badge yes">Активен</span>
                    @else
                      <span class="badge no">Заблокирован</span>
                    @endif
                  </td>
                  <td>{{ \Carbon\Carbon::parse($user->LastUpdate)->format('d.m.Y H:i') }}</td>

                  <td class="actions-cell">
                    <div class="tactions">

                      {{-- Редактировать (оставляем ссылку как было), просто даём “табличный” класс --}}
                      <a href="{{ route('admin.users.edit', ['user' => $user->Id]) }}"

                         class="action-btn-row secondary"
                         title="Редактировать">Редактировать</a>

                      {{-- Toggle (оставляем PATCH и ваш маршрут как было) --}}
                      <form method="POST"
                            action="{{ route('admin.users.toggle', $user->Id) }}"
                            
                            style="display:inline">
                        @csrf
                        @method('PATCH')

                        @if($user->IsActive)
                          <button type="submit" class="action-btn-row">Заблокировать</button>
                        @else
                          <button type="submit" class="action-btn-row secondary">Активировать</button>
                        @endif
                      </form>

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

</div>

</body>
</html>
