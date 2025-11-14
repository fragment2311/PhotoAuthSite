<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','FTP-Галерея')</title>

  <!-- Подключаем общий CSS -->
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
  <header class="header">
    <h1 class="header__title">
      <a href="{{ route('gallery',['dir'=>'entrance_photos']) }}">FTP-Галерея</a>
    </h1>
    <div class="header__actions">
      @if(Auth::check() && Auth::user()->role->NameRole==='Администратор')
        <a href="{{ route('admin.users') }}" class="btn btn-primary">Список пользователей</a>
        <a href="{{ route('admin.register') }}" class="btn btn-primary">Новый пользователь</a>
      @endif
      <a href="{{ route('gallery',['dir'=>'entrance_photos']) }}" class="btn btn-primary">В галерею</a>
      <form action="{{ route('logout') }}" method="POST" style="display:inline">@csrf
        <button type="submit" class="btn btn-danger">Выйти</button>
      </form>
    </div>
  </header>

  <main class="container">
    @yield('content')
  </main>

  <!-- Подключаем общий JS -->
  <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
