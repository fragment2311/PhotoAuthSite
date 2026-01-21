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






    /* --- КНОПКИ ДЕЙСТВИЙ НА СТРАНИЦЕ --- */
    .page-actions {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 20px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .action-btn {
      background: var(--primary-bg);
      color: white;
      padding: 10px 18px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 15px;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .action-btn:hover { background: var(--link-hover); }

    .action-btn.danger { background: var(--danger-bg); }
    .action-btn.danger:hover { background: #b91c2a; }

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

    /* === Карточка + таблица === */
    .card {
      background: var(--card);
      border-radius: 12px;
      border: 1px solid var(--border);
      padding: 16px;
      margin-bottom: 24px;
      box-shadow: 0 2px 6px rgba(0,0,0,.04);
    }

    .card h2 {
      font-size: 20px;
      margin: 0 0 12px;
    }

    .table-wrap {
      width: 100%;
      overflow: auto;
      border-radius: 10px;
      border: 1px solid var(--border);
    }

    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 10px 12px; white-space: nowrap; }

    thead th {
      background: #f9fafb;
      font-weight: 600;
      font-size: 13px;
      color: var(--muted);
      border-bottom: 1px solid var(--border);
    }

    tbody tr:hover td { background: #f9fafb; }

    .badge {
      padding: 3px 8px;
      border-radius: 999px;
      font-size: 12px;
      display: inline-block;
      border: 1px solid var(--border);
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

    <div class="card">
      <h2>Список пользователей</h2>

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
              <th>Действия</th>
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

                  {{-- 🖉 Кнопка редактирования — только иконка --}}
                  <a href="{{ route('admin.users.edit', $user->Id) }}"
                     class="icon-btn"
                     title="Редактировать">✎</a>

                  {{-- Блокировка / активация --}}
                  <form method="POST"
                        action="{{ route('admin.users.toggle', $user->Id) }}"
                        onsubmit="return confirm('Вы уверены?');">
                    @csrf
                    @method('PATCH')

                    @if($user->IsActive)
                      <button class="action-btn danger" type="submit">Заблокировать</button>
                    @else
                      <button class="action-btn" type="submit">Активировать</button>
                    @endif
                  </form>

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
