@php
  $galleryRootDir = trim((string) config('app.gallery_root_dir', config('app.gallery_root_dir_default', '')), '/');

  $isAdminOpen =
      request()->routeIs('admin.*')
      || request()->routeIs('admin.devices.*')
      || request()->routeIs('admin.locations.*')
      || request()->routeIs('admin.users')
      || request()->routeIs('admin.register')
      || request()->routeIs('admin.settings.*')
      || request()->routeIs('admin.logs.*');
@endphp

<aside class="app-sidebar d-flex flex-column">
  <div class="app-sidebar__brand px-3 py-3">
    <a href="{{ route('gallery', ['dir' => $galleryRootDir]) }}" class="app-brand-link">
      <div class="app-brand-title">СКУД</div>
      <div class="app-brand-sub">Портал</div>
    </a>
  </div>

  <nav class="app-nav flex-grow-1 px-2">
    <a class="app-nav-link {{ request()->routeIs('gallery') ? 'active' : '' }}"
       href="{{ route('gallery', ['dir' => $galleryRootDir]) }}">
      <span class="app-nav-ico">🖼️</span>
      <span>Галерея</span>
    </a>

    <a class="app-nav-link {{ request()->routeIs('passage.*') ? 'active' : '' }}"
       href="{{ route('passage.index') }}">
      <span class="app-nav-ico">🚪</span>
      <span>Проходы</span>
    </a>

    <button class="app-nav-link app-nav-link-btn {{ $isAdminOpen ? 'active' : '' }}"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#adminMenu"
            aria-expanded="{{ $isAdminOpen ? 'true' : 'false' }}"
            aria-controls="adminMenu">
      <span class="app-nav-ico">⚙️</span>
      <span class="flex-grow-1 text-start">Администрирование</span>
      <span class="app-nav-caret">▾</span>
    </button>

    <div class="collapse {{ $isAdminOpen ? 'show' : '' }}" id="adminMenu">
      <div class="app-nav-sub ms-4 mt-1">
        <a class="app-nav-sublink {{ request()->routeIs('admin.devices.*') ? 'active' : '' }}"
           href="{{ route('admin.devices.index') }}">Устройства</a>

        <a class="app-nav-sublink {{ request()->routeIs('admin.locations.*') ? 'active' : '' }}"
           href="{{ route('admin.locations.index') }}">Локации</a>

        <a class="app-nav-sublink {{ request()->routeIs('admin.users') ? 'active' : '' }}"
           href="{{ route('admin.users') }}">Пользователи</a>

        <a class="app-nav-sublink {{ request()->routeIs('admin.register') ? 'active' : '' }}"
           href="{{ route('admin.register') }}">Новый пользователь</a>

        {{-- добавишь, когда сделаешь страницу настроек --}}
@if (\Illuminate\Support\Facades\Route::has('admin.settings.index'))
  <a class="app-nav-sublink {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
     href="{{ route('admin.settings.index') }}">Настройки</a>
@endif

@if (\Illuminate\Support\Facades\Route::has('admin.logs.index'))
  <a class="app-nav-sublink {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}"
     href="{{ route('admin.logs.index') }}">Журнал логов</a>
@endif
      </div>
    </div>
  </nav>

  <div class="app-sidebar__footer p-3">
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="btn btn-outline-danger w-100">Выйти</button>
    </form>
  </div>
</aside>
