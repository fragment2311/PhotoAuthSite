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
    .nav-row{ top: var(--header-h); }

    .search-bar {
      font-size: 1.2rem;
      padding: .6rem 1rem;
      width: 350px;
      border-radius: 8px;
      border: 1px solid #bbb;
    }

    @media (max-width:700px) {
      .container { padding:0 0.5rem; }
      .nav-row { flex-direction:column; align-items:stretch; gap:.5rem; }
      .search-bar, .btn-secondary { width:100%; }
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
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(240px,1fr));
      gap: 0.5rem;
      margin-bottom:1.5rem;
    }

    .card {
      display:flex;
      flex-direction:column;
      height:320px;
    }
    .card:hover { transform: translateY(-3px); }

    .card img {
      width:100%;
      height:180px;
      object-fit:cover;
    }

    .card .name {
      padding: 0.5rem 0.4rem;
      font-size:1.05rem;
      word-break:break-word;
    }

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
    #back-to-top.show { display:flex; opacity: 1; }
    #back-to-top:hover { background: var(--link-hover); }
  </style>
<link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/datetime-picker.css') }}">

  <link rel="stylesheet" href="{{ asset('css/header-buttons.css') }}">
</head>

<body>
  @php
    use Illuminate\Support\Carbon;

    // Приведение CameraPhoto к URL:
    // 1) если уже http(s) — используем как есть
    // 2) если начинается с "/" — приклеиваем к httpBaseUrl
    // 3) иначе — считаем относительным путём к httpBaseUrl
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
      <input type="text" id="search-input" class="search-bar" placeholder="Поиск…">
    </div>

    {{-- Фото (копия логики из gallery) --}}
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
  <input type="text" id="date-from" class="dt-picker" placeholder="YYYY-MM-DD HH:MM:SS" readonly>

  <label for="date-to">По:</label>
  <input type="text" id="date-to" class="dt-picker" placeholder="YYYY-MM-DD HH:MM:SS" readonly>
</div>

      <button id="reset-filters">Сбросить</button>
    </div>

    <div id="photos-grid" class="grid">
      @foreach ($photos as $p)
        @php
          $dt = !empty($p->AccessTime) ? Carbon::parse($p->AccessTime) : null;

          $displayDate = $dt ? $dt->format('d.m.Y, H:i:s') : '';
          $dateAttr    = $dt ? $dt->format('Y-m-d') : '';
          $timestamp   = $dt ? $dt->format('YmdHis') : '';

          // Определяем тип Вход/Выход:
          // 1) сначала пробуем из имени файла in_/out_
          // 2) если не получилось — пробуем по AccessType (если есть)
          $fileNameOnly = basename((string)($p->CameraPhoto ?? ''));
          $typeCode = '';
          $displayType = '';

          if (preg_match('/^(in|out)_/i', $fileNameOnly, $t)) {
              $typeCode    = strtolower($t[1]);
              $displayType = $typeCode === 'in' ? 'Вход' : 'Выход';
          } else {
              // fallback: AccessType может быть строкой/числом (у вас может отличаться)
              $raw = strtolower((string)($p->AccessType ?? ''));
              if ($raw !== '') {
                  if (str_contains($raw, 'in') || str_contains($raw, 'вход') || $raw === '1') {
                      $typeCode = 'in';  $displayType = 'Вход';
                  } elseif (str_contains($raw, 'out') || str_contains($raw, 'выход') || $raw === '2') {
                      $typeCode = 'out'; $displayType = 'Выход';
                  }
              }
          }

          // Подпись как в gallery: "Вход 26.01.2026, 12:34:56"
          // Если тип не определён — просто дата/время
          $caption = trim(($displayType ? ($displayType . ' ') : '') . $displayDate);

          $url         = buildPhotoUrl($httpBaseUrl ?? '', $p->CameraPhoto) . ($timestamp ? "?v={$timestamp}" : '');
          $notFoundUrl = rtrim(($httpBaseUrl ?? ''), '/') . "/entrance_photos/PhotosStaff/NotFound.jpg?v=" . time();

          $dataFilename = $fileNameOnly !== '' ? $fileNameOnly : (string)($p->CameraPhoto ?? '');
        @endphp

        <div class="card"
             data-type="{{ $typeCode }}"
             data-date="{{ $dateAttr }}"
             data-timestamp="{{ $timestamp }}"
             data-filename="{{ $dataFilename }}">
          <a href="{{ $url }}" target="_blank">
            <img src="{{ $url }}"
                 alt="{{ $dataFilename }}"
                 loading="lazy"
                 decoding="async"
                 width="240" height="180"
                 onerror="this.onerror=null;this.src='{{ $notFoundUrl }}'">
          </a>
          <div class="name">{{ $caption }}</div>
        </div>
      @endforeach
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

  {{-- Скрипт фильтрации и сортировки фото (копия из gallery) --}}
  <script>
document.addEventListener('DOMContentLoaded', () => {
  const photoSort    = document.getElementById('photos-sort');
  const photoType    = document.getElementById('photo-type');
  const dateFrom     = document.getElementById('date-from');
  const dateTo       = document.getElementById('date-to');
  const globalSearch = document.getElementById('search-input');
  const resetBtn     = document.getElementById('reset-filters');
  const photosGrid   = document.getElementById('photos-grid');

  if (!photosGrid) return;

  function getQuery() {
    return (globalSearch?.value || '').trim().toLowerCase();
  }

  function applyFilters() {
    const typeFilter = photoType.value;
    const fromDate   = dateFrom.value;
    const toDate     = dateTo.value;
    const query      = getQuery();

    const cards = Array.from(photosGrid.querySelectorAll('.card'));
    for (const card of cards) {
      let show = true;

      if (typeFilter !== 'all') {
        show = show && (card.getAttribute('data-type') === typeFilter);
      }

function toYmdHis(v){
  if (!v) return '';
  return v.replace(/[-:\s]/g,''); // '2026-01-31 23:59:59' -> '20260131235959'
}

const fromTs = toYmdHis(fromDate);
const toTs   = toYmdHis(toDate);

const cardTs = card.getAttribute('data-timestamp') || '';

if ((fromTs || toTs) && !cardTs) show = false;       // если у фото нет даты — скрыть при активном фильтре
if (fromTs && cardTs && cardTs < fromTs) show = false;
if (toTs   && cardTs && cardTs > toTs)   show = false;

      if (query) {
        const caption  = card.querySelector('.name')?.textContent.toLowerCase() || '';
        const filename = (card.getAttribute('data-filename') || '').toLowerCase();
        if (!caption.includes(query) && !filename.includes(query)) show = false;
      }

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
      } else {
        const ta = a.getAttribute('data-timestamp') || '';
        const tb = b.getAttribute('data-timestamp') || '';
        return mode === 'date-asc'
          ? ta.localeCompare(tb)
          : tb.localeCompare(ta);
      }
    });

    cards.forEach(c => photosGrid.appendChild(c));
  }

  function resetFilters(e) {
    if (e) e.preventDefault();
    photoSort.value = 'date-desc';
    photoType.value = 'all';
    dateFrom.value  = '';
    dateTo.value    = '';
    if (globalSearch) globalSearch.value = '';
    applyFilters();
  }

  photoSort.addEventListener('change', sortPhotos);
  photoType.addEventListener('change', applyFilters);
  dateFrom.addEventListener('change', applyFilters);
  dateTo.addEventListener('change', applyFilters);
  if (globalSearch) globalSearch.addEventListener('input', applyFilters);
  if (resetBtn)     resetBtn.addEventListener('click', resetFilters);

  const today = new Date().toISOString().split('T')[0];
  if (dateFrom) dateFrom.max = today;
  if (dateTo)   dateTo.max   = today;

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
