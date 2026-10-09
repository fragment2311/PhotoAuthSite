<!DOCTYPE html>
<html lang="ru">
<head>
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
  <meta http-equiv="Pragma" content="no-cache"/>
  <meta http-equiv="Expires" content="0"/>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Журнал логов</title>

  <style>
    *, *::before, *::after { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #f3f4f6;
      color: #111827;
    }

    .page {
      margin: 24px auto 24px;
      padding: 0 1rem;
      max-width: 1500px;
    }

    .card {
      background: #fff;
      border: 1px solid #e1e1e1;
      border-radius: 10px;
      padding: 1rem 1.2rem 1.4rem;
      box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }

    h1 { margin: 0 0 1rem; font-size: 1.8rem; }

    .filters {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
      align-items: flex-end;
      margin-bottom: 1rem;
    }

    .field { min-width: 220px; display: flex; flex-direction: column; gap: .35rem; }

    .field label { color: #374151; font-size: .95rem; }

    .field input:not(.dt-picker),
    .field select {
      font-size: .95rem;
      padding: .5rem .7rem;
      border: 1px solid #bbb;
      border-radius: 8px;
      background: #fff;
      width: 100%;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: .55rem .9rem;
      border-radius: 8px;
      border: 1px solid #2563eb;
      background: #2563eb;
      color: #fff;
      text-decoration: none;
      cursor: pointer;
      font-size: .95rem;
    }

    .btn-secondary {
      background: #fff;
      color: #2563eb;
    }

    .btn-test {
      background: #dc2626;
      border-color: #dc2626;
      color: #fff;
    }

    .btn-test:hover {
      background: #b91c1c;
      border-color: #b91c1c;
    }

    .alert-ok {
      margin: 0 0 .9rem;
      padding: .7rem .9rem;
      border-radius: 8px;
      border: 1px solid #bbf7d0;
      background: #f0fdf4;
      color: #166534;
      font-size: .92rem;
    }

    .alert-err {
      margin: 0 0 .9rem;
      padding: .7rem .9rem;
      border-radius: 8px;
      border: 1px solid #fecaca;
      background: #fef2f2;
      color: #991b1b;
      font-size: .92rem;
    }

    .table-wrap { overflow: auto; }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 1100px;
    }

    th, td {
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
      padding: .65rem .55rem;
      font-size: .92rem;
      vertical-align: top;
    }

    th.sortable-th {
      padding: 0;
    }

    .sort-link {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .45rem;
      width: 100%;
      padding: .65rem .55rem;
      color: inherit;
      text-decoration: none;
      user-select: none;
    }

    .sort-link:hover {
      background: #f8fafc;
    }

    .sort-arrow {
      color: #2563eb;
      font-size: .82rem;
      min-width: 1rem;
      text-align: right;
    }

    .badge {
      display: inline-flex;
      border-radius: 999px;
      padding: .2rem .6rem;
      font-size: .78rem;
      font-weight: 600;
      border: 1px solid transparent;
      text-transform: uppercase;
    }

    .badge-warning { background: #fff7ed; border-color: #fdba74; color: #9a3412; }
    .badge-error,
    .badge-fatal,
    .badge-emergency,
    .badge-alert,
    .badge-critical { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
    .badge-info,
    .badge-notice,
    .badge-debug { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }

    .message {
      white-space: pre-wrap;
      max-width: 560px;
      word-break: break-word;
    }

    details summary { cursor: pointer; color: #1d4ed8; }

    pre {
      margin: .5rem 0 0;
      padding: .6rem;
      border-radius: 8px;
      background: #111827;
      color: #e5e7eb;
      max-width: 520px;
      max-height: 220px;
      overflow: auto;
      white-space: pre-wrap;
      word-break: break-word;
      font-size: .82rem;
    }

    .empty {
      padding: 1.1rem;
      border: 1px dashed #d1d5db;
      border-radius: 10px;
      background: #fafafa;
      color: #6b7280;
      text-align: center;
    }
  </style>

  <link rel="stylesheet" href="{{ asset('css/header-buttons.css') }}">
  <link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/datetime-picker.css') }}">
</head>
<body>
  @include('layouts.header', ['headerTitle' => 'Журнал логов'])

  <div class="page">
    <div class="card">
      <h1>Журнал логов</h1>

      @if(session('ok'))
        <div class="alert-ok">{{ session('ok') }}</div>
      @endif

      @if(session('err'))
        <div class="alert-err">{{ session('err') }}</div>
      @endif

      <form method="GET" class="filters">
        <div class="field" style="min-width:180px;">
          <label for="level">Уровень</label>
          <select id="level" name="level">
            <option value="">Все</option>
            @foreach($levels as $level)
              <option value="{{ $level }}" @selected(request('level') === $level)>{{ strtoupper($level) }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label for="date_from">От (дата/время)</label>
          <input
            id="date_from"
            type="text"
            name="date_from"
            class="dt-picker"
            data-enable-time="1"
            data-date-format="Y-m-d H:i:S"
            value="{{ (string) request('date_from', '') }}"
            placeholder="YYYY-MM-DD HH:MM:SS"
            readonly
          >
        </div>

        <div class="field">
          <label for="date_to">До (дата/время)</label>
          <input
            id="date_to"
            type="text"
            name="date_to"
            class="dt-picker"
            data-enable-time="1"
            data-date-format="Y-m-d H:i:S"
            value="{{ (string) request('date_to', '') }}"
            placeholder="YYYY-MM-DD HH:MM:SS"
            readonly
          >
        </div>

        <div class="field" style="min-width:320px;">
          <label for="search">Поиск</label>
          <input id="search" type="text" name="search" value="{{ (string) request('search', '') }}" placeholder="Сообщение / детали">
        </div>

        <button type="submit" class="btn">Фильтровать</button>
        <a href="{{ route('admin.logs.index') }}" class="btn btn-secondary">Сброс</a>
      </form>


      @if($logs->isEmpty())
        <div class="empty">Логи не найдены по заданным фильтрам.</div>
      @else
        @php
          $currentSort = (string) ($currentSort ?? 'date');
          $currentDir = (string) ($currentDir ?? 'desc');
          $baseQuery = request()->except(['sort', 'dir', 'page']);

          $sortUrl = static function (string $key) use ($baseQuery, $currentSort, $currentDir): string {
              $nextDir = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';

              return route('admin.logs.index', array_merge($baseQuery, [
                  'sort' => $key,
                  'dir' => $nextDir,
              ]));
          };

          $sortArrow = static function (string $key) use ($currentSort, $currentDir): string {
              if ($currentSort !== $key) {
                  return '↕';
              }

              return $currentDir === 'asc' ? '↑' : '↓';
          };
        @endphp

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th data-sortable="false" class="sortable-th">
                  <a href="{{ $sortUrl('id') }}" class="sort-link">
                    <span>ID</span>
                    <span class="sort-arrow">{{ $sortArrow('id') }}</span>
                  </a>
                </th>
                <th data-sortable="false" class="sortable-th">
                  <a href="{{ $sortUrl('date') }}" class="sort-link">
                    <span>Дата</span>
                    <span class="sort-arrow">{{ $sortArrow('date') }}</span>
                  </a>
                </th>
                <th data-sortable="false" class="sortable-th">
                  <a href="{{ $sortUrl('level') }}" class="sort-link">
                    <span>Уровень</span>
                    <span class="sort-arrow">{{ $sortArrow('level') }}</span>
                  </a>
                </th>
                <th data-sortable="false" class="sortable-th">
                  <a href="{{ $sortUrl('message') }}" class="sort-link">
                    <span>Сообщение</span>
                    <span class="sort-arrow">{{ $sortArrow('message') }}</span>
                  </a>
                </th>
                <th data-sortable="false" class="sortable-th">
                  <a href="{{ $sortUrl('ip') }}" class="sort-link">
                    <span>IP</span>
                    <span class="sort-arrow">{{ $sortArrow('ip') }}</span>
                  </a>
                </th>
              </tr>
            </thead>
            <tbody>
              @foreach($logs as $log)
                @php
                  $logType = strtolower((string) $log->LogType);
                  $badgeClass = in_array($logType, ['critical', 'fatal', 'error', 'alert', 'emergency'], true)
                    ? 'badge-error'
                    : (in_array($logType, ['info', 'notice', 'debug'], true) ? 'badge-info' : 'badge-warning');
                @endphp
                <tr>
                  <td>{{ $log->Id }}</td>
                  <td>{{ $log->EventDate ? $log->EventDate->format('d.m.Y H:i:s') : '—' }}</td>
                  <td><span class="badge {{ $badgeClass }}">{{ $log->LogType }}</span></td>
                  <td><div class="message">{{ $log->Message }}</div></td>
                  <td>{{ $log->IPAddress ?: '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="passage-pagination" style="margin-top:12px;">
          {{ $logs->onEachSide(1)->links('pagination.passages') }}
        </div>
      @endif
    </div>
  </div>

  <script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>
  <script src="{{ asset('js/datetime-picker.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      initDateTimePickers('.dt-picker');
    });
  </script>
</body>
</html>
