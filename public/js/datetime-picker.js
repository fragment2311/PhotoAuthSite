// Требует подключенный flatpickr (глобально: window.flatpickr)
(function () {
  function clamp(n, min, max) { return Math.max(min, Math.min(max, n)); }

  function attachWheel(calendarEl, fp) {
    // Колесо: без Shift — месяц, с Shift — год
    // Ctrl — ускорение
    calendarEl.addEventListener('wheel', (e) => {
      e.preventDefault();

      const dir = e.deltaY > 0 ? 1 : -1;
      const step = e.ctrlKey ? 3 : 1;

      const d = new Date(fp.currentYear, fp.currentMonth, 1);

      if (e.shiftKey) {
        d.setFullYear(d.getFullYear() + dir * step);
      } else {
        d.setMonth(d.getMonth() + dir * step);
      }

      fp.changeYear(d.getFullYear());
      fp.changeMonth(d.getMonth(), false);
    }, { passive: false });
  }

  window.initDateTimePickers = function initDateTimePickers(selector = '.dt-picker') {
    if (!window.flatpickr) return;

    document.querySelectorAll(selector).forEach((el) => {
      const min = el.getAttribute('data-min') || null;
      const max = el.getAttribute('data-max') || null;

      const fp = window.flatpickr(el, {
        enableTime: true,
        time_24hr: true,
        seconds: true,
        allowInput: true,
        dateFormat: 'Y-m-d H:i:S',
        defaultDate: el.value ? el.value : null,
        minDate: min,
        maxDate: max,
        onReady: function (_, __, instance) {
          attachWheel(instance.calendarContainer, instance);
        }
      });
    });
  };
})();
