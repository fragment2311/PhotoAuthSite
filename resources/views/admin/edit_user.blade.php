<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Редактировать пользователя</title>
  <style>
    :root {
      --bg: #f9f9f9;
      --card: #fff;
      --text: #333;
      --link: #007bff;
      --link-hover: #0056b3;
      --success: #28a745;
      --success-hover: #218838;
      --danger: #dc3545;
      --danger-hover: #c82333;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      font-size: 22px;
    }
    .btn {
      padding: 0.7rem 1.4rem;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      text-align: center;
      font-size: 1.1rem;
      margin-left: .5rem;
      transition: background .2s;
      color: #fff;
    }
    .user-form-container .btn { margin-left: 0; }
    .btn-primary {
      background: var(--link);
    }
    .btn-primary:hover {
      background: var(--link-hover);
    }
    .btn-success {
      background: var(--success);
      width: 120px;
    }
    .btn-success:hover {
      background: var(--success-hover);
    }
    .btn-danger {
      background: var(--danger);
    }
    .btn-danger:hover {
      background: var(--danger-hover);
    }
    .user-form-container {
      width: min(100%, 760px);
      margin: 2rem auto;
      padding: 1rem 2rem;
      background: var(--card);
      border-radius: 6px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      position: relative;
    }
    form div { margin-bottom: 1rem; }
    label {
      display: block;
      margin-bottom: .3rem;
      font-weight: 600;
      font-size: 1rem;
    }
    input, select {
      width: 100%;
      padding: .6rem;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 1rem;
    }
    .status {
      color: var(--success);
      margin-bottom: 1rem;
      font-size: 1rem;
      text-align: center;
    }
    .error {
      color: var(--danger);
      font-size: .9rem;
      margin-top: .3rem;
    }

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
  @php use Illuminate\Support\Facades\Auth; @endphp
  @php
    $galleryRootDir = trim((string) config('app.gallery_root_dir', config('app.gallery_root_dir_default', '')), '/');
  @endphp

  @include('layouts.header', ['headerTitle' => 'FTP-Галерея'])

  <div class="user-form-container">
    @if(session('status'))
      <div class="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.users.update', $user->Id) }}">
      @csrf
      @method('PATCH')

      <div>
        <label for="login">Логин</label>
        <input type="text" id="login" name="login" value="{{ old('login', $user->Login) }}">
        @error('login')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div>
        <label for="role_id">Роль</label>
        <select name="role_id" id="role_id">
          @foreach($roles as $role)
            <option value="{{ $role->ID }}"
              @selected(old('role_id', $user->IdRole) == $role->ID)>
              {{ $role->NameRole }}
            </option>
          @endforeach
        </select>
        @error('role_id')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div>
        <label for="password">Новый пароль (необязательно)</label>
        <input type="password" id="password" name="password">
        @error('password')<div class="error">{{ $message }}</div>@enderror
      </div>

      <div>
        <label for="password_confirmation">Подтверждение пароля</label>
        <input type="password" id="password_confirmation" name="password_confirmation">
      </div>

          @include('partials.last_change_card', ['lastChange' => $lastChange ?? null])
      <button type="submit" class="btn btn-success">Сохранить</button>
    </form>


  </div>
</body>
</html>
