<!DOCTYPE html>
<html lang="ru">
<head>
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
  <meta http-equiv="Pragma" content="no-cache"/>
  <meta http-equiv="Expires" content="0"/>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Все проходы</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }

    .container{ margin-top: calc(var(--header-h) + 2rem); }
    .nav-row{
      top: var(--header-h);
      display:flex;
      align-items:center;
      gap:.5rem;
      flex-wrap:wrap;
    }

    .search-bar {
      font-size: 1.2rem;
      padding: .6rem 1rem;
      width: 350px;
      border-radius: 8px;
      border: 1px solid #bbb;
    }

    .btn.btn-primary {
      background: var(--link);
      color: #fff;
    }
    .btn.btn-primary:hover { background: var(--link-hover); }

    .btn.btn-secondary {
      background: var(--secondary);
      color: #fff;
    }
    .btn.btn-secondary:hover { background: var(--secondary-hover); }

    @media (max-width:700px) {
      .container { padding:0 0.5rem; }
      .nav-row { flex-direction:column; align-items:stretch; gap:.5rem; }
      .search-bar, .nav-row .btn, .filters-row .btn { width:100%; }
    }

    .filters-row {
      display: flex;
      align-items: center;
      gap: 1.5rem;
      margin: 1rem 0 .5rem;
      flex-wrap: wrap;
    }
    .filters-row label { font-size:1.1rem; color:#444; margin-right:.6rem; }
    .filters-row select,
    .filters-row input[type="date"] {
      font-size:1.1rem;
      padding:.2rem .6rem;
      border-radius:6px;
      border:1px solid #bbb;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill,minmax(240px,1fr));
      gap: 0.5rem;
      margin-bottom:1.5rem;
    }
    .card {
      display:flex;
      flex-direction:column;
      height:320px;
    }
    .card:hover { transform: translateY(-3px); }
    .card img { width:100%; height:180px; object-fit:cover; }
    .card .name { padding: 0.5rem 0.4rem; font-size:1.05rem; word-break:break-word; }

    #back-to-top {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 3rem;
      height: 3rem;
      background: var(--link);
      color: #fff;
      border: none;
      border-radius: 50%;
      cursor: pointer;
      font-size: 1.5rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1100;
      transition: opacity .3s;
      opacity: 0;
    }
    #back-to-top.show { display: flex; opacity: 1; }
    #back-to-top:hover { background: var(--link-hover); }
    .search-loading[hidden] { display:none; }
    .search-loading { position:fixed; inset:0; z-index:2000; display:flex; align-items:center; justify-content:center; padding:1rem; background:rgba(15, 23, 42, .42); }
    .search-loading__panel { max-width:360px; padding:1.25rem 1.5rem; border-radius:12px; background:#fff; color:#111827; box-shadow:0 18px 50px rgba(0,0,0,.25); text-align:center; font-size:1.1rem; }
  </style>

  <link rel="stylesheet" href="{{ asset('css/header-buttons.css') }}">
  <link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/datetime-picker.css') }}">
</head>
<body>
  @php
    use Illuminate\Support\Carbon;

    function buildPhotoUrl($httpBaseUrl, $path) {
      $path = (string)($path ?? '');
      if ($path === '') return '';
      if (preg_match('~^https?://~i', $path)) return $path;
      $httpBaseUrl = trim((string)($httpBaseUrl ?? ''));
      if ($httpBaseUrl === '') return '';
      if (str_starts_with($path, '/')) return rtrim($httpBaseUrl, '/') . $path;
      return rtrim($httpBaseUrl, '/') . '/' . ltrim($path, '/');
    }
  @endphp

  @include('layouts.header', ['headerTitle' => 'Все проходы'])

  <div class="container">
    <form id="all-passages-filters" method="GET" action="{{ route('all.passages') }}">
      <div class="nav-row">
        <a href="{{ route('gallery') }}" class="btn btn-secondary">← Назад</a>
        <input type="text" id="search-input" name="q" value="{{ $q ?? '' }}" class="search-bar" placeholder="Поиск по всем сотрудникам…">
        <button type="submit" class="btn btn-primary">Найти</button>
      </div>

      <div class="filters-row">
        <label for="photos-sort">Сортировать фото:</label>
        <select id="photos-sort" name="photo_sort" data-server-filter>
          <option value="date-desc" @selected(($photoSort ?? 'date-desc') === 'date-desc')>Новые→Старые</option>
          <option value="date-asc" @selected(($photoSort ?? 'date-desc') === 'date-asc')>Старые→Новые</option>
          <option value="az" @selected(($photoSort ?? 'date-desc') === 'az')>А→Я</option>
          <option value="za" @selected(($photoSort ?? 'date-desc') === 'za')>Я→А</option>
        </select>

        <label for="photo-type">Тип:</label>
        <select id="photo-type" name="photo_type" data-server-filter>
          <option value="all" @selected(($photoType ?? 'all') === 'all')>Все</option>
          <option value="in" @selected(($photoType ?? 'all') === 'in')>Вход</option>
          <option value="out" @selected(($photoType ?? 'all') === 'out')>Выход</option>
        </select>

        <div class="dt">
          <label for="date-from">С:</label>
          <input type="text" id="date-from" name="from" class="dt-picker" value="{{ $from ?? '' }}" placeholder="YYYY-MM-DD HH:MM:SS" readonly data-server-filter>

          <label for="date-to">По:</label>
          <input type="text" id="date-to" name="to" class="dt-picker" value="{{ $to ?? '' }}" placeholder="YYYY-MM-DD HH:MM:SS" readonly data-server-filter>
        </div>

        <a href="{{ route('all.passages') }}" class="btn btn-primary">Сбросить</a>
      </div>
    </form>
    @if(count($photos))
    <div id="photos-grid" class="grid">
      @foreach ($photos as $p)
      @php
          $dt = !empty($p->AccessTime) ? Carbon::parse($p->AccessTime) : null;

          $displayDate = $dt ? $dt->format('d.m.Y, H:i:s') : '';
          $timestamp   = $dt ? $dt->format('YmdHis') : '';

          $fileNameOnly = basename((string)($p->CameraPhoto ?? ''));
          $typeCode = '';
          $displayType = '';

          if (preg_match('/^(in|out)_/i', $fileNameOnly, $t)) {
              $typeCode    = strtolower($t[1]);
              $displayType = $typeCode === 'in' ? 'Вход' : 'Выход';
          } else {
              $raw = strtolower((string)($p->AccessType ?? ''));
              if ($raw !== '') {
                  if (str_contains($raw, 'in') || str_contains($raw, 'вход') || $raw === '1') {
                      $typeCode = 'in';  $displayType = 'Вход';
                  } elseif (str_contains($raw, 'out') || str_contains($raw, 'выход') || $raw === '2') {
                      $typeCode = 'out'; $displayType = 'Выход';
                  }
              }
          }

          $photoPath = str_replace('\\', '/', (string)($p->CameraPhoto ?? ''));
          $isCardNotFound = (bool) preg_match('~(?:^|/)CardNotFound(?:/|$)~i', $photoPath);
          $fio = $isCardNotFound ? 'Карта не найдена' : trim((string)($p->PersonFio ?? ''));
          if ($fio === '') {
              $fio = (string)($p->PersonId ?? 'Не определён');
              if ($fio === '') {
                  $fio = 'Не определён';
              }
          }
          $caption = trim(($displayType ? ($displayType . ' ') : '') . $displayDate);
          if (!empty($p->Point)) {
              $caption .= ' · ' . trim((string)$p->Point);
          }

          $notFoundBase = (string)($notFoundUrl ?? '');
          $notFoundUrl = str_starts_with($notFoundBase, 'data:')
              ? $notFoundBase
              : rtrim($notFoundBase, '/') . '?v=' . time();

          $builtUrl = buildPhotoUrl($httpBaseUrl ?? '', $p->CameraPhoto);
          $url = $builtUrl === ''
              ? $notFoundUrl
              : $builtUrl . ($timestamp ? "?v={$timestamp}" : '');
      @endphp

        <div class="card"
             data-type="{{ $typeCode }}"
             data-timestamp="{{ $timestamp }}"
             data-filename="{{ $fileNameOnly }}"
             data-person="{{ mb_strtolower($fio) }}">
          <a href="{{ $url }}" target="_blank">
            <img src="{{ $url }}"
                 alt="{{ $fileNameOnly }}"
                 loading="lazy"
                 decoding="async"
                 width="240" height="180"
                 onerror="this.onerror=null;this.src='{{ $notFoundUrl }}'">
          </a>
          <div class="name">{{ $fio }}<br>{{ $caption }}</div>
        </div>
      @endforeach
    </div>
    @else
      <p>Фото проходов не найдены.</p>
    @endif

    @if(method_exists($photos, 'nextPageUrl'))
      <div style="margin:1rem 0;">
        @include('pagination.passages-cursor', ['paginator' => $photos])
      </div>
    @endif

    <button id="back-to-top" aria-label="Наверх">↑</button>
  </div>

  <div id="search-loading" class="search-loading" hidden role="status" aria-live="polite" aria-label="Идёт поиск фото">
    <div class="search-loading__panel">Идёт поиск фото…<br><small>При большом периоде это может занять некоторое время.</small></div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const btn = document.getElementById('back-to-top');
      window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) btn.classList.add('show');
        else btn.classList.remove('show');
      });
      btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const form = document.getElementById('all-passages-filters');
      const overlay = document.getElementById('search-loading');
      if (!form || !overlay) return;

      form.addEventListener('submit', () => {
        overlay.hidden = false;
      });
    });
  </script>

  <script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>
  <script src="{{ asset('js/datetime-picker.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      initDateTimePickers('.dt-picker');
    });
  </script>
</body>
</html>
