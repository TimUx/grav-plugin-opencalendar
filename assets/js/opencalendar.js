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

  function formatDate(value, allDay) {
    if (!value) {
      return '';
    }
    try {
      var d = new Date(value);
      if (Number.isNaN(d.getTime())) {
        return String(value);
      }
      if (allDay) {
        return d.toLocaleDateString(undefined, { dateStyle: 'full' });
      }
      return d.toLocaleString(undefined, { dateStyle: 'full', timeStyle: 'short' });
    } catch (e) {
      return String(value);
    }
  }

  function openModal(root, event) {
    var modal = root.querySelector('[data-oc-modal]');
    if (!modal || !event) {
      return;
    }
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
          a.textContent = att.filename || att.uri || 'Attachment';
          li.appendChild(a);
          el.appendChild(li);
        });
        return;
      }
      el.textContent = value || '—';
    };

    set('title', event.title || '');
    set('date', formatDate(event.start, event.all_day || event.allDay));
    set('time', event.all_day || event.allDay ? 'All day' : formatDate(event.start, false));
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

    EventCalendar.create(mount, {
      view: initialView,
      headerToolbar: {
        start: 'prev,next today',
        center: 'title',
        end: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },
      firstDay: typeof calendarOpts.first_day === 'number' ? calendarOpts.first_day : 1,
      height: 'auto',
      events: config.events || [],
      locale: config.locale && config.locale !== 'auto' ? config.locale : undefined,
          weekNumbers: !!calendarOpts.week_numbers,
          weekends: calendarOpts.weekends !== false,
          height: calendarOpts.height || 'auto',
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
        openModal(root, event);
      }
    });
  }

  function initList(root, config) {
    root.querySelectorAll('[data-oc-event-id]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var event = findEvent(config, btn.getAttribute('data-oc-event-id'));
        openModal(root, event);
      });
    });
  }

  function initFilters(root, config) {
    var form = root.querySelector('[data-oc-filters]');
    if (!form) {
      return;
    }

    var apply = function () {
      var q = (form.querySelector('[data-oc-search]') || {}).value || '';
      var source = (form.querySelector('[data-oc-filter-source]') || {}).value || '';
      var category = (form.querySelector('[data-oc-filter-category]') || {}).value || '';
      var api = config.api || {};
      if (!api.enabled || !api.route) {
        return;
      }

      var url = api.route.replace(/\/$/, '') + '/events?limit=' + encodeURIComponent((config.meta && config.meta.limit) || 50);
      if (q) {
        url += '&q=' + encodeURIComponent(q);
      }
      if (source) {
        url += '&source=' + encodeURIComponent(source);
      }
      if (category) {
        url += '&category=' + encodeURIComponent(category);
      }

      fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
          config.eventsList = (payload.data || []).map(function (item) {
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

          var list = root.querySelector('[data-oc-list]');
          if (list) {
            window.location.search = new URLSearchParams({
              q: q,
              source: source,
              category: category
            }).toString();
          } else {
            var mount = root.querySelector('[data-oc-calendar]');
            if (mount) {
              mount.innerHTML = '';
              initCalendar(root, config);
            }
          }
        })
        .catch(function () {
          /* keep current data */
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
