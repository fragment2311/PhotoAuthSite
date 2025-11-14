<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>{{ $title ?? 'Редактирование устройства' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <style>
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 0; background: #f6f7fb; }
    .wrap { max-width: 900px; margin: 40px auto; padding: 0 16px; }
    h1 { font-size: 24px; margin-bottom: 16px; }
    .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(15,23,42,0.08); }
    .form-row { margin-bottom: 12px; }
    .form-row label { display: block; font-size: 14px; margin-bottom: 4px; }
    .form-row input, .form-row select, .form-row textarea {
      width: 100%; padding: 8px 10px; border-radius: 8px;
      border: 1px solid #e5e7eb; font-size: 14px; box-sizing: border-box;
    }
    .form-row input[type="checkbox"] { width: auto; }
    .form-actions { margin-top: 18px; display: flex; gap: 12px; }
    .btn {
      display: inline-flex; align-items: center; justify-content: center;
      padding: 8px 16px; border-radius: 999px; border: none;
      font-size: 14px; cursor: pointer; text-decoration: none;
    }
    .btn-primary { background: #2563eb; color: #fff; }
    .btn-secondary { background: #e5e7eb; color: #111827; }
    .error { color: #dc2626; font-size: 12px; margin-top: 2px; }
    .badge-type { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; background: #e5e7eb; }
  </style>
</head>
<body>
  <div class="wrap">
    <div style="margin-bottom: 16px;">
      <a href="{{ route('admin.devices.index') }}" class="btn btn-secondary">← Назад к устройствам</a>
    </div>

    <div class="card">
      <h1>{{ $title ?? 'Редактирование устройства' }}</h1>
      <p>
        Тип:
        <span class="badge-type">
          {{ $type === 'controller' ? 'Контроллер' : 'Камера' }}
        </span>
      </p>

      <form method="POST"
            action="{{ route('admin.devices.update', ['type' => $type, 'id' => $device->id]) }}">
        @csrf
        @method('PATCH')

        <div class="form-row">
          <label for="ip_address">IP-адрес</label>
          <input id="ip_address" name="ip_address" type="text"
                 value="{{ old('ip_address', $device->ip ?? $device->ip_address) }}">
          @error('ip_address') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <label for="Port">Порт</label>
          <input id="Port" name="Port" type="number"
                 value="{{ old('Port', $device->port ?? $device->Port) }}">
          @error('Port') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <label for="Login">Логин</label>
          <input id="Login" name="Login" type="text"
                 value="{{ old('Login', $device->login ?? $device->Login) }}">
          @error('Login') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <label for="Password">Пароль</label>
          <input id="Password" name="Password" type="text"
                 value="{{ old('Password', $device->Password ?? '') }}">
          @error('Password') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <label for="Location">Локация</label>
          <input id="Location" name="Location" type="text"
                 value="{{ old('Location', $device->location ?? $device->Location) }}">
          @error('Location') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-row">
          <label for="comment">Комментарий</label>
          <textarea id="comment" name="comment" rows="2">{{ old('comment', $device->comment ?? '') }}</textarea>
          @error('comment') <div class="error">{{ $message }}</div> @enderror
        </div>

        @if($type === 'camera')
          <div class="form-row">
            <label for="Direction">Направление</label>
            <select id="Direction" name="Direction">
              @php
                $dir = old('Direction', $device->Direction ?? $device->direction ?? null);
              @endphp
              <option value="entry" {{ $dir === 'entry' ? 'selected' : '' }}>Вход</option>
              <option value="exit"  {{ $dir === 'exit'  ? 'selected' : '' }}>Выход</option>
            </select>
            @error('Direction') <div class="error">{{ $message }}</div> @enderror
          </div>
        @endif

        <div class="form-row">
          <label>
            <input type="checkbox" name="IsActive" value="1"
                   {{ old('IsActive', (int)($device->active ?? 0)) ? 'checked' : '' }}>
            Активно
          </label>
          @error('IsActive') <div class="error">{{ $message }}</div> @enderror
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Сохранить</button>
          <a href="{{ route('admin.devices.index') }}" class="btn btn-secondary">Отмена</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
