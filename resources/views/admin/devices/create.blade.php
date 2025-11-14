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
        <ul style="margin:0; padding-left:18px;">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="card">
      <h2>{{ $pageTitle }}</h2>

      <form method="POST"
            action="{{ $isEdit
                        ? route('admin.devices.update', ['type' => $type, 'id' => $device->id])
                        : route('admin.devices.store') }}">
        @csrf
        @if($isEdit)
          @method('PATCH')
        @endif

        <div class="form-grid">
          {{-- Тип устройства --}}
          <div class="form-row">
            <label for="device_type">Тип устройства</label>
            <select id="device_type" name="device_type" required {{ $isEdit ? 'disabled' : '' }}>
              <option value="controller" {{ $currentType === 'controller' ? 'selected' : '' }}>Контроллер</option>
              <option value="camera"     {{ $currentType === 'camera' ? 'selected' : '' }}>Камера</option>
            </select>
            @if($isEdit)
              <input type="hidden" name="device_type" value="{{ $currentType }}">
            @endif
            @error('device_type')
              <div class="form-error">{{ $message }}</div>
            @enderror
          </div>

          <div class="form-row-inline">
            {{-- IP --}}
            <div class="form-row">
              <label for="ip_address">IP адрес</label>
              <input type="text"
                     id="ip_address"
                     name="ip_address"
                     value="{{ old('ip_address', $isEdit ? $device->ip : '') }}"
                     required>
              @error('ip_address')
                <div class="form-error">{{ $message }}</div>
              @enderror
            </div>

            {{-- Порт --}}
            <div class="form-row">
              <label for="Port">Порт</label>
              <input type="number"
                     id="Port"
                     name="Port"
                     min="1"
                     max="65535"
                     value="{{ old('Port', $isEdit ? $device->port : '') }}"
                     required>
              @error('Port')
                <div class="form-error">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div class="form-row-inline">
            {{-- Логин --}}
            <div class="form-row">
              <label for="Login">Логин</label>
              <input type="text"
                     id="Login"
                     name="Login"
                     value="{{ old('Login', $isEdit ? $device->login : '') }}"
                     required>
              @error('Login')
                <div class="form-error">{{ $message }}</div>
              @enderror
            </div>

            {{-- Пароль --}}
            <div class="form-row">
              <label for="Password">Пароль</label>
              <input type="password"
                     id="Password"
                     name="Password"
                     value="">
              @error('Password')
                <div class="form-error">{{ $message }}</div>
              @enderror
            </div>
          </div>

          {{-- Локация --}}
          <div class="form-row">
            <label for="Location">Локация (описание)</label>
            <input type="text"
                   id="Location"
                   name="Location"
                   value="{{ old('Location', $isEdit ? $device->location : '') }}">
            @error('Location')
              <div class="form-error">{{ $message }}</div>
            @enderror
          </div>

          

          {{-- Направление (для камеры) --}}
          <div class="form-row" id="camera_direction_row">
            <label for="Direction">Направление (для камеры)</label>
            <select id="Direction" name="Direction">
              <option value="entry" {{ $currentDirection === 'entry' ? 'selected' : '' }}>Вход</option>
              <option value="exit"  {{ $currentDirection === 'exit'  ? 'selected' : '' }}>Выход</option>
              <option value="other" {{ $currentDirection === 'other' ? 'selected' : '' }}>Другое</option>
            </select>
            @error('Direction')
              <div class="form-error">{{ $message }}</div>
            @enderror
          </div>

          {{-- Активность --}}
          <div class="form-row">
            <div class="form-row-checkbox">
              <input type="checkbox"
                     id="IsActive"
                     name="IsActive"
                     value="1"
                     {{ $activeChecked ? 'checked' : '' }}>
              <label for="IsActive">Активно</label>
            </div>
            @error('IsActive')
              <div class="form-error">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <div class="form-actions">
          <a href="{{ route('admin.devices.index') }}" class="btn-secondary-small">Отмена</a>
          <button type="submit" class="btn-primary-small">
            {{ $isEdit ? 'Сохранить изменения' : 'Создать устройство' }}
          </button>
        </div>
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
