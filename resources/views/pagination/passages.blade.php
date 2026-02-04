@if ($paginator->hasPages())
    <nav class="passage-pagination" role="navigation" aria-label="Pagination Navigation">
        <div class="passage-pagination__summary">
            {{ $paginator->firstItem() }} - {{ $paginator->lastItem() }} из {{ $paginator->total() }} 

        </div>

        <ul class="passage-pagination__list">
            {{-- Prev --}}
            @if ($paginator->onFirstPage())
                <li class="passage-pagination__item is-disabled" aria-disabled="true">
                    <span class="passage-pagination__btn" title="Previous">
                        {{-- chevron-left --}}
                        <svg viewBox="0 0 20 20" fill="currentColor" class="passage-pagination__icon" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.78 15.53a.75.75 0 0 1-1.06 0l-5-5a.75.75 0 0 1 0-1.06l5-5a.75.75 0 1 1 1.06 1.06L8.31 10l4.47 4.47a.75.75 0 0 1 0 1.06z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                </li>
            @else
                <li class="passage-pagination__item">
                    <a class="passage-pagination__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" title="Previous">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="passage-pagination__icon" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.78 15.53a.75.75 0 0 1-1.06 0l-5-5a.75.75 0 0 1 0-1.06l5-5a.75.75 0 1 1 1.06 1.06L8.31 10l4.47 4.47a.75.75 0 0 1 0 1.06z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                </li>
            @endif

            {{-- Pages --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="passage-pagination__item is-disabled" aria-disabled="true">
                        <span class="passage-pagination__dots">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="passage-pagination__item is-active" aria-current="page">
                                <span class="passage-pagination__page">{{ $page }}</span>
                            </li>
                        @else
                            <li class="passage-pagination__item">
                                <a class="passage-pagination__page" href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li class="passage-pagination__item">
                    <a class="passage-pagination__btn" href="{{ $paginator->nextPageUrl() }}" rel="next" title="Next">
                        {{-- chevron-right --}}
                        <svg viewBox="0 0 20 20" fill="currentColor" class="passage-pagination__icon" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.22 4.47a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 0 1-1.06-1.06L11.69 10 7.22 5.53a.75.75 0 0 1 0-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                </li>
            @else
                <li class="passage-pagination__item is-disabled" aria-disabled="true">
                    <span class="passage-pagination__btn" title="Next">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="passage-pagination__icon" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.22 4.47a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 0 1-1.06-1.06L11.69 10 7.22 5.53a.75.75 0 0 1 0-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                </li>
            @endif
        </ul>
    </nav>

    <style>
        .passage-pagination { margin-top: 12px; }
        .passage-pagination__summary { font-size: 12px; color: #6b7280; margin: 6px 0; }
        .passage-pagination__list { list-style: none; padding: 0; margin: 0; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

        .passage-pagination__btn,
        .passage-pagination__page {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 10px;
            height: 20px;
            padding: 0 10px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #1f2937;
            text-decoration: none;
            font-size: 13px;
            line-height: 1;
        }

        .passage-pagination__btn:hover,
        .passage-pagination__page:hover {
            background: #f3f4f6;
        }

        .passage-pagination__icon { width: 16px; height: 16px; }

        .passage-pagination__item.is-active .passage-pagination__page {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
            cursor: default;
        }

        .passage-pagination__item.is-disabled .passage-pagination__btn,
        .passage-pagination__item.is-disabled .passage-pagination__page,
        .passage-pagination__dots {
            opacity: .45;
            cursor: not-allowed;
            user-select: none;
        }

        /* защита от глобальных правил svg { width:100% } */
        .passage-pagination svg { max-width: 16px; max-height: 16px; }
    </style>
@endif
