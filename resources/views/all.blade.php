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
      if (str_starts_with($path, '/')) return rtrim($httpBaseUrl, '/') . $path;
      return rtrim($httpBaseUrl, '/') . '/' . ltrim($path, '/');
    }
  @endphp

  @include('layouts.header', ['headerTitle' => 'Все проходы'])

  <div class="container">
    <div class="nav-row">
      <a href="{{ route('gallery') }}" class="btn btn-secondary">← Назад</a>
      <input type="text" id="search-input" value="{{ $q ?? '' }}" class="search-bar" placeholder="Поиск по всем сотрудникам…">
      <button id="apply-filters" type="button" class="btn btn-primary">Найти</button>
    </div>

    @if(count($photos))
    <div class="filters-row">
      <label for="photos-sort">Сортировать фото:</label>
      <select id="photos-sort">
        <option value="date-desc" selected>Новые→Старые</option>
        <option value="date-asc">Старые→Новые</option>
        <option value="az">А→Я</option>
        <option value="za">Я→А</option>
      </select>

      <label for="photo-type">Тип:</label>
      <select id="photo-type">
        <option value="all">Все</option>
        <option value="in">Вход</option>
        <option value="out">Выход</option>
      </select>

      <div class="dt">
        <label for="date-from">С:</label>
        <input type="text" id="date-from" class="dt-picker" value="{{ $from ?? '' }}" placeholder="YYYY-MM-DD HH:MM:SS" readonly>

        <label for="date-to">По:</label>
        <input type="text" id="date-to" class="dt-picker" value="{{ $to ?? '' }}" placeholder="YYYY-MM-DD HH:MM:SS" readonly>
      </div>

      <button id="reset-filters" type="button" class="btn btn-primary">Сбросить</button>
    </div>

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

          $fio = trim((string)($p->PersonFio ?? ''));
          if ($fio === '') {
              $fio = (string)($p->PersonId ?? '');
          }

          $caption = trim(($displayType ? ($displayType . ' ') : '') . $displayDate);
          if (!empty($p->Point)) {
              $caption .= ' · ' . trim((string)$p->Point);
          }

          $url         = buildPhotoUrl($httpBaseUrl ?? '', $p->CameraPhoto) . ($timestamp ? "?v={$timestamp}" : '');
          $notFoundUrl = rtrim((string)($notFoundUrl ?? ''), '/') . '?v=' . time();
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

    @if(method_exists($photos, 'links'))
      <div style="margin:1rem 0;">
        {{ $photos->onEachSide(1)->links() }}
      </div>
    @endif

    <button id="back-to-top" aria-label="Наверх">↑</button>
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
    const photoSort    = document.getElementById('photos-sort');
    const photoType    = document.getElementById('photo-type');
    const dateFrom     = document.getElementById('date-from');
    const dateTo       = document.getElementById('date-to');
    const globalSearch = document.getElementById('search-input');
    const applyBtn     = document.getElementById('apply-filters');
    const resetBtn     = document.getElementById('reset-filters');
    const photosGrid   = document.getElementById('photos-grid');
    const allPassagesUrl = @json(route('all.passages'));

    if (!photosGrid || !photoSort || !photoType) return;

    const urlParams = new URLSearchParams(window.location.search);
    const initialSort = (urlParams.get('photo_sort') || '').toLowerCase();
    const initialType = (urlParams.get('photo_type') || '').toLowerCase();

    if (['date-desc', 'date-asc', 'az', 'za'].includes(initialSort)) {
      photoSort.value = initialSort;
    }
    if (['all', 'in', 'out'].includes(initialType)) {
      photoType.value = initialType;
    }

    function buildServerUrl() {
      const params = new URLSearchParams(window.location.search);

      const q = (globalSearch?.value || '').trim();
      const from = dateFrom?.value || '';
      const to = dateTo?.value || '';
      const sort = photoSort?.value || 'date-desc';
      const type = photoType?.value || 'all';

      if (q) params.set('q', q); else params.delete('q');
      if (from) params.set('from', from); else params.delete('from');
      if (to) params.set('to', to); else params.delete('to');
      if (sort !== 'date-desc') params.set('photo_sort', sort); else params.delete('photo_sort');
      if (type !== 'all') params.set('photo_type', type); else params.delete('photo_type');

      params.delete('page');

      const qs = params.toString();
      return qs ? `${allPassagesUrl}?${qs}` : allPassagesUrl;
    }

    function applyServerFilters() {
      const nextUrl = buildServerUrl();
      const currentUrl = `${window.location.origin}${window.location.pathname}${window.location.search}`;
      if (nextUrl === currentUrl) return;
      window.location.assign(nextUrl);
    }

    function applyAllFiltersByButton() {
      applyFilters();
      applyServerFilters();
    }

    function toYmdHis(v) {
      if (!v) return '';
      return v.replace(/[-:\s]/g, '');
    }

    function applyFilters() {
      const typeFilter = photoType.value;
      const fromTs = toYmdHis(dateFrom?.value || '');
      const toTs   = toYmdHis(dateTo?.value || '');
      const cards = Array.from(photosGrid.querySelectorAll('.card'));

      for (const card of cards) {
        let show = true;
        if (typeFilter !== 'all') {
          show = show && (card.getAttribute('data-type') === typeFilter);
        }

        const cardTs = card.getAttribute('data-timestamp') || '';
        if ((fromTs || toTs) && !cardTs) show = false;
        if (fromTs && cardTs && cardTs < fromTs) show = false;
        if (toTs && cardTs && cardTs > toTs) show = false;

        card.style.display = show ? '' : 'none';
      }
      sortPhotos();
    }

    function sortPhotos() {
      const mode  = photoSort.value;
      const cards = Array.from(photosGrid.querySelectorAll('.card'))
                         .filter(c => c.style.display !== 'none');

      cards.sort((a, b) => {
        if (mode === 'az' || mode === 'za') {
          const ta = (a.querySelector('.name')?.textContent || '').trim().toLowerCase();
          const tb = (b.querySelector('.name')?.textContent || '').trim().toLowerCase();
          return mode === 'za'
            ? tb.localeCompare(ta, 'ru')
            : ta.localeCompare(tb, 'ru');
        }

        const ta = a.getAttribute('data-timestamp') || '';
        const tb = b.getAttribute('data-timestamp') || '';
        return mode === 'date-asc'
          ? ta.localeCompare(tb)
          : tb.localeCompare(ta);
      });

      cards.forEach(c => photosGrid.appendChild(c));
    }

    function resetFilters() {
      photoSort.value = 'date-desc';
      photoType.value = 'all';
      if (dateFrom) dateFrom.value = '';
      if (dateTo)   dateTo.value   = '';
      if (globalSearch) globalSearch.value = '';

      const hasServerFilters = new URLSearchParams(window.location.search).toString().length > 0;
      if (hasServerFilters) {
        window.location.assign(allPassagesUrl);
        return;
      }

      applyFilters();
    }

    if (applyBtn) applyBtn.addEventListener('click', applyAllFiltersByButton);
    if (globalSearch) globalSearch.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        applyAllFiltersByButton();
      }
    });
    if (resetBtn) resetBtn.addEventListener('click', resetFilters);

    applyFilters();
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
