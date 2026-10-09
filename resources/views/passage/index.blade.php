<!doctype html>
<html lang="ru">
    <style>
        :root{
  --btn-bg: #3b82f6;
  --btn-bg-hover:#2563eb;
  --btn-text:#fff;

  --btn-danger:#d9534f;
  --btn-danger-hover:#c9302c;

  --btn-ghost-bg: transparent;
  --btn-ghost-text:#2563eb;
  --btn-ghost-hover-bg: rgba(37,99,235,.08);

  --btn-radius: 8px;
  --btn-font: 13px;
  --btn-pad-y: 116px;
  --btn-pad-x: 12px;
}

/* База — работает и для <a>, и для <button> */
.ui-btn{
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;

  padding: var(--btn-pad-y) var(--btn-pad-x);
  border-radius: var(--btn-radius);

  font-size: var(--btn-font);
  font-weight: 600;
  text-decoration: none;
  line-height: 1.2;

  border: 1px solid transparent;
  cursor: pointer;
  user-select: none;

  transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .05s ease;
}

.ui-btn:active{ transform: translateY(1px); }

/* Primary */
.ui-btn.is-primary{
  background: var(--btn-bg);
  color: var(--btn-text);
}
.ui-btn.is-primary:hover{
  background: var(--btn-bg-hover);
  color: var(--btn-text);
}

/* Danger */
.ui-btn.is-danger{
  background: var(--btn-danger);
  color: #fff;
}
.ui-btn.is-danger:hover{
  background: var(--btn-danger-hover);
  color: #fff;
}

/* Ghost / link-like (для "Скачать Excel" можно так же) */
.ui-btn.is-ghost{
  background: var(--btn-ghost-bg);
  color: var(--btn-ghost-text);
  border-color: rgba(37,99,235,.25);
}
.ui-btn.is-ghost:hover{
  background: var(--btn-ghost-hover-bg);
}

/* Компактный вариант, если надо */
.ui-btn.is-sm{
  padding: 8px 15px;
  font-size: 12px;
  border-radius: 7px;
}

/* Чтобы Bootstrap .btn не перебивал. Если Bootstrap подключён — это важно */
.btn.ui-btn{
  border: 1px solid transparent; /* фикс на bootstrap border */
  background: initial;
  color: inherit;
}
  /* Фикс “огромных стрелок” (SVG) в пагинации Laravel */
  .passage-pagination svg {
    width: 16px !important;
    height: 16px !important;
    max-width: 16px !important;
    max-height: 16px !important;
    display: inline-block !important;
    vertical-align: middle;
  }

  /* чтобы ссылки не раздувались */
  .passage-pagination a,
  .passage-pagination span {
    font-size: 14px;
    line-height: 1.2;
  }

  /* Select2: размеры как у остальных полей фильтров */
  #passage-filters-form .select2-container {
    width: 320px !important;
    min-width: 320px !important;
  }

  #passage-filters-form .select2-selection--single {
    height: 30px !important;
    min-height: 30px !important;
  }

  #passage-filters-form .select2-selection__rendered {
    line-height: 28px !important;
    padding-left: 8px !important;
    padding-right: 24px !important;
  }

  #passage-filters-form .select2-selection__arrow {
    height: 28px !important;
    top: 1px !important;
  }

  /* Сам выпадающий список по ширине равен полю */
  .select2-container--open .select2-dropdown {
    width: 320px !important;
  }

  /* Высота списка ближе к стандартному восприятию фильтров */
  .select2-container--default .select2-results > .select2-results__options {
    max-height: 260px !important;
  }
</style>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Проходы</title>

  {{-- ваши стили шапки --}}
<link rel="stylesheet" href="{{ asset('css/header-buttons.css') }}?v={{ filemtime(public_path('css/header-buttons.css')) }}">
<link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/datetime-picker.css') }}">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>

  {{-- шапка шаблоном --}}
@include('layouts.header', ['headerTitle' => 'Галерея - Проходы сотрудников'])

@if(!empty($licenseBanner['show']))
  <div style="
    margin: 12px 16px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #fee2e2;
    border: 1px solid #fecaca;
    color: #991b1b;
    font-weight: 700;
  ">
    {{ $licenseBanner['text'] ?? 'Проблема с лицензией.' }}
  </div>
@endif


  <div style="padding: 16px;">

    {{-- Фильтры --}}
    <form id="passage-filters-form" method="GET" action="{{ route('passage.index') }}"
          style="display:flex; gap:12px; flex-wrap:wrap; align-items:end; margin-bottom:12px;">
      <div>
        <label>С</label><br>
        <input
          type="text"
          id="passage-date-from"
          name="from"
          class="dt-picker"
          data-enable-time="0"
          data-date-format="Y-m-d"
          value="{{ ($filters['from'] ?? '') ?: now()->startOfMonth()->toDateString() }}"
          placeholder="YYYY-MM-DD"
          readonly
        >
      </div>
      <div>
        <label>По</label><br>
        <input
          type="text"
          id="passage-date-to"
          name="to"
          class="dt-picker"
          data-enable-time="0"
          data-date-format="Y-m-d"
          value="{{ ($filters['to'] ?? '') ?: now()->endOfMonth()->toDateString() }}"
          placeholder="YYYY-MM-DD"
          readonly
        >
      </div>
<div>
  <label>Подразделение</label><br>
  <select id="department-select" name="department" class="js-select2-filter" style="width: 320px;">
    <option value="">Все</option>
    @foreach(($departments ?? []) as $dep)
      <option value="{{ $dep }}" @selected(($filters['department'] ?? '') === $dep)>{{ $dep }}</option>
    @endforeach
  </select>
</div>

<div>
  <label>Должность</label><br>
  <select id="position-select" name="position" class="js-select2-filter" style="width: 320px;">
    <option value="">Все</option>
    @foreach(($positions ?? []) as $pos)
      <option value="{{ $pos }}" @selected(($filters['position'] ?? '') === $pos)>{{ $pos }}</option>
    @endforeach
  </select>
</div>


      <div>
        <label>Поиск (ФИО / таб.№ / устройство)</label><br>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" style="min-width: 320px;">
      </div>
<div class="filter-item">
    <label for="break_minutes">Время на перерыв (мин)</label>
    <input
        type="number"
        id="break_minutes"
        name="break_minutes"
        min="0"
        max="240"
        value="{{ request('break_minutes', 20) }}"
        class="form-control"
    >
</div>

      <button class="ui-btn is-primary is-sm" type="submit">Применить</button>
      <a class="ui-btn is-ghost is-sm" href="{{ route('passage.index') }}">Сбросить</a>

      {{-- сохраняем сортировку при фильтрах --}}
      <input type="hidden" name="sort" value="{{ $filters['sort'] ?? '' }}">
      <input type="hidden" name="dir" value="{{ $filters['dir'] ?? '' }}">
      <input type="hidden" name="applied" value="1">
    </form>


@if(!empty($canDownloadReport))
  <a id="passage-export-excel" class="ui-btn is-primary is-sm"
     href="{{ route('admin.passages.export', request()->query()) }}">
    Скачать Excel
  </a>
@else
  <span class="ui-btn is-primary is-sm" style="opacity:.45; cursor:not-allowed;">
    Скачать Excel
  </span>
@endif



    {{-- Таблица --}}
    @if(!empty($canViewReport))
    <div style="overflow:auto; background:#fff; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.06);">
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="background:#f3f4f6;">
            @php
              $sort = $filters['sort'] ?? 'fio';
              $dir  = $filters['dir'] ?? 'asc';

              $mk = function($key, $title) use ($filters, $sort, $dir) {
                $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
                $qs = array_merge($filters, ['sort'=>$key, 'dir'=>$nextDir]);
                $arrow = ($sort === $key) ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
                return '<a href="'.e(route('passage.index', $qs)).'" style="color:inherit; text-decoration:none;">'.$title.$arrow.'</a>';
              };
            @endphp

<th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">{!! $mk('fio','ФИО') !!}</th>
<th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">{!! $mk('department','Подразделение') !!}</th>
<th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">{!! $mk('position','Должность') !!}</th>
<th style="text-align:left; padding:10px; border-bottom:1px solid #e5e7eb;">{!! $mk('worked_hours','Итог за период, ч') !!}</th>
          </tr>
        </thead>

        <tbody>
@forelse($rows as $r)
  <tr>
    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">{{ $r['fio'] }}</td>
    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">{{ $r['department'] }}</td>
    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">{{ $r['position'] }}</td>
    <td style="padding:10px; border-bottom:1px solid #f1f5f9;">{{ $r['worked_hours'] }}</td>
  </tr>
@empty
  @if(($filters['applied'] ?? '0') === '1')
    <tr>
      <td colspan="4" style="padding:14px;">Нет данных за выбранный период.</td>
    </tr>
  @endif
@endforelse
        </tbody>
      </table>
    </div>

<div class="passage-pagination" style="margin-top:12px;">
    {{ $rows->appends($filters ?? [])->links('pagination.passages') }}
</div>
@else
  <div style="
    margin-top: 12px;
    padding: 14px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e5e7eb;
    color: #111827;
  ">
    <b>Отчет недоступен.</b><br>
    Проверьте наличие лицензии или продлите её.
  </div>
@endif

  </div>

</body>
<script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ asset('js/datetime-picker.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    initDateTimePickers('.dt-picker');

    const form = document.getElementById('passage-filters-form');
    if (!form) return;

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
      const matcher = function(params, data) {
        const term = (params.term || '').toString().toLowerCase().trim();

        if (term === '') {
          return data;
        }

        if (!data.text) {
          return null;
        }

        const words = data.text
          .toString()
          .toLowerCase()
          .replace(/\s+/g, ' ')
          .trim()
          .split(' ')
          .filter(Boolean);

        const tokens = term
          .split(' ')
          .filter(Boolean);

        const ok = tokens.every((token) => words.some((word) => word.includes(token)));
        return ok ? data : null;
      };

      window.jQuery('.js-select2-filter').select2({
        width: 'resolve',
        matcher,
        minimumResultsForSearch: 0,
        placeholder: 'Выберите значение'
      });
    }

    const applyBtn = form.querySelector('button[type="submit"]');
    if (!applyBtn) return;

    const exportBtn = document.getElementById('passage-export-excel');
    if (exportBtn) {
      exportBtn.addEventListener('click', (e) => {
        e.preventDefault();

        const baseUrl = exportBtn.getAttribute('href').split('?')[0];
        const currentParams = new URLSearchParams(window.location.search);
        const finalUrl = currentParams.toString() ? `${baseUrl}?${currentParams.toString()}` : baseUrl;

        window.location.href = finalUrl;
      });
    }
  });
</script>
</html>
