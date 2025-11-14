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
      font-size: 2rem; margin: 0;
    }
    .btn {
      padding: 0.6rem 1.2rem;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      color: #fff;
      font-size: 1rem;
      margin-left: .5rem;
      transition: background .2s;
      display: inline-block;
    }
    .btn-primary {
      background: var(--primary);
    }
    .btn-primary:hover {
      background: var(--primary-hover);
    }
    .btn-secondary {
      background: var(--secondary);
    }
    .btn-secondary:hover {
      background: var(--secondary-hover);
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
      width: 100%;
      max-width: 800px;
      margin: 1rem auto;
      padding: 0 2rem;
    }
    .nav-back {
      display: inline-block;
      margin-bottom: 1rem;
    }
    .form-card {
      background: var(--card);
      border-radius: 6px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      padding: 2rem;
    }
    .form-card h2 {
      margin-top: 0; margin-bottom: 1.5rem;
      font-size: 1.5rem; text-align: center;
    }
    .form-field {
      width: 100%;
      font-size: 1.1rem;
      padding: 0.8rem 1rem;
      border: 1px solid #bbb;
      border-radius: 6px;
      margin-bottom: 1rem;
    }
    .status {
      color: var(--success);
      text-align: center;
      margin-bottom: 1rem;
      font-size: 1.2rem;
    }
    .error {
      color: var(--danger);
      text-align: center;
      margin-bottom: 1rem;
      font-size: 1rem;
    }
  </style>
</head>
<body>

@php use Illuminate\Support\Facades\Auth; @endphp

<header>
    <h1>
        <a href="{{ route('gallery',['dir'=>'entrance_photos']) }}"
           style="text-decoration:none;color:inherit;">
            Галерея</a>
    </h1>
    <div>
        @if(Auth::check() && Auth::user()->role->NameRole==='Администратор')
            <a href="{{ route('admin.users') }}" class="btn btn-primary">Список пользователей</a>
            <a href="{{ route('admin.register') }}" class="btn btn-primary">Новый пользователь</a>
        @endif
        <a href="{{ route('gallery',['dir'=>'entrance_photos']) }}" class="btn btn-primary">В галерею</a>
        <form method="POST" action="{{ route('logout') }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-danger">Выйти</button>
        </form>
    </div>
</header>

<div class="container">
  <a href="{{ route('admin.users') }}" class="btn btn-primary nav-back">← Назад к списку</a>

  <div class="form-card">
    <h2>Новый пользователь</h2>

    @if(session('status'))
      <div class="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
      <div class="error">
        @foreach($errors->all() as $e)
          <div>{{ $e }}</div>
        @endforeach
      </div>
    @endif

    <form method="POST" action="{{ route('admin.register.post') }}">
      @csrf

      <input type="text"
             name="login"
             class="form-field"
             placeholder="Логин"
             value="{{ old('login') }}"
             required autofocus>

      <input type="password"
             name="password"
             class="form-field"
             placeholder="Пароль"
             required>

      <input type="password"
             name="password_confirmation"
             class="form-field"
             placeholder="Повторите пароль"
             required>

      <select name="role_id" class="form-field" required>
        <option value="">— Выберите роль —</option>
        @foreach($roles as $role)
          <option value="{{ $role->ID }}" @selected(old('role_id') == $role->ID)>
            {{ $role->NameRole }}
          </option>
        @endforeach
      </select>

      <button type="submit" class="btn btn-success" style="width:100%; margin-top:0.5rem;">
        Создать
      </button>
    </form>
  </div>
</div>

</body>
</html>
