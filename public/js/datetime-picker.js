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

    const ruLocale = (window.flatpickr.l10ns && window.flatpickr.l10ns.ru) ? window.flatpickr.l10ns.ru : {
      firstDayOfWeek: 1,
      weekdays: {
        shorthand: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
        longhand: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота']
      },
      months: {
        shorthand: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
        longhand: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь']
      },
      rangeSeparator: ' — ',
      weekAbbreviation: 'Нед.',
      scrollTitle: 'Прокрутите для увеличения',
      toggleTitle: 'Нажмите для переключения',
      time_24hr: true
    };

    document.querySelectorAll(selector).forEach((el) => {
      const min = el.getAttribute('data-min') || null;
      const max = el.getAttribute('data-max') || null;
      const enableTimeAttr = el.getAttribute('data-enable-time');
      const dateFormatAttr = el.getAttribute('data-date-format');
      const enableTime = enableTimeAttr === null ? true : (enableTimeAttr === '1' || enableTimeAttr === 'true');
      const dateFormat = dateFormatAttr || (enableTime ? 'Y-m-d H:i:S' : 'Y-m-d');

      const fp = window.flatpickr(el, {
        locale: ruLocale,
        enableTime,
        time_24hr: true,
        seconds: enableTime,
        allowInput: true,
        dateFormat,
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
