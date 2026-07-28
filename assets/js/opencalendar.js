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

  function formatModalDate(value, locale, timezone) {
    if (!value) {
      return '';
    }
    try {
      var d = new Date(value);
      if (Number.isNaN(d.getTime())) {
        return String(value);
      }
      var loc = isGermanLocale(locale) ? 'de-DE' : (locale || undefined);
      return new Intl.DateTimeFormat(loc, {
        timeZone: resolveTimeZone(timezone),
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
      }).format(d);
    } catch (e) {
      return String(value);
    }
  }

  function formatModalTime(value, locale, timezone) {
    if (!value) {
      return '';
    }
    try {
      var d = new Date(value);
      if (Number.isNaN(d.getTime())) {
        return String(value);
      }
      var isDe = isGermanLocale(locale);
      var parts = new Intl.DateTimeFormat(isDe ? 'de-DE' : (locale || undefined), {
        timeZone: resolveTimeZone(timezone),
        hour: '2-digit',
        minute: '2-digit',
        hour12: !isDe
      }).formatToParts(d);
      var map = {};
      parts.forEach(function (p) {
        if (p.type !== 'literal') {
          map[p.type] = p.value;
        }
      });
      var time = (map.hour || '00') + ':' + (map.minute || '00');
      if (map.dayPeriod) {
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
    var locale = config.locale && config.locale !== 'auto' ? config.locale : undefined;
    var timezone = config.timezone || undefined;

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
    set('title', event.title || '');
    set('date', formatModalDate(event.start, locale, timezone));
    set('time', allDay ? (labels.all_day || 'All day') : formatModalTime(event.start, locale, timezone));
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

  function renderListHtml(config) {
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
        + '<span class="oc-list__title">' + title + '</span>'
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
      html += '<nav class="oc-pagination" aria-label="' + escapeHtml(labels.pagination || 'Pagination')
        + '" data-oc-pagination data-oc-page="' + page + '" data-oc-pages="' + pages + '">'
        + '<button type="button" class="oc-pagination__btn" data-oc-page-goto="' + Math.max(1, page - 1) + '"'
        + (page <= 1 ? ' disabled aria-disabled="true"' : '') + '>'
        + escapeHtml(labels.previous || 'Previous') + '</button>'
        + '<span class="oc-pagination__status" data-oc-page-status>' + escapeHtml(pageLabel) + '</span>'
        + '<button type="button" class="oc-pagination__btn" data-oc-page-goto="' + Math.min(pages, page + 1) + '"'
        + (page >= pages ? ' disabled aria-disabled="true"' : '') + '>'
        + escapeHtml(labels.next || 'Next') + '</button>'
        + '</nav>';
    }

    return html;
  }

  function mapApiEvents(payload) {
    return (payload.data || []).map(function (item) {
      return {
        id: item.id,
        uid: item.uid,
        title: item.title,
        start: item.start,
        end: item.end,
        all_day: item.allDay,
        location: item.location,
        description: item.description,
        organizer: item.organizer,
        categories: item.categories,
        url: item.url,
        attachments: item.attachments,
        color: item.color,
        calendar_name: item.source && item.source.name,
        calendar_key: item.source && item.source.key,
        calendar_color: item.source && item.source.color
      };
    });
  }

  function applyEventsToConfig(config, payload) {
    config.eventsList = mapApiEvents(payload);
    config.meta = payload.meta || config.meta || {};
    config.events = config.eventsList.map(function (item) {
      return {
        id: String(item.id || item.uid),
        title: item.title,
        start: item.start,
        end: item.end,
        allDay: !!item.all_day,
        backgroundColor: item.color,
        borderColor: item.color,
        extendedProps: item
      };
    });
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

  function buildEventsUrl(config, root, page) {
    var api = config.api || {};
    var meta = config.meta || {};
    var limit = meta.limit || 50;
    var offset = Math.max(0, (Math.max(1, page) - 1) * limit);
    var params = currentFilterParams(root);
    params.set('limit', String(limit));
    params.set('offset', String(offset));
    params.delete('oc_page');
    return api.route.replace(/\/$/, '') + '/events?' + params.toString();
  }

  function navigateToPage(root, config, page) {
    var meta = config.meta || {};
    var pages = Number(meta.pages || 1);
    var nextPage = Math.max(1, Math.min(pages || 1, Number(page) || 1));
    var api = config.api || {};

    if (api.enabled && api.route) {
      root.classList.add('oc-loading');
      fetch(buildEventsUrl(config, root, nextPage), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
          applyEventsToConfig(config, payload);
          var list = root.querySelector('[data-oc-list]');
          if (list) {
            list.innerHTML = renderListHtml(config);
            initList(root, config);
            initPagination(root, config);
          }
          var params = currentFilterParams(root);
          params.set('oc_page', String(config.meta.page || nextPage));
          var query = params.toString();
          var url = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
          window.history.replaceState({}, '', url);
        })
        .catch(function () {
          /* keep current page */
        })
        .finally(function () {
          root.classList.remove('oc-loading');
        });
      return;
    }

    var params = currentFilterParams(root);
    params.set('oc_page', String(nextPage));
    window.location.search = params.toString();
  }

  function initPagination(root, config) {
    var nav = root.querySelector('[data-oc-pagination]');
    if (!nav) {
      return;
    }
    nav.querySelectorAll('[data-oc-page-goto]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.disabled) {
          return;
        }
        navigateToPage(root, config, btn.getAttribute('data-oc-page-goto'));
      });
    });
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
      var api = config.api || {};
      var isList = root.getAttribute('data-view') === 'list';

      if (!api.enabled || !api.route) {
        var params = currentFilterParams(root);
        params.delete('oc_page');
        window.location.search = params.toString();
        return;
      }

      if (isList) {
        navigateToPage(root, config, 1);
        return;
      }

      root.classList.add('oc-loading');
      fetch(buildEventsUrl(config, root, 1), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
          applyEventsToConfig(config, payload);
          var mount = root.querySelector('[data-oc-calendar]');
          if (mount) {
            mount.innerHTML = '';
            initCalendar(root, config);
          }
        })
        .catch(function () {
          /* keep current data */
        })
        .finally(function () {
          root.classList.remove('oc-loading');
        });
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
      initPagination(root, config);
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
