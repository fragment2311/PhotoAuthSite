@php
  $galleryRootDir = trim((string) config('app.gallery_root_dir', 'entrance_photos'), '/');
  if ($galleryRootDir === '') {
    $galleryRootDir = 'entrance_photos';
  }
@endphp

<header>
  <h1>
    <a href="{{ route('gallery', ['dir' => $galleryRootDir]) }}"
       style="text-decoration:none;color:inherit;">
      {{ $headerTitle ?? 'Галерея' }}
    </a>
  </h1>

  <div class="btn-group">
    <button type="button" class="btn btn-primary" onclick="toggleSidebarMenu()">
      ☰ Меню
    </button>

    <a href="{{ route('gallery', ['dir' => $galleryRootDir]) }}" class="btn btn-primary">Галерея</a>
    <a href="{{ route('passage.index') }}" class="btn btn-primary">Проходы</a>
  </div>
</header>

<div id="sidebarOverlay" class="app-menu-overlay" onclick="closeSidebarMenu()"></div>

<aside id="sidebarMenu" class="app-menu">
  <div class="app-menu__head">
    <div class="app-menu__title">Навигация</div>
    <button type="button" class="btn btn-primary app-menu__close" onclick="closeSidebarMenu()">
      Закрыть
    </button>
  </div>

  <div class="app-menu__section">
    <div class="app-menu__sectionTitle">Основное</div>
    <div class="app-menu__grid">
      <a href="{{ route('gallery', ['dir' => $galleryRootDir]) }}" class="btn btn-primary">В галерею</a>
      <a href="{{ route('passage.index') }}" class="btn btn-primary">Проходы</a>
    </div>
  </div>

  <div class="app-menu__divider"></div>

  <div class="app-menu__section">
    <div class="app-menu__sectionTitle">Администрирование</div>
    <div class="app-menu__grid">
      <a href="{{ route('admin.devices.index') }}" class="btn btn-primary">Устройства</a>
      <a href="{{ route('admin.users') }}" class="btn btn-primary">Список пользователей</a>
      <a href="{{ route('admin.register') }}" class="btn btn-primary">Новый пользователь</a>

      @if (\Illuminate\Support\Facades\Route::has('admin.settings.index'))
        <a href="{{ route('admin.settings.index') }}" class="btn btn-primary">Настройки</a>
      @endif
    </div>
  </div>

  <div class="app-menu__divider"></div>

  <div class="app-menu__section">
    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
      @csrf
      <button type="submit" class="btn btn-danger app-menu__logout">Выйти</button>
    </form>
  </div>
</aside>

<style>
  /* overlay не закрывает шапку */
  .app-menu-overlay{
    position: fixed;
    left: 0;
    right: 0;
    top: var(--header-h);
    bottom: 0;
    background: rgba(0,0,0,.28);
    z-index: 1500;

    opacity: 0;
    pointer-events: none;
    transition: opacity .15s ease;
  }
  .app-menu-overlay.is-open{
    opacity: 1;
    pointer-events: auto;
  }

  /* sidebar */
  .app-menu{
    position: fixed;
    top: var(--header-h);
    right: 0;
    height: calc(100vh - var(--header-h));
    width: 360px;
    max-width: 92vw;

    background: var(--card);
    box-shadow: -2px 0 14px rgba(0,0,0,.18);
    z-index: 1600;

    padding: 14px;
    overflow: auto;

    transform: translateX(110%);
    transition: transform .18s ease;
  }
  .app-menu.is-open{
    transform: translateX(0);
  }

  .app-menu__head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 12px;
    margin-bottom: 10px;
  }
  .app-menu__title{
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
  }

  .app-menu__divider{
    height: 1px;
    background: #e1e1e1;
    margin: 12px 0;
  }

  .app-menu__sectionTitle{
    font-size: 12px;
    color: var(--secondary);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  /* 2 колонки всегда (как ты хочешь), с нормальным gap */
  .app-menu__grid{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    overflow: visible;
  }

  /* --- КНОПКИ В МЕНЮ: КОМПАКТНЫЕ --- */
  .app-menu .btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 100%;
    max-width: 100%;

    font-size: 14px !important;
    padding: 7px 10px !important;
    border-radius: 10px !important;

    line-height: 1.15 !important;
    text-align: center;
    text-decoration: none;
    cursor: pointer;

    white-space: normal !important;   /* чтобы текст переносился */
    word-break: break-word;

    color: #fff !important;
    border: 2px solid transparent;
    box-sizing: border-box;
  }

  /* close — по контенту */
  .app-menu__close{
    width: auto !important;
    white-space: nowrap !important;
    padding: 7px 12px !important;
    font-size: 14px !important;
  }

  /* logout — на всю ширину и чуть выше */
  .app-menu__logout{
    width: 100% !important;
    font-size: 15px !important;
    padding: 10px 12px !important;
    border-radius: 10px !important;
  }

  /* цвета (как в шапке) */
  .app-menu .btn.btn-primary{
    background: var(--link, #007bff) !important;
    border-color: var(--link, #007bff) !important;
  }
  .app-menu .btn.btn-primary:hover{
    background: var(--link-hover, #0056b3) !important;
    border-color: var(--link-hover, #0056b3) !important;
  }

  .app-menu .btn.btn-danger{
    background: var(--danger, #dc3545) !important;
    border-color: var(--danger, #dc3545) !important;
  }
  .app-menu .btn.btn-danger:hover{
    background: var(--danger-hover, #c82333) !important;
    border-color: var(--danger-hover, #c82333) !important;
  }
</style>

<script>
  function toggleSidebarMenu() {
    const menu = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('sidebarOverlay');

    const isOpen = menu.classList.contains('is-open');
    if (isOpen) {
      menu.classList.remove('is-open');
      overlay.classList.remove('is-open');
    } else {
      menu.classList.add('is-open');
      overlay.classList.add('is-open');
    }
  }

  function closeSidebarMenu() {
    const menu = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('sidebarOverlay');

    menu.classList.remove('is-open');
    overlay.classList.remove('is-open');
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebarMenu();
  });
</script>
