@php
    $baseQuery = request()->except(['cursor', 'edge']);
    $newestUrl = route('all.passages', array_merge($baseQuery, ['edge' => 'newest']));
    $oldestUrl = route('all.passages', array_merge($baseQuery, ['edge' => 'oldest']));
@endphp

<nav class="passage-pagination" aria-label="Навигация по фото проходов">
    <div class="passage-pagination__summary">Показано до {{ count($paginator) }} фото.</div>
    <ul class="passage-pagination__list">
        <li class="passage-pagination__item"><a class="passage-pagination__page" href="{{ $newestUrl }}">В начало</a></li>

        @if ($paginator->previousPageUrl())
            <li class="passage-pagination__item"><a class="passage-pagination__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">« Предыдущая</a></li>
        @else
            <li class="passage-pagination__item is-disabled"><span class="passage-pagination__btn">« Предыдущая</span></li>
        @endif

        @if ($paginator->nextPageUrl())
            <li class="passage-pagination__item"><a class="passage-pagination__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Следующая »</a></li>
        @else
            <li class="passage-pagination__item is-disabled"><span class="passage-pagination__btn">Следующая »</span></li>
        @endif

        <li class="passage-pagination__item"><a class="passage-pagination__page" href="{{ $oldestUrl }}">В конец</a></li>
    </ul>
</nav>

<style>
    .passage-pagination { margin-top:12px; }
    .passage-pagination__summary { margin:6px 0; color:#6b7280; font-size:12px; }
    .passage-pagination__list { display:flex; flex-wrap:wrap; gap:6px; margin:0; padding:0; list-style:none; }
    .passage-pagination__btn, .passage-pagination__page { display:inline-flex; align-items:center; justify-content:center; min-width:10px; height:32px; padding:0 10px; border:1px solid #d1d5db; border-radius:8px; background:#fff; color:#1f2937; text-decoration:none; font-size:13px; }
    .passage-pagination__btn:hover, .passage-pagination__page:hover { background:#f3f4f6; }
    .passage-pagination__item.is-disabled .passage-pagination__btn { opacity:.45; cursor:not-allowed; }
</style>
