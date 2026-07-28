(function () {
  'use strict';

  function parseConfig(root) {
    var id = root.getAttribute('data-config');
    var node = id ? document.getElementById(id) : null;
    if (!node) {
      return null;
    }
    try {
      return JSON.parse(node.textContent || '{}');
    } catch (e) {
      return null;
    }
  }

  function i18n(config) {
    return config.i18n || {};
  }

  function findEvent(config, id) {
    var list = config.eventsList || [];
    for (var i = 0; i < list.length; i++) {
      var item = list[i];
      if (String(item.id) === String(id) || String(item.uid) === String(id)) {
        return item;
      }
    }
    var calEvents = config.events || [];
    for (var j = 0; j < calEvents.length; j++) {
      var ev = calEvents[j];
      if (String(ev.id) === String(id)) {
        return Object.assign({}, ev.extendedProps || {}, {
          id: ev.id,
          title: ev.title,
          start: ev.start,
          end: ev.end,
          all_day: ev.allDay,
          color: ev.backgroundColor
        });
      }
    }
    return null;
  }

  function pad2(n) {
    return String(n).padStart(2, '0');
  }

  function resolveTimeZone(timezone) {
    var tz = timezone || 'Europe/Berlin';
    if (String(tz).toUpperCase() === 'UTC') {
      return 'Europe/Berlin';
    }
    return tz;
  }

  function isGermanLocale(locale) {
    return String(locale || '').toLowerCase().indexOf('de') === 0;
  }

  function resolveLocale(config, root) {
    var locale = config && config.locale ? String(config.locale) : '';
    if (!locale || locale === 'auto') {
      locale = (root && root.getAttribute('data-locale')) || '';
    }
    if (!locale || locale === 'auto') {
      locale = document.documentElement.lang || navigator.language || 'de';
    }
    return locale;
  }

  function formatPartsMap(value, locale, timezone, options) {
    var d = new Date(value);
    if (Number.isNaN(d.getTime())) {
      return null;
    }
    var loc = isGermanLocale(locale) ? 'de-DE' : (locale || undefined);
    var opts = Object.assign({ timeZone: resolveTimeZone(timezone) }, options || {});
    var map = {};
    new Intl.DateTimeFormat(loc, opts).formatToParts(d).forEach(function (p) {
      if (p.type !== 'literal') {
        map[p.type] = p.value;
      }
    });
    map.__date = d;
    return map;
  }

  function formatModalDate(value, locale, timezone) {
    if (!value) {
      return '';
    }
    try {
      var isDe = isGermanLocale(locale);
      var map = formatPartsMap(value, locale, timezone, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
      });
      if (!map) {
        return String(value);
      }
      if (isDe) {
        // Explicit German date only — never include time ("um …").
        return (map.weekday || '') + ', ' + (map.day || '') + '. ' + (map.month || '') + ' ' + (map.year || '');
      }
      return new Intl.DateTimeFormat(locale || undefined, {
        timeZone: resolveTimeZone(timezone),
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
      }).format(map.__date);
    } catch (e) {
      return String(value);
    }
  }

  function formatModalTime(value, locale, timezone) {
    if (!value) {
      return '';
    }
    try {
      var isDe = isGermanLocale(locale);
      var map = formatPartsMap(value, locale, timezone, {
        hour: '2-digit',
        minute: '2-digit',
        hour12: !isDe
      });
      if (!map) {
        return String(value);
      }
      var hour = map.hour || '00';
      var minute = map.minute || '00';
      // Some locales return hour="19 Uhr" — keep digits only.
      hour = String(hour).replace(/[^\d]/g, '') || '00';
      minute = String(minute).replace(/[^\d]/g, '') || '00';
      if (hour.length < 2) {
        hour = pad2(hour);
      }
      if (minute.length < 2) {
        minute = pad2(minute);
      }
      var time = hour + ':' + minute;
      if (!isDe && map.dayPeriod) {
        time += ' ' + map.dayPeriod;
      }
      return isDe ? (time + ' Uhr') : time;
    } catch (e) {
      return String(value);
    }
  }

  function formatListWhen(value, allDay, locale, timezone) {
    if (!value) {
      return '';
    }
    try {
      var d = new Date(value);
      if (Number.isNaN(d.getTime())) {
        return String(value);
      }
      var isDe = isGermanLocale(locale);
      var tz = resolveTimeZone(timezone);
      var dateParts = new Intl.DateTimeFormat(isDe ? 'de-DE' : (locale || undefined), {
        timeZone: tz,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
      }).formatToParts(d);
      var map = {};
      dateParts.forEach(function (p) {
        if (p.type !== 'literal') {
          map[p.type] = p.value;
        }
      });
      var date = isDe
        ? ((map.day || pad2(d.getDate())) + '.' + (map.month || pad2(d.getMonth() + 1)) + '.' + (map.year || d.getFullYear()))
        : ((map.year || d.getFullYear()) + '-' + (map.month || pad2(d.getMonth() + 1)) + '-' + (map.day || pad2(d.getDate())));
      if (allDay) {
        return date;
      }
      var time = formatModalTime(value, locale, timezone);
      return date + ' ' + time;
    } catch (e) {
      return String(value);
    }
  }

  function openModal(root, config, event) {
    var modal = root.querySelector('[data-oc-modal]');
    if (!modal || !event) {
      return;
    }
    var labels = i18n(config);
    var locale = resolveLocale(config, root);
    var timezone = config.timezone || root.getAttribute('data-timezone') || 'Europe/Berlin';

    var set = function (name, value) {
      var el = modal.querySelector('[data-oc-field="' + name + '"]');
      if (!el) {
        return;
      }
      if (el.tagName === 'A') {
        if (value) {
          el.href = value;
          el.textContent = value;
          el.hidden = false;
        } else {
          el.removeAttribute('href');
          el.textContent = '';
          el.hidden = true;
        }
        return;
      }
      if (name === 'attachments') {
        el.innerHTML = '';
        (value || []).forEach(function (att) {
          var li = document.createElement('li');
          var a = document.createElement('a');
          a.href = att.uri || '#';
          a.target = '_blank';
          a.rel = 'noopener noreferrer';
          a.textContent = att.filename || att.uri || labels.attachment || 'Attachment';
          li.appendChild(a);
          el.appendChild(li);
        });
        return;
      }
      el.textContent = value || '—';
    };

    var allDay = !!(event.all_day || event.allDay);
    var startValue = event.start || event.startAt || '';
    var displayDate = event.display_date || (event.extendedProps && event.extendedProps.display_date) || '';
    var displayTime = event.display_time || (event.extendedProps && event.extendedProps.display_time) || '';
    set('title', event.title || '');
    set('date', displayDate || formatModalDate(startValue, locale, timezone));
    set(
      'time',
      allDay
        ? (labels.all_day || 'Ganztägig')
        : (displayTime || formatModalTime(startValue, locale, timezone))
    );
    set('location', event.location || '');
    set('organizer', event.organizer || '');
    set('calendar', event.calendar_name || (event.calendar && event.calendar.name) || event.calendar || '');
    set('categories', Array.isArray(event.categories) ? event.categories.join(', ') : '');
    set('description', event.description || '');
    set('url', event.url || '');
    set('attachments', event.attachments || []);

    modal.hidden = false;
    var dialog = modal.querySelector('.oc-modal__dialog');
    if (dialog) {
      dialog.focus();
    }
  }

  function closeModal(root) {
    var modal = root.querySelector('[data-oc-modal]');
    if (modal) {
      modal.hidden = true;
    }
  }

  function initCalendar(root, config) {
    var mount = root.querySelector('[data-oc-calendar]');
    if (!mount || typeof EventCalendar === 'undefined') {
      return;
    }

    var initialView = mount.getAttribute('data-initial-view') || 'dayGridMonth';
    var calendarOpts = config.calendar || {};
    var labels = i18n(config);

    EventCalendar.create(mount, {
      view: initialView,
      headerToolbar: {
        start: 'prev,next today',
        center: 'title',
        end: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },
      buttonText: {
        today: labels.today || 'Today',
        dayGridMonth: labels.month || 'Month',
        timeGridWeek: labels.week || 'Week',
        timeGridDay: labels.day || 'Day',
        listWeek: labels.list || 'List'
      },
      firstDay: typeof calendarOpts.first_day === 'number' ? calendarOpts.first_day : 1,
      height: calendarOpts.height || 'auto',
      events: config.events || [],
      locale: config.locale && config.locale !== 'auto' ? config.locale : undefined,
      weekNumbers: !!calendarOpts.week_numbers,
      weekends: calendarOpts.weekends !== false,
      nowIndicator: calendarOpts.show_now_indicator !== false,
      eventClick: function (info) {
        info.jsEvent.preventDefault();
        var event = findEvent(config, info.event.id);
        if (!event) {
          event = {
            id: info.event.id,
            title: info.event.title,
            start: info.event.start,
            end: info.event.end,
            all_day: info.event.allDay,
            description: info.event.extendedProps && info.event.extendedProps.description,
            location: info.event.extendedProps && info.event.extendedProps.location,
            organizer: info.event.extendedProps && info.event.extendedProps.organizer,
            categories: info.event.extendedProps && info.event.extendedProps.categories,
            url: info.event.extendedProps && info.event.extendedProps.url,
            attachments: info.event.extendedProps && info.event.extendedProps.attachments,
            calendar: info.event.extendedProps && info.event.extendedProps.calendar
          };
        }
        openModal(root, config, event);
      }
    });
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatListGroup(value, locale, timezone) {
    if (!value) {
      return '—';
    }
    try {
      var d = new Date(value);
      if (Number.isNaN(d.getTime())) {
        return String(value).slice(0, 7);
      }
      return new Intl.DateTimeFormat(isGermanLocale(locale) ? 'de-DE' : (locale || undefined), {
        timeZone: resolveTimeZone(timezone),
        month: 'long',
        year: 'numeric'
      }).format(d);
    } catch (e) {
      return String(value).slice(0, 7);
    }
  }

  function renderListHtml(config, root) {
    var labels = i18n(config);
    var events = config.eventsList || [];
    var meta = config.meta || {};
    var locale = config.locale;
    var timezone = config.timezone;
    var html = '';

    if (!events.length) {
      return '<p class="oc-list__empty">' + escapeHtml(labels.no_events || 'No events found.') + '</p>';
    }

    var currentGroup = null;
    events.forEach(function (event) {
      var start = event.start || '';
      var group = formatListGroup(start, locale, timezone);
      if (group !== currentGroup) {
        if (currentGroup !== null) {
          html += '</ul>';
        }
        html += '<h3 class="oc-list__group">' + escapeHtml(group) + '</h3><ul class="oc-list__items">';
        currentGroup = group;
      }
      var color = escapeHtml(event.color || '#3788d8');
      var title = escapeHtml(event.title || '');
      var location = escapeHtml(event.location || '');
      var id = escapeHtml(event.id || event.uid || '');
      var when = escapeHtml(formatListWhen(start, !!(event.all_day || event.allDay), locale, timezone));
      html += '<li class="oc-list__item" style="--oc-event-color:' + color + '">'
        + '<button type="button" class="oc-list__button" data-oc-event-id="' + id + '">'
        + '<span class="oc-list__icon" aria-hidden="true">📅</span>'
        + '<span class="oc-list__body">'
        + '<span class="oc-list__when">' + when + '</span>'
        + '<strong class="oc-list__title">' + title + '</strong>'
        + (location ? '<span class="oc-list__location">' + location + '</span>' : '')
        + '</span>'
        + '</button></li>';
    });
    if (currentGroup !== null) {
      html += '</ul>';
    }

    var page = Number(meta.page || 1);
    var pages = Number(meta.pages || 1);
    if (pages > 1) {
      var pageLabel = String(labels.page || 'Page %1 of %2')
        .replace('%1', String(page))
        .replace('%2', String(pages));
      var prevPage = Math.max(1, page - 1);
      var nextPage = Math.min(pages, page + 1);
      var prevHref = buildPageHref(root, prevPage);
      var nextHref = buildPageHref(root, nextPage);
      html += '<nav class="oc-pagination" aria-label="' + escapeHtml(labels.pagination || 'Pagination')
        + '" data-oc-pagination data-oc-page="' + page + '" data-oc-pages="' + pages + '">';
      if (page <= 1) {
        html += '<span class="oc-pagination__btn oc-pagination__btn--disabled" aria-disabled="true">'
          + escapeHtml(labels.previous || 'Previous') + '</span>';
      } else {
        html += '<a class="oc-pagination__btn" href="' + escapeHtml(prevHref) + '">'
          + escapeHtml(labels.previous || 'Previous') + '</a>';
      }
      html += '<span class="oc-pagination__status" data-oc-page-status>' + escapeHtml(pageLabel) + '</span>';
      if (page >= pages) {
        html += '<span class="oc-pagination__btn oc-pagination__btn--disabled" aria-disabled="true">'
          + escapeHtml(labels.next || 'Next') + '</span>';
      } else {
        html += '<a class="oc-pagination__btn" href="' + escapeHtml(nextHref) + '">'
          + escapeHtml(labels.next || 'Next') + '</a>';
      }
      html += '</nav>';
    }

    return html;
  }

  function buildPageHref(root, page) {
    var params = currentFilterParams(root);
    params.set('oc_page', String(Math.max(1, page || 1)));
    var query = params.toString();
    return window.location.pathname + (query ? '?' + query : '') + window.location.hash;
  }

  function currentFilterParams(root) {
    var form = root.querySelector('[data-oc-filters]');
    var params = new URLSearchParams(window.location.search);
    if (!form) {
      return params;
    }
    var q = (form.querySelector('[data-oc-search]') || {}).value || '';
    var source = (form.querySelector('[data-oc-filter-source]') || {}).value || '';
    var category = (form.querySelector('[data-oc-filter-category]') || {}).value || '';
    if (q) {
      params.set('q', q);
    } else {
      params.delete('q');
    }
    if (source) {
      params.set('source', source);
    } else {
      params.delete('source');
    }
    if (category) {
      params.set('category', category);
    } else {
      params.delete('category');
    }
    return params;
  }

  function initList(root, config) {
    root.querySelectorAll('[data-oc-event-id]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var event = findEvent(config, btn.getAttribute('data-oc-event-id'));
        openModal(root, config, event);
      });
    });
  }

  function initFilters(root, config) {
    var form = root.querySelector('[data-oc-filters]');
    if (!form) {
      return;
    }

    var apply = function () {
      var params = currentFilterParams(root);
      params.delete('oc_page');
      window.location.search = params.toString();
    };

    form.addEventListener('change', apply);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      apply();
    });
  }

  function initRoot(root) {
    var config = parseConfig(root);
    if (!config) {
      return;
    }

    if (!config.locale || config.locale === 'auto') {
      config.locale = root.getAttribute('data-locale') || 'en';
    }

    root.querySelectorAll('[data-oc-modal-close]').forEach(function (el) {
      el.addEventListener('click', function () { closeModal(root); });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeModal(root);
      }
    });

    if (root.getAttribute('data-view') === 'list') {
      initList(root, config);
    } else {
      initCalendar(root, config);
    }
    initFilters(root, config);
  }

  function boot() {
    document.querySelectorAll('[data-oc-root]').forEach(initRoot);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
