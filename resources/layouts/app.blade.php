<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title', 'Приложение')</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <style>
    table thead th[data-sortable="true"]{
      cursor: pointer;
      user-select: none;
      position: relative;
      padding-right: 24px;
    }

    table thead th[data-sortable="true"]::after{
      content: '↕';
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 12px;
      color: #9ca3af;
      line-height: 1;
    }

    table thead th[data-sort-direction="asc"]::after{ content: '↑'; color: #2563eb; }
    table thead th[data-sort-direction="desc"]::after{ content: '↓'; color: #2563eb; }
  </style>
</head>
<body>
  <nav>
    @auth
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Выйти</button>
      </form>
    @endauth
  </nav>
  <div class="container">
    @yield('content')
  </div>

  <script>
    (() => {
      const collator = new Intl.Collator('ru', { numeric: true, sensitivity: 'base' });

      const parseValue = (rawValue) => {
        const text = String(rawValue ?? '').replace(/\u00a0/g, ' ').trim();

        const dateMatch = text.match(/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (dateMatch) {
          const day = Number(dateMatch[1]);
          const month = Number(dateMatch[2]) - 1;
          const year = Number(dateMatch[3]);
          const hour = Number(dateMatch[4] ?? 0);
          const minute = Number(dateMatch[5] ?? 0);
          const second = Number(dateMatch[6] ?? 0);
          return { type: 'number', value: new Date(year, month, day, hour, minute, second).getTime() };
        }

        const normalizedNumber = text.replace(/\s+/g, '').replace(',', '.');
        if (/^-?\d+(?:\.\d+)?$/.test(normalizedNumber)) {
          return { type: 'number', value: Number(normalizedNumber) };
        }

        return { type: 'string', value: text.toLocaleLowerCase('ru-RU') };
      };

      const getCellSortText = (cell) => {
        if (!cell) return '';
        if (cell.dataset && cell.dataset.sortValue) return cell.dataset.sortValue;
        const control = cell.querySelector('input, select, textarea');
        if (control) return control.value ?? '';
        return cell.textContent ?? '';
      };

      const initTableSorting = () => {
        const tables = document.querySelectorAll('table');

        tables.forEach((table) => {
          const thead = table.tHead;
          const tbody = table.tBodies && table.tBodies[0];
          if (!thead || !tbody || thead.rows.length === 0) return;

          const headerRow = thead.rows[thead.rows.length - 1];
          const headers = Array.from(headerRow.cells);

          headers.forEach((th, columnIndex) => {
            const title = (th.textContent || '').trim().toLowerCase();
            if (th.classList.contains('actions-cell') || title === 'действия' || th.dataset.sortable === 'false') return;

            th.dataset.sortable = 'true';
            th.setAttribute('role', 'button');
            th.setAttribute('tabindex', '0');

            const sortColumn = (event) => {
              event.preventDefault();

              const rows = Array.from(tbody.rows).filter((row) => row.cells.length > columnIndex);
              if (rows.length < 2) return;

              const nextDirection = th.dataset.sortDirection === 'asc' ? 'desc' : 'asc';

              const prepared = rows.map((row, index) => ({
                row,
                index,
                parsed: parseValue(getCellSortText(row.cells[columnIndex]))
              }));

              prepared.sort((a, b) => {
                const left = a.parsed;
                const right = b.parsed;
                let result = 0;

                if (left.type === 'number' && right.type === 'number') {
                  result = left.value - right.value;
                } else {
                  result = collator.compare(String(left.value), String(right.value));
                }

                if (result === 0) result = a.index - b.index;
                return nextDirection === 'asc' ? result : -result;
              });

              headers.forEach((headerCell) => delete headerCell.dataset.sortDirection);
              th.dataset.sortDirection = nextDirection;
              prepared.forEach((item) => tbody.appendChild(item.row));
            };

            th.addEventListener('click', sortColumn);
            th.addEventListener('keydown', (event) => {
              if (event.key === 'Enter' || event.key === ' ') sortColumn(event);
            });
          });
        });
      };

      document.addEventListener('DOMContentLoaded', initTableSorting);
    })();
  </script>
</body>
</html>
