(function () {
  'use strict';

  function config() {
    var node = document.getElementById('oc-sync-admin-config');
    if (!node) {
      return null;
    }
    try {
      return JSON.parse(node.textContent || '{}');
    } catch (e) {
      return null;
    }
  }

  function showMessage(text, type) {
    var el = document.querySelector('[data-oc-admin-message]');
    if (!el) {
      return;
    }
    el.hidden = false;
    el.textContent = text;
    el.classList.remove('is-ok', 'is-error');
    if (type) {
      el.classList.add(type);
    }
  }

  function renderTable(cfg, payload) {
    var tbody = document.querySelector('[data-oc-sync-table] tbody');
    var count = document.querySelector('[data-oc-event-count]');
    if (!tbody) {
      return;
    }

    var calendars = (payload && payload.calendars) || [];
    if (count) {
      count.textContent = String((payload && payload.event_count) || 0);
    }

    if (!calendars.length) {
      tbody.innerHTML = '<tr><td colspan="9">' + (cfg.labels.no_calendars || '—') + '</td></tr>';
      return;
    }

    tbody.innerHTML = calendars.map(function (cal) {
      var duration = cal.last_sync_duration_ms != null ? cal.last_sync_duration_ms + ' ms' : '—';
      return (
        '<tr>' +
        '<td><strong>' + escapeHtml(cal.name || '') + '</strong>' +
        '<div class="oc-sync-dashboard__key">' + escapeHtml(cal.source_key || '') + '</div></td>' +
        '<td><span class="oc-sync-status oc-sync-status--' + escapeHtml(cal.status || '') + '">' +
        escapeHtml(cal.status || '') + '</span></td>' +
        '<td>' + escapeHtml(cal.last_sync_at || '—') + '</td>' +
        '<td>' + escapeHtml(duration) + '</td>' +
        '<td>' + escapeHtml(String(cal.imported_count || 0)) + '</td>' +
        '<td>' + escapeHtml(String(cal.updated_count || 0)) + '</td>' +
        '<td>' + escapeHtml(String(cal.deleted_count || 0)) + '</td>' +
        '<td class="oc-sync-dashboard__error">' + escapeHtml(cal.last_error || '') + '</td>' +
        '<td><button type="button" class="button small" data-oc-admin-action="sync" data-oc-source="' +
        escapeAttr(cal.source_key || '') + '">' + escapeHtml(cfg.labels.columns.sync_one) + '</button></td>' +
        '</tr>'
      );
    }).join('');
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/'/g, '&#39;');
  }

  function runAction(cfg, action, source) {
    var url = cfg.base.replace(/\/$/, '') + '/' + action;
    if (source) {
      url += '?source=' + encodeURIComponent(source);
    }

    showMessage(cfg.labels.running, null);

    return fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (response) {
        return response.json().then(function (body) {
          return { ok: response.ok, body: body };
        });
      })
      .then(function (result) {
        if (!result.ok || result.body.ok === false) {
          showMessage((result.body && result.body.message) || cfg.labels.error, 'is-error');
          return;
        }

        showMessage(result.body.message || cfg.labels.ok, 'is-ok');

        if (action === 'status' || result.body.calendars) {
          renderTable(cfg, result.body);
          return;
        }

        return fetch(cfg.base.replace(/\/$/, '') + '/status', {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        })
          .then(function (r) { return r.json(); })
          .then(function (statusBody) {
            renderTable(cfg, statusBody);
          });
      })
      .catch(function () {
        showMessage(cfg.labels.error, 'is-error');
      });
  }

  function boot() {
    var cfg = config();
    if (!cfg || !cfg.base) {
      return;
    }

    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-oc-admin-action]');
      if (!button || !button.closest('.oc-sync-dashboard')) {
        return;
      }
      event.preventDefault();
      runAction(cfg, button.getAttribute('data-oc-admin-action'), button.getAttribute('data-oc-source'));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
