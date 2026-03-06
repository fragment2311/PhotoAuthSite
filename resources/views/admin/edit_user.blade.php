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
    header {
      background: var(--card);
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    header h1 {
      font-size: 2rem;
      margin: 0;
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
    .btn-primary {
      background: var(--link);
    }
    .btn-primary:hover {
      background: var(--link-hover);
    }
    .btn-success {
      background: var(--success);
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
    .container {
      max-width: 600px;
      margin: 2rem auto;
      padding: 0 2rem;
      background: var(--card);
      border-radius: 6px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
  </style>
</head>
<body>
  @php use Illuminate\Support\Facades\Auth; @endphp
  @php
    $galleryRootDir = trim((string) config('app.gallery_root_dir', config('app.gallery_root_dir_default', '')), '/');
  @endphp

  <header>
    <h1>
      <a href="{{ route('gallery', ['dir' => $galleryRootDir]) }}"
         style="text-decoration:none;color:inherit;">
        FTP-Галерея
      </a>
    </h1>
    <div>
      <a href="{{ route('gallery', ['dir' => $galleryRootDir]) }}" class="btn btn-primary">Галерея</a>
      <a href="{{ route('admin.users') }}" class="btn btn-primary">К списку</a>
      <form method="POST" action="{{ route('logout') }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-danger">Выйти</button>
      </form>
    </div>
  </header>

  <div class="container">
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

      <button type="submit" class="btn btn-success">Сохранить</button>
    </form>
  </div>
</body>
</html>
