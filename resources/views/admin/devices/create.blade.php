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

/* Кнопка */
button {
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
button:hover {
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

    header {
      background: white;
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
    .btn-primary:hover { background: var(--link-hover); }

    .btn-danger { background: var(--danger-bg); border-color: var(--danger-bg); }
    .btn-danger:hover { background: #b91c1c; }

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
    input[type="number"],
    input[type="password"],
    select {
      padding: 7px 9px;
      border-radius: 8px;
      border: 1px solid var(--border);
      font-size: 14px;
      outline: none;
      background: #f9fafb;
    }

    input[type="text"]:focus,
    input[type="number"]:focus,
    input[type="password"]:focus,
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

    @media (max-width: 640px) {
      .form-row-inline { flex-direction: column; }
      header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
      header .btn-group { flex-wrap: wrap; }
    }
  </style>
</head>
<body>
  @php
    $galleryRootDir = trim((string) config('app.gallery_root_dir', config('app.gallery_root_dir_default', '')), '/');
  @endphp

  <header>
    <h1>
      <a href="{{ route('gallery',['dir'=>$galleryRootDir]) }}"
         style="text-decoration:none;color:inherit;">
        Галерея-Устройства
      </a>
    </h1>
    <div class="btn-group">
      <a href="{{ route('gallery', ['dir' => $galleryRootDir]) }}" class="btn btn-primary">В галерею</a>
      <a href="{{ route('admin.devices.index') }}" class="btn btn-primary">Устройства</a>
      <a href="{{ route('admin.users') }}" class="btn btn-primary">Список пользователей</a>
      <a href="{{ route('admin.register') }}" class="btn btn-primary">Новый пользователь</a>
      <form method="POST" action="{{ route('logout') }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-danger">Выйти</button>
      </form>
    </div>
  </header>

  @php
      /** @var \App\Models\ControllerDevice|\App\Models\Camera|null $device */
      $isEdit = isset($device) && $device !== null;
      $currentType = old('device_type', $isEdit ? ($type ?? 'controller') : 'controller');
      $pageTitle = $isEdit ? 'Редактировать устройство' : 'Добавить устройство';
      $currentDirection = old(
          'Direction',
          $isEdit ? ($device->direction ?? 'entry') : 'entry'
      );
      $activeChecked = old(
          'IsActive',
          $isEdit ? (int)($device->active ?? 1) : 1
      );
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

        <form method="POST"
              action="{{ isset($device)
                    ? route('admin.devices.update', ['type' => $type, 'id' => $device->id])
                    : route('admin.devices.store') }}">

            @csrf
            @if(isset($device))
                @method('PUT')
            @endif

            @if(!isset($device))
                <div class="form-group">
                    <label for="device_type">Тип устройства</label>
                    <select id="device_type" name="device_type" required>
                        <option value="controller" {{ old('device_type') === 'controller' ? 'selected' : '' }}>Контроллер</option>
                        <option value="camera" {{ old('device_type') === 'camera' ? 'selected' : '' }}>Камера</option>
                    </select>
                </div>
            @else
                <div class="form-group">
                    <label>Тип устройства</label>
                    <p class="muted-text" style="margin: 0;">
                        {{ $type === 'controller' ? 'Контроллер' : 'Камера' }}
                    </p>
                    <input type="hidden" name="device_type" value="{{ $type }}">
                </div>
            @endif

            <div class="form-group">
                <label for="ip_address">IP адрес</label>
                <input type="text" id="ip_address" name="ip_address"
                       value="{{ old('ip_address', $device->ip ?? '') }}" required>
            </div>

            <div class="form-group">
                <label for="Port">Порт</label>
                <input type="number" id="Port" name="Port"
                       value="{{ old('Port', $device->port ?? 80) }}"
                       min="1" max="65535" required>
            </div>

            <div class="form-group">
                <label for="Login">Логин</label>
                <input type="text" id="Login" name="Login"
                       value="{{ old('Login', $device->login ?? '') }}" maxlength="100" required>
            </div>

            <div class="form-group">
                <label for="Password">
                    Пароль
                    @if(isset($device))
                        <span class="muted-text">(оставьте пустым, чтобы не менять)</span>
                    @endif
                </label>
                <input type="password" id="Password" name="Password" maxlength="255">
            </div>

            <div class="form-group">
                <label for="Location">Расположение</label>
                <input type="text" id="Location" name="Location"
                       value="{{ old('Location', $device->location ?? '') }}" maxlength="255">
            </div>

            @php
                $isActive = old('IsActive', $device->active ?? 1);
            @endphp
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="IsActive" value="1" {{ $isActive ? 'checked' : '' }}>
                    Активно
                </label>
            </div>

            {{-- Только для камер --}}
            @php
                $deviceType = old('device_type', $type ?? 'controller');
                $direction  = old('Direction', $device->direction ?? 'entry');
            @endphp

            <div id="camera-direction-block"
                 class="form-group"
                 style="{{ $deviceType === 'camera' ? '' : 'display:none' }}">
                <label for="Direction">Направление (для камеры)</label>
                <select id="Direction" name="Direction">
                    <option value="entry" {{ $direction === 'entry' ? 'selected' : '' }}>Вход</option>
                    <option value="exit"  {{ $direction === 'exit' ? 'selected' : '' }}>Выход</option>
                    <option value="other" {{ $direction === 'other' ? 'selected' : '' }}>Другое</option>
                </select>
            </div>

            <button type="submit">
                {{ isset($device) ? 'Сохранить' : 'Создать' }}
            </button>
        </form>
    </div>
</div>


  <script>
    (function () {
      var typeSelect = document.getElementById('device_type');
      var dirRow = document.getElementById('camera_direction_row');

      function updateDirectionVisibility() {
        if (!typeSelect || !dirRow) return;
        var val = typeSelect.value;
        dirRow.style.display = val === 'camera' ? 'block' : 'none';
      }

      if (typeSelect) {
        typeSelect.addEventListener('change', updateDirectionVisibility);
        updateDirectionVisibility();
      }
    })();
  </script>
</body>
</html>
