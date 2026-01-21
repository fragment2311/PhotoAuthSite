<header>
  <h1>
    <a href="{{ route('gallery', ['dir' => 'entrance_photos']) }}"
       style="text-decoration:none;color:inherit;">
      {{ $headerTitle ?? 'Галерея' }}
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
