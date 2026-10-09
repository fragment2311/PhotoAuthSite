<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Пары устройств</title>
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

/* Общие стили для формы */
.form-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
    background-color: var(--card);
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Заголовок формы */
h2 {
    text-align: center;
    color: var(--text);
    font-size: 24px;
    margin-bottom: 20px;
}

/* Стили для полей ввода и выпадающих списков */
input[type="text"], select, textarea {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid var(--border);
    border-radius: 5px;
    font-size: 16px;
    transition: all 0.3s ease-in-out;
}

input[type="text"]:focus, select:focus, textarea:focus {
    border-color: var(--primary-bg);
    outline: none;
}

/* Стилизация чекбокса */
input[type="checkbox"] {
    margin-right: 10px;
}

/* Кнопка отправки формы */
.main-submit-btn {
    width: 100%;
    padding: 12px;
    background-color: var(--primary-bg);
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 18px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

/* Наведение на кнопку */
.main-submit-btn:hover {
    background-color: var(--link-hover);
}

/* Стилизация контейнера для элементов формы */
.form-group {
    margin-bottom: 20px;
}

/* Отступы и выравнивание для меток */
label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: var(--text);
}

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial,
        "Apple Color Emoji","Segoe UI Emoji";
      background: var(--bg);
      color: var(--text);
    }

    .wrap {
      max-width: 1100px;
      margin: 20px auto 40px;
      padding: 0 16px 40px;
    }

    .card {
      background: var(--card);
      border-radius: 16px;
      padding: 24px 24px 20px;
      margin-bottom: 24px;
      box-shadow: 0 2px 6px rgba(0,0,0,.04);
      position: relative;
    }

    .card h2 { font-size: 20px; margin: 0 0 12px; }

    .alert {
      padding: 10px 12px;
      border-radius: 10px;
      margin-bottom: 16px;
      font-size: 14px;
    }

    .alert-ok {
      background: #ecfdf5;
      border: 1px solid #bbf7d0;
      color: #166534;
    }

    .alert-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #991b1b;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
    }

    .form-row {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .form-row-inline {
      display: flex;
      gap: 12px;
    }

    .form-row-inline > .form-row { flex: 1; }

    label {
      font-size: 13px;
      color: #4b5563;
    }

    input[type="text"],
    select {
      padding: 7px 9px;
      border-radius: 8px;
      border: 1px solid var(--border);
      font-size: 14px;
      outline: none;
      background: #f9fafb;
    }

    input[type="text"]:focus,
    select:focus {
      border-color: var(--link);
      background: #ffffff;
      box-shadow: 0 0 0 1px rgba(37,99,235,0.15);
    }

    .form-row-checkbox {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 4px;
    }

    .form-row-checkbox label { margin: 0; }

    .form-error {
      font-size: 12px;
      color: #b91c1c;
    }

    .form-actions {
      margin-top: 18px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn-primary-small {
      background: var(--primary-bg);
      color: white;
      border-radius: 999px;
      padding: 8px 18px;
      border: none;
      font-size: 14px;
      cursor: pointer;
    }

    .btn-primary-small:hover { background: var(--link-hover); }

    .btn-secondary-small {
      background: #e5e7eb;
      color: #111827;
      border-radius: 999px;
      padding: 8px 16px;
      border: 1px solid #d1d5db;
      font-size: 14px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn-secondary-small:hover { background: #d1d5db; }

    .last-change-inline {
      margin-top: 0;
      margin-bottom: 10px;
      font-size: 13px;
      color: #4b5563;
      text-align: right;
      line-height: 1.35;
    }

    @media (max-width: 640px) {
      .form-row-inline { flex-direction: column; }
      .last-change-inline { text-align: left; }
    }
  </style>
  <link rel="stylesheet" href="{{ asset('css/header-buttons.css') }}">
</head>
<body>
  @php
    $galleryRootDir = trim((string) config('app.gallery_root_dir', config('app.gallery_root_dir_default', '')), '/');
  @endphp

  @include('layouts.header', ['headerTitle' => 'Галерея-Пары устройств'])

  @php
      /** @var \App\Models\PairDevice|null $pair */
      $isEdit    = isset($pair) && $pair !== null;
      $pageTitle = $isEdit ? 'Редактировать пару устройств' : 'Создать пару устройств';
      $currentName          = old('Name', $isEdit ? $pair->Name : '');
      $currentComment       = old('Comment',         $isEdit ? ($pair->Comment ?? '') : '');
      $activeChecked        = old('IsActive',        $isEdit ? (int)($pair->IsActive ?? 1) : 1);
  @endphp

  <div class="wrap">
    @if(session('status'))
        <div class="alert alert-ok">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <h2>{{ $pageTitle }}</h2>

        <form method="POST" novalidate action="{{ isset($pair) ? route('admin.device_pairs.update', $pair->id) : route('admin.device_pairs.store') }}">
            @csrf
            @if(isset($pair))
                @method('PUT')
            @endif

            <div class="form-group">
                <label for="Name">Название пары</label>
                <input type="text" id="Name" name="Name" value="{{ old('Name', $pair->name ?? '') }}" required maxlength="100">
            </div>

            <div class="form-group">
                <label for="location_id">Точка (локация)</label>
                <select id="location_id" name="location_id" required>
                    <option value="">-- выберите точку --</option>
                    @foreach(($locations ?? []) as $location)
                        <option value="{{ $location->IdLocation }}"
                            {{ (int) old('location_id', $pair->Location ?? 0) === (int) $location->IdLocation ? 'selected' : '' }}>
                            {{ $location->LocationName ?? ('Точка ' . $location->IdLocation) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="Comment">Комментарий</label>
                <input type="text" id="Comment" name="Comment" value="{{ old('Comment', $pair->Comment ?? '') }}" maxlength="255">
            </div>

            <hr style="margin:20px 0; border:none; border-top:1px solid #e5e7eb;">
            <h3 style="margin:0 0 12px; font-size:18px;">Связи пары</h3>

            <div class="form-row-inline">
              <div class="form-row">
                <label for="controller_id">Контроллер</label>
                <select id="controller_id" name="controller_id" required>
                  <option value="">-- выберите контроллер --</option>
                  @foreach(($controllers ?? []) as $controller)
                    <option value="{{ $controller->id }}" {{ (int) old('controller_id', $pairRelations['controller_id'] ?? 0) === (int) $controller->id ? 'selected' : '' }}>
                      {{ ($controller->ip ?? $controller->ip_address ?? 'Контроллер') . ':' . ($controller->port ?? '—') }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="form-row-inline" style="margin-top:12px;">
              <div class="form-row">
                <label for="entry_camera_id">Камера входа</label>
                <select id="entry_camera_id" name="entry_camera_id">
                  <option value="">-- не выбрана --</option>
                  @foreach(($cameras ?? []) as $camera)
                    <option value="{{ $camera->id }}" {{ (int) old('entry_camera_id', $pairRelations['entry_camera_id'] ?? 0) === (int) $camera->id ? 'selected' : '' }}>
                      {{ ($camera->ip ?? $camera->ip_address ?? 'Камера') . ':' . ($camera->port ?? '—') }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-row">
                <label for="exit_camera_id">Камера выхода</label>
                <select id="exit_camera_id" name="exit_camera_id">
                  <option value="">-- не выбрана --</option>
                  @foreach(($cameras ?? []) as $camera)
                    <option value="{{ $camera->id }}" {{ (int) old('exit_camera_id', $pairRelations['exit_camera_id'] ?? 0) === (int) $camera->id ? 'selected' : '' }}>
                      {{ ($camera->ip ?? $camera->ip_address ?? 'Камера') . ':' . ($camera->port ?? '—') }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            @php
                $isActive = old('IsActive', $pair->IsActive ?? 1);
            @endphp
            <div class="form-group">
                <label>
                    <input type="checkbox" name="IsActive" value="1" {{ $isActive ? 'checked' : '' }}>
                    Пара активна
                </label>
            </div>

                    @if($isEdit)
            @include('partials.last_change_card', ['lastChange' => $lastChange ?? null])
        @endif

            <button type="submit" class="main-submit-btn">{{ isset($pair) ? 'Сохранить' : 'Создать' }}</button>
        </form>

    </div>
</div>

</body>
</html>
