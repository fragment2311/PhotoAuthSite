<!DOCTYPE html>
<html lang="ru">
<head>
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"/>
  <meta http-equiv="Pragma" content="no-cache"/>
  <meta http-equiv="Expires" content="0"/>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FTP-Галерея</title>
  <style>

    *, *::before, *::after { box-sizing: border-box; }

  



.container{
  margin-top: calc(var(--header-h) + 2rem);
}

.nav-row{
  top: var(--header-h);
}


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
    .card .name { padding: 0.5rem 0.4rem; font-size:1.15rem; word-break:break-all; }

    .folder-link {
      display:block;
      text-align:center;
      padding:1rem;
      background:var(--link);
      color:#fff;
      text-decoration:none;
      font-weight:bold;
      transition:background .2s;
      border-radius:4px;
    }
    .folder-link:hover { background: var(--link-hover); }

    h2 { margin:1.5rem 0 .75rem; font-size:1.35rem; font-weight:600; }
    .breadcrumb {   margin-top: 1rem; margin-bottom:1rem; font-size:1.1rem; color:var(--secondary); }
    .separator { margin:0 .5rem; color:var(--secondary); }

    .photo-container {
      height:270px;
      background:#f5f5f5;
      display:flex;
      align-items:center;
      justify-content:center;
      border-bottom:1px solid #ddd;
    }
    .photo-container img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }
    .storage-state {
      margin: 0 0 1rem;
      padding: .85rem 1rem;
      border-radius: 8px;
      border: 1px solid #f59e0b;
      background: #fff7ed;
      color: #92400e;
      line-height: 1.35;
    }
    .storage-state__reason {
      margin-top: .35rem;
      font-size: .95rem;
      opacity: .85;
      word-break: break-word;
    }
    .no-photo { display:flex; align-items:center; justify-content:center; width:100%; height:100%; background:#f9f9f9; }
    .no-photo img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .staff-photo { max-width:100%; max-height:100%; object-fit:contain; transition:transform .3s ease; }
    .card:hover .staff-photo { transform:scale(1.05); }
    header h1 span { font-weight: normal; opacity: 0.8; vertical-align: middle; }
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
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Str;
  @endphp

@include('layouts.header', ['headerTitle' => 'Галерея'])


  <div class="container">
    <div class="nav-row">
      @if($dir!==$base)
        @php
          $parent = rtrim($dir,'/');
          $parts = explode('/',$parent); array_pop($parts);
          $parent = implode('/',$parts)?:$base;
        @endphp
        <a href="{{ route('gallery',['dir'=>$parent]) }}"
           class="btn btn-secondary">← Назад</a>
      @endif
      <input type="text" id="search-input" class="search-bar" placeholder="Поиск…">
    </div>

    @if(!empty($storageUnavailable))
      @php
        $storageReasonMap = [
          'empty_photo_storage_host' => 'Не задан адрес фото-хранилища в настройках.',
          'photo_storage_probe_exception' => 'Ошибка сетевого соединения с фото-хранилищем.',
        ];

        $storageReasonText = $storageReasonMap[$storageUnavailableReason ?? '']
          ?? 'Фото-хранилище не ответило корректно.';

        if (is_string($storageUnavailableReason ?? '') && str_starts_with((string) $storageUnavailableReason, 'photo_storage_probe_http_')) {
          $statusCode = (string) str_replace('photo_storage_probe_http_', '', (string) $storageUnavailableReason);
          if ($statusCode !== '') {
            $storageReasonText = 'Фото-хранилище вернуло неожиданный HTTP-статус: ' . $statusCode . '.';
          }
        }
      @endphp
      <div class="storage-state">
        Фото-хранилище временно недоступно. Галерея открыта в безопасном пустом режиме.
        @if(!empty($storageUnavailableReason))
          <div class="storage-state__reason">Причина: {{ $storageReasonText }}</div>
          @if((bool) config('app.debug', false))
            <div class="storage-state__reason">Технический код: {{ $storageUnavailableReason }}</div>
          @endif
        @endif
      </div>
    @endif



   {{-- Папки --}}
@if(count($items) && !$isShbfOrLkSubfolder)

  @if($dir === $base && !($rootSectionsMatchCurrent ?? true) && empty($storageUnavailable))
    <div class="help" style="margin:0 0 .75rem;color:#b45309;">
      В корневой папке не найдены каталоги, совпадающие с активными точками из пар устройств.
    </div>
  @endif

  <div class="filters-row">
    <label for="folders-sort">Сортировать папки:</label>
    <select id="folders-sort">
      <option value="az" selected>А→Я</option>
      <option value="za">Я→А</option>
    </select>
  </div>

  <h2>Папки</h2>
  <div id="folders-grid" class="grid">

    {{-- НОВАЯ КНОПКА рядом с SHBF/LK: показываем только в корне --}}
    @if($dir === $base)
      <div class="card" data-folder="0-all-passages">
        <a href="{{ route('all.passages') }}" class="folder-link all-passages-link">
          📁 Все проходы
        </a>
      </div>
    @endif

    @foreach($items as $item)
      @php
        $id = $item['id'];
        $idNorm = mb_strtolower(trim((string) $id));
        $show = $dir === $base
                ? (
                    in_array($idNorm, $rootSectionsNorm ?? [], true)
                    || !($rootSectionsMatchCurrent ?? true)
                  )
                : (
                    ! $isShbfOrLk
                    || (is_numeric($id) && $id !== '0')
                    || in_array($id, ['CardNotFound','Button'], true)
                  );
      @endphp

      @if($show)
        <div class="card" data-folder="{{ Str::lower($item['name']) }}">
          @if($inRootShbfLk)
            <div class="photo-container">
              <img src="{{ $item['photo'] }}"
                   alt="Фото {{ $item['name'] }}"
                   class="staff-photo"
                   onerror="this.onerror=null;this.src='{{ $notFoundUrl ?? '' }}';">
            </div>
          @endif

          <a href="{{ route('gallery', ['dir' => $item['path']]) }}"
             class="folder-link">
            📁 {{ $item['name'] }}
          </a>
        </div>
      @endif
    @endforeach

  </div>
@endif


    {{-- Фото --}}
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
    @foreach ($photos as $name)
    @php
        // Тип (Вход/Выход)
        $typeCode    = '';
        $displayType = '';
        if (preg_match('/^(in|out)_/i', $name, $t)) {
            $typeCode    = strtolower($t[1]);
            $displayType = $typeCode === 'in' ? 'Вход' : 'Выход';
        }

        // Дата и время: ищем 8 + 6 цифр
        $datePart = '';
        $timePart = '';
        if (preg_match('/^(?:in|out)_(\d{8})_(\d{6})/i', $name, $m)) {
            $datePart = $m[1];
            $timePart = $m[2];
        }

        $displayDate = '';
        $dateAttr    = '';
        $timestamp   = '';

        if ($datePart && $timePart) {
            // пробуем YYYYMMDD, иначе DDMMYYYY (валидация через checkdate)
            $y  = (int)substr($datePart, 0, 4);
            $mn = (int)substr($datePart, 4, 2);
            $d  = (int)substr($datePart, 6, 2);
            if (!checkdate($mn, $d, $y)) {
                $d  = (int)substr($datePart, 0, 2);
                $mn = (int)substr($datePart, 2, 2);
                $y  = (int)substr($datePart, 4, 4);
            }
            $h = (int)substr($timePart, 0, 2);
            $i = (int)substr($timePart, 2, 2);
            $s = (int)substr($timePart, 4, 2);

            $displayDate = sprintf('%02d.%02d.%04d, %02d:%02d:%02d', $d, $mn, $y, $h, $i, $s);
            $dateAttr    = sprintf('%04d-%02d-%02d', $y, $mn, $d);
            $timestamp   = sprintf('%04d%02d%02d%02d%02d%02d', $y, $mn, $d, $h, $i, $s);
        }

        $url = "{$httpBaseUrl}/" . ltrim("$dir/$name", '/') . "?v={$timestamp}";
        $notFoundBase = (string) ($notFoundUrl ?? '');
        $notFoundUrl = str_starts_with($notFoundBase, 'data:')
            ? $notFoundBase
            : rtrim($notFoundBase, '/') . '?v=' . time();
    @endphp

      <div class="card"
           data-type="{{ $typeCode }}"
           data-date="{{ $dateAttr }}"
           data-timestamp="{{ $timestamp }}"
           data-filename="{{ $name }}">
        <a href="{{ $url }}" target="_blank">
          <img src="{{ $url }}"
               alt="{{ $name }}"
               loading="lazy"
               decoding="async"
               width="240" height="180"
               onerror="this.onerror=null;this.src='{{ $notFoundUrl }}'">
        </a>
        <div class="name">{{ $displayType }} {{ $displayDate }}</div>
      </div>
    @endforeach
    </div>
    {{ $photos->onEachSide(1)->links('pagination.passages') }}
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
    document.querySelectorAll('.all-passages-link').forEach((link) => {
      link.addEventListener('click', () => {
        const overlay = document.createElement('div');
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.textContent = 'Идёт поиск фото…';
        Object.assign(overlay.style, {
          position: 'fixed', inset: '0', zIndex: '2000', display: 'flex',
          alignItems: 'center', justifyContent: 'center', background: 'rgba(15, 23, 42, .42)',
          color: '#111827', fontSize: '1.1rem', fontWeight: '600'
        });
        const panel = document.createElement('div');
        panel.textContent = overlay.textContent;
        overlay.textContent = '';
        Object.assign(panel.style, { background: '#fff', borderRadius: '12px', padding: '1.25rem 1.5rem', boxShadow: '0 18px 50px rgba(0,0,0,.25)' });
        overlay.appendChild(panel);
        document.body.appendChild(overlay);
      }, { once: true });
    });
  });
</script>

{{-- Скрипт фильтрации и сортировки фото --}}
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

  function resetFilters() {
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

{{-- Скрипт фильтрации и сортировки папок --}}
@unless($isShbfOrLkSubfolder)
<script>
document.addEventListener('DOMContentLoaded', () => {
  const folderSort  = document.getElementById('folders-sort');
  const foldersGrid = document.getElementById('folders-grid');
  const searchInput = document.getElementById('search-input');

  if (!folderSort || !foldersGrid) return;

  function sortAndFilterFolders() {
    const q = searchInput?.value.trim().toLowerCase() || '';

    Array.from(foldersGrid.children).forEach(card => {
      const txt = card.textContent.toLowerCase();
      card.style.display = txt.includes(q) ? '' : 'none';
    });

    const visible = Array.from(foldersGrid.children)
                         .filter(c => c.style.display !== 'none');

    visible.sort((a, b) => {
      const A = a.textContent.trim().toLowerCase();
      const B = b.textContent.trim().toLowerCase();
      return folderSort.value === 'za'
        ? B.localeCompare(A, 'ru')
        : A.localeCompare(B, 'ru');
    });

    visible.forEach(c => foldersGrid.appendChild(c));
  }

  folderSort.addEventListener('change', sortAndFilterFolders);
  if (searchInput) searchInput.addEventListener('input', sortAndFilterFolders);
  sortAndFilterFolders();
});
</script>
@endunless

<script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('js/datetime-picker.js') }}"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    initDateTimePickers('.dt-picker');
  });
</script>

</body>
</html>
