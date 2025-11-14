<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title', 'Приложение')</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
  <nav>
    @auth
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Выйти</button>
      </form>
    @endauth
  </nav>
  <div class="container">
    @yield('content')
  </div>
</body>
</html>
