<header class="app-topbar d-flex align-items-center justify-content-between px-3 py-2">
  <div class="d-flex align-items-center gap-2">
    {{-- Кнопка для мобилки (показывает offcanvas sidebar) --}}
    <button class="btn btn-outline-secondary d-lg-none"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#mobileSidebar"
            aria-controls="mobileSidebar">
      ☰
    </button>

    <div class="app-topbar-title">
      {{ $headerTitle ?? 'Галерея' }}
    </div>
  </div>

  {{-- Слот под “действия страницы”: кнопки, фильтры, скачать Excel и т.д. --}}
  <div class="app-topbar-actions">
    @yield('topbar-actions')
  </div>
</header>
