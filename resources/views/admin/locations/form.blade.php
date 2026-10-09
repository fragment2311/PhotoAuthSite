<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>{{ $location ? 'Редактировать локацию' : 'Создать локацию' }}</title>
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
      --danger-bg: #dc2626;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
      background: var(--bg);
      color: var(--text);
    }

    .wrap {
      max-width: 900px;
      margin: 120px auto 40px;
      padding: 0 16px;
    }

    .card {
      background: var(--card);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 2px 6px rgba(0,0,0,.04);
    }

    .card h2 {
      margin: 0 0 14px;
      font-size: 20px;
    }

    .alert {
      padding: 10px 12px;
      border-radius: 10px;
      margin-bottom: 16px;
      font-size: 14px;
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #991b1b;
    }

    .form-group {
      margin-bottom: 14px;
    }

    label {
      display: block;
      margin-bottom: 6px;
      font-size: 13px;
      color: #4b5563;
    }

    input[type="text"] {
      width: 100%;
      padding: 9px 10px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      background: #f9fafb;
      outline: none;
    }

    input[type="text"]:focus {
      border-color: #2563eb;
      background: #ffffff;
      box-shadow: 0 0 0 1px rgba(37,99,235,0.15);
    }

    input[type="date"] {
      width: 100%;
      padding: 9px 10px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      background: #f9fafb;
      outline: none;
    }

    input[type="date"]:focus {
      border-color: #2563eb;
      background: #ffffff;
      box-shadow: 0 0 0 1px rgba(37,99,235,0.15);
    }

    .form-actions {
      margin-top: 18px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      border: none;
      border-radius: 999px;
      padding: 8px 16px;
      font-size: 14px;
      cursor: pointer;
    }

    .btn-primary {
      background: var(--primary-bg);
      color: #fff;
    }

    .btn-primary:hover { background: var(--link-hover); }

    .btn-secondary {
      background: #e5e7eb;
      color: #111827;
      border: 1px solid #d1d5db;
    }

    .btn-secondary:hover { background: #d1d5db; }

    .last-change-inline {
      margin-top: 0;
      margin-bottom: 10px;
      font-size: 13px;
      color: #4b5563;
      text-align: right;
      line-height: 1.35;
    }

    @media (max-width: 640px) {
      .last-change-inline { text-align: left; }
    }
  </style>

  <link rel="stylesheet" href="{{ asset('css/header-buttons.css') }}">
</head>
<body>
@include('layouts.header', ['headerTitle' => 'Галерея-Проходы'])

<div class="wrap">
  @if($errors->any())
    <div class="alert">
      <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card">
    <h2>{{ $pageTitle ?? ($location ? 'Редактировать локацию' : 'Создать локацию') }}</h2>

    <form method="POST"
          action="{{ $location
              ? route('admin.locations.update', ['location' => $location->IdLocation])
              : route('admin.locations.store') }}">
      @csrf
      @if($location)
        @method('PUT')
      @endif

      <div class="form-group">
        <label for="LocationDate">Дата</label>
        <input type="date"
               id="LocationDate"
               name="LocationDate"
               value="{{ old('LocationDate', isset($location) && $location && $location->LocationDate ? $location->LocationDate->format('Y-m-d') : now()->format('Y-m-d')) }}"
               required>
      </div>

      <div class="form-group">
        <label for="LocationName">Название локации (латинницей)</label>
        <input type="text"
               id="LocationName"
               name="LocationName"
               maxlength="150"
               value="{{ old('LocationName', $location->LocationName ?? '') }}"
               required>
      </div>

      @if($location)
        @include('partials.last_change_card', ['lastChange' => $lastChange ?? null])
      @endif

      <div class="form-actions">
        <a href="{{ route('admin.locations.index') }}" class="btn btn-secondary">К списку</a>
        <button type="submit" class="btn btn-primary">{{ $location ? 'Сохранить' : 'Создать' }}</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
