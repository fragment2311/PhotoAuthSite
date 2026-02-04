<div class="spreadsheet">
    <div class="sheet">
        @isset($title)
            <h2 class="sheet-title">{{ $title }}</h2>
        @endisset

        <div class="table-wrap">
            <table>
                <thead>
                    {{ $thead }}
                </thead>
                <tbody>
                    {{ $tbody }}
                </tbody>
            </table>
        </div>
    </div>
</div>
