<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Локации</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    :root {
      --bg: #f6f7fb;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --border: #e5e7eb;
      --primary-bg: #2563eb;
      --link-hover: #1d4ed8;
      --ok: #16a34a;
      --no: #dc2626;
    }

    * { box-sizing: border-box; }

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

    .sheet-head {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 14px;
    }

    .action-btn {
      background: var(--primary-bg);
      color: white;
      padding: 10px 18px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 15px;
      transition: background .2s;
    }

    .action-btn:hover { background: var(--link-hover); }

    .sheet {
      background: var(--card);
      border-radius: 16px;
      padding: 18px;
      box-shadow: 0 2px 6px rgba(0,0,0,.04);
    }

    .sheet-title {
      margin: 0 0 14px;
      font-size: 20px;
    }

    .table-wrap { overflow: auto; }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 560px;
    }

    th, td {
      border-bottom: 1px solid var(--border);
      text-align: left;
      padding: 10px 12px;
      font-size: 14px;
    }

    tbody tr {
      cursor: pointer;
      transition: background-color .15s ease;
    }

    tbody tr:hover {
      background: #f8fafc;
    }

    .empty {
      padding: 20px;
      border-radius: 12px;
      border: 1px dashed var(--border);
      text-align: center;
      background: #fafafa;
      color: var(--muted);
    }
  </style>

  <link rel="stylesheet" href="{{ asset('css/spreadsheet.css') }}">
  <link rel="stylesheet" href="{{ asset('css/header-buttons.css') }}">
</head>
<body>
@include('layouts.header', ['headerTitle' => 'Галерея-Проходы'])

<div class="wrap">
  <div class="sheet">
    <div class="sheet-head">
      
      <h2 class="sheet-title">Список локаций</h2>
      <a href="{{ route('admin.locations.create') }}" class="action-btn">Создать</a>
    </div>

    @if($locations->isEmpty())
      <div class="empty">Локации не найдены.</div>
    @else
      <div class="table-wrap">
        <table>
          <thead>
          <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Дата</th>
          </tr>
          </thead>
          <tbody>
          @foreach($locations as $location)
            <tr onclick="window.location='{{ route('admin.locations.edit', ['location' => $location->IdLocation]) }}'">
              <td>{{ $location->IdLocation }}</td>
              <td>{{ $location->LocationName }}</td>
              <td>{{ $location->LocationDate ? $location->LocationDate->format('d.m.Y') : '—' }}</td>
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
