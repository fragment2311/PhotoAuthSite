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
    :root {
      --bg: #f9f9f9;
      --card: #fff;
      --text: #333;
      --link: #007bff;
      --link-hover: #0056b3;
      --danger: #dc3545;
      --danger-hover: #c82333;
      --secondary: #6c757d;
      --secondary-hover: #5a6268;
      --primary: #28a745;
      --primary-hover: #218838;
    }
    *, *::before, *::after { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      font-size: 22px;
    }

    header {
      position: fixed;
      height: 80px;
      top: 0; left: 0; right: 0;
      background: var(--card);
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      z-index: 1000;
      border-radius: 0;
    }
    header h1 {
      margin: 0;
      font-size: 2rem;
    }

    .btn {
      padding: .7rem 1.4rem;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      text-align: center;
      font-size: 1.1rem;
      margin-left: .5rem;
      transition: background .2s;
      color: #fff;
    }
    .btn-primary      { background: var(--link); }
    .btn-primary:hover{ background: var(--link-hover); }
    .btn-secondary    { background: var(--secondary); }
    .btn-secondary:hover{ background: var(--secondary-hover); }
    .btn-danger       { background: var(--danger); }
    .btn-danger:hover { background: var(--danger-hover); }
    .btn-primary, .btn-secondary, .btn-danger { border-radius: 4px; }

    .container {
      width: 100%;
      max-width: 1600px;
      padding: 0 2rem;
      margin: 0 auto;
      margin-top: calc(80px + 2rem);
    }

    .nav-row {
      position: sticky;
      top: 80px;
      margin: 0 auto;
      max-width: 1600px;
      padding: .5rem 2rem;
      background: var(--card);
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      z-index: 900;
      border-bottom: 1px solid #eee;
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
</head>
<body>
  @php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Str;
  @endphp

  <header>
    <h1>
        <a href="{{ route('gallery',['dir'=>'entrance_photos']) }}"
           style="text-decoration:none;color:inherit;">
            Галерея
            @if($personName)
                <span style="font-size: 0.8em; margin-left: 15px;">{{ $personName }}</span>
            @endif
        </a>
    </h1>
    <div>
        @if(Auth::check() && Auth::user()->role->NameRole==='Администратор')
            <a href="{{ route('admin.users') }}" class="btn btn-primary">Список пользователей</a>
            <a href="{{ route('admin.register') }}" class="btn btn-primary">Новый пользователь</a>
            <a href="{{ route('admin.devices.index') }}" class="btn btn-primary">Устройства</a>
        @endif
        <a href="{{ route('gallery',['dir'=>'entrance_photos']) }}" class="btn btn-primary">В галерею</a>
        <form method="POST" action="{{ route('logout') }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-danger">Выйти</button>
        </form>
    </div>
  </header>

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

    @if($dir!==$base)
      <nav class="breadcrumb">
        @php $rel=str_replace($base.'/','',$dir); $parts=explode('/',$rel); @endphp
        @foreach($parts as $i=>$p)
          @if($i>0)<span class="separator">›</span>@endif
          <span>{{ $p }}</span>
        @endforeach
      </nav>
    @endif

    {{-- Папки --}}
    @if(count($items) && !$isShbfOrLkSubfolder)
    <div class="filters-row">
      <label for="folders-sort">Сортировать папки:</label>
      <select id="folders-sort">
        <option value="az" selected>А→Я</option>
        <option value="za">Я→А</option>
      </select>
    </div>

    <h2>Папки</h2>
    <div id="folders-grid" class="grid">
      @foreach($items as $item)
        @php
          $id = $item['id'];
          $show = $dir === $base
                  ? in_array($id, ['SHBF','LK'], true)
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
                 onerror="this.onerror=null;this.src='{{ $httpBaseUrl }}/entrance_photos/PhotosStaff/NotFound.jpg';">
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

      <label for="date-from">С даты:</label>
      <input type="date" id="date-from">

      <label for="date-to">По:</label>
      <input type="date" id="date-to">

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

        $url         = "{$httpBaseUrl}/" . ltrim("$dir/$name", '/') . "?v={$timestamp}";
        $notFoundUrl = "{$httpBaseUrl}/entrance_photos/PhotosStaff/NotFound.jpg?v=" . time();
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

      const cardDate = card.getAttribute('data-date');
      if (fromDate && cardDate && cardDate < fromDate) show = false;
      if (toDate   && cardDate && cardDate > toDate)   show = false;

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

</body>
</html>
