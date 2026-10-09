<!DOCTYPE html>
<html lang="ru">
<head>
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
  <meta http-equiv="Pragma" content="no-cache"/>
  <meta http-equiv="Expires" content="0"/>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Создать пользователя</title>
  <style>
    :root {
      --bg: #f9f9f9;
      --card: #fff;
      --text: #333;
      --primary: #007bff;
      --primary-hover: #0056b3;
      --secondary: #6c757d;
      --secondary-hover: #5a6268;
      --success: #28a745;
      --success-hover: #218838;
      --danger: #dc3545;
      --danger-hover: #c82333;
    }
    * { box-sizing: border-box; }

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
    .btn-success {
      background: var(--success);
      width: 120px;
    }
    .btn-success:hover {
      background: var(--success-hover);
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
    .error-list {
      margin-bottom: 1rem;
    }
  </style>
  <link rel="stylesheet"
      href="{{ asset('css/header-buttons.css') }}?v={{ filemtime(public_path('css/header-buttons.css')) }}">

</head>
<body>

@php use Illuminate\Support\Facades\Auth; @endphp

@include('layouts.header', ['headerTitle' => 'FTP-Галерея'])


<div class="user-form-container">
  @if(session('status'))
    <div class="status">{{ session('status') }}</div>
  @endif

  @if($errors->any())
    <div class="error-list">
      @foreach($errors->all() as $e)
        <div class="error">{{ $e }}</div>
      @endforeach
    </div>
  @endif

  <form method="POST" action="{{ route('admin.register.post') }}">
    @csrf

    <div>
      <label for="login">Логин</label>
      <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus>
      @error('login')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div>
      <label for="role_id">Роль</label>
      <select name="role_id" id="role_id" required>
        <option value="">— Выберите роль —</option>
        @foreach($roles as $role)
          <option value="{{ $role->ID }}" @selected(old('role_id') == $role->ID)>
            {{ $role->NameRole }}
          </option>
        @endforeach
      </select>
      @error('role_id')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div>
      <label for="password">Пароль</label>
      <input type="password" id="password" name="password" required>
      @error('password')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div>
      <label for="password_confirmation">Подтверждение пароля</label>
      <input type="password" id="password_confirmation" name="password_confirmation" required>
    </div>

    <button type="submit" class="btn btn-success">Создать</button>
  </form>
</div>

</body>
</html>
