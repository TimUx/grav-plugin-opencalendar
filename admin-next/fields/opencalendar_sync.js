/**
 * OpenCalendar sync dashboard — Admin Next custom field (Grav 2.0).
 *
 * Blueprint: type: opencalendar_sync
 * Talks to /api/v1/opencalendar/{status,sync,rebuild,clear-cache,upload}
 */
const TAG = window.__GRAV_FIELD_TAG;

function apiUrl(path) {
  const base = window.__GRAV_API_SERVER_URL || '';
  const prefix = window.__GRAV_API_PREFIX || '/api/v1';
  return base + prefix + path;
}

function apiHeaders(json) {
  const headers = {};
  const token = window.__GRAV_API_TOKEN;
  if (token) headers['X-API-Token'] = token;
  if (json) headers['Content-Type'] = 'application/json';
  return headers;
}

async function apiCall(method, path, body, isForm) {
  const opts = { method, headers: apiHeaders(!isForm && !!body), credentials: 'same-origin' };
  if (body) opts.body = isForm ? body : JSON.stringify(body);
  const resp = await fetch(apiUrl(path), opts);
  const text = await resp.text();
  let json = {};
  try { json = text ? JSON.parse(text) : {}; } catch { json = { message: text }; }
  const data = json.data !== undefined ? json.data : json;
  if (!resp.ok || data.ok === false) {
    const msg = (json.errors && json.errors[0] && json.errors[0].detail)
      || data.message || json.detail || json.message || ('HTTP ' + resp.status);
    throw new Error(msg);
  }
  return data;
}

class OpenCalendarSyncField extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this._field = null;
    this._status = { calendars: [], event_count: 0, source_count: 0 };
    this._message = '';
    this._messageType = '';
    this._busy = false;
  }

  set field(v) { this._field = v || {}; this._render(); }
  get field() { return this._field; }
  set value(v) { /* display-only field */ }
  get value() { return null; }

  connectedCallback() {
    this._render();
    this.refresh();
  }

  async refresh() {
    try {
      this._busy = true;
      this._message = this._t('running', 'Running…');
      this._messageType = '';
      this._render();
      this._status = await apiCall('GET', '/opencalendar/status');
      this._message = this._status.message || this._t('ok', 'OK');
      this._messageType = 'ok';
    } catch (e) {
      this._message = e.message || this._t('error', 'Error');
      this._messageType = 'error';
    } finally {
      this._busy = false;
      this._render();
    }
  }

  async run(action, source) {
    try {
      this._busy = true;
      this._message = this._t('running', 'Running…');
      this._messageType = '';
      this._render();
      let path = '/opencalendar/' + action;
      if (source) path += '?source=' + encodeURIComponent(source);
      const method = action === 'status' ? 'GET' : 'POST';
      this._status = await apiCall(method, path, method === 'POST' ? {} : null);
      this._message = this._status.message || this._t('ok', 'OK');
      this._messageType = this._status.ok === false ? 'error' : 'ok';
    } catch (e) {
      this._message = e.message || this._t('error', 'Error');
      this._messageType = 'error';
    } finally {
      this._busy = false;
      this._render();
    }
  }

  async upload() {
    const fileInput = this.shadowRoot.querySelector('[data-file]');
    const nameInput = this.shadowRoot.querySelector('[data-name]');
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
      this._message = this._t('upload_missing', 'Please choose a calendar file first.');
      this._messageType = 'error';
      this._render();
      return;
    }
    const fd = new FormData();
    fd.append('calendar', fileInput.files[0]);
    if (nameInput && nameInput.value.trim()) fd.append('name', nameInput.value.trim());
    try {
      this._busy = true;
      this._message = this._t('upload_running', 'Uploading and importing…');
      this._messageType = '';
      this._render();
      this._status = await apiCall('POST', '/opencalendar/upload', fd, true);
      this._message = this._status.message || this._t('ok', 'OK');
      this._messageType = this._status.ok === false ? 'error' : 'ok';
      fileInput.value = '';
    } catch (e) {
      this._message = e.message || this._t('error', 'Error');
      this._messageType = 'error';
    } finally {
      this._busy = false;
      this._render();
    }
  }

  _t(key, fallback) {
    const labels = (this._field && this._field.labels) || {};
    return labels[key] || fallback;
  }

  _esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  _render() {
    const calendars = (this._status && this._status.calendars) || [];
    const rows = calendars.length
      ? calendars.map((cal) => {
          const duration = cal.last_sync_duration_ms != null ? cal.last_sync_duration_ms + ' ms' : '—';
          return `<tr>
            <td><strong>${this._esc(cal.name)}</strong><div class="key">${this._esc(cal.source_key)}</div></td>
            <td><span class="status status--${this._esc(cal.status)}">${this._esc(cal.status)}</span></td>
            <td>${this._esc(cal.last_sync_at || '—')}</td>
            <td>${this._esc(duration)}</td>
            <td>${this._esc(cal.imported_count || 0)}</td>
            <td>${this._esc(cal.updated_count || 0)}</td>
            <td>${this._esc(cal.deleted_count || 0)}</td>
            <td class="err">${this._esc(cal.last_error || '')}</td>
            <td><button type="button" class="btn small" data-action="sync" data-source="${this._esc(cal.source_key)}" ${this._busy ? 'disabled' : ''}>Sync</button></td>
          </tr>`;
        }).join('')
      : `<tr><td colspan="9">${this._esc(this._t('no_calendars', 'No calendars synchronized yet.'))}</td></tr>`;

    this.shadowRoot.innerHTML = `
      <style>
        :host { display: block; font-family: inherit; color: var(--foreground, inherit); }
        .help { color: var(--muted-foreground, #666); margin: 0 0 1rem; }
        .count { margin: 0 0 1rem; }
        .actions, .upload-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-end; margin-bottom: 1rem; }
        .upload { border: 1px solid var(--border, #ddd); border-radius: 4px; padding: 1rem; margin-bottom: 1rem; background: var(--muted, #fafafa); }
        .upload h3 { margin: 0 0 .5rem; font-size: 1rem; }
        label { display: flex; flex-direction: column; gap: .35rem; font-size: .85rem; flex: 1 1 12rem; }
        input[type="text"], input[type="file"] { width: 100%; }
        .btn { cursor: pointer; padding: .45rem .8rem; border-radius: 4px; border: 1px solid var(--border, #ccc); background: var(--primary, #2563eb); color: #fff; }
        .btn.secondary { background: transparent; color: var(--foreground, inherit); }
        .btn.small { padding: .25rem .5rem; font-size: .8rem; }
        .btn:disabled { opacity: .6; cursor: wait; }
        .msg { margin: 0 0 1rem; padding: .75rem 1rem; border-radius: 4px; border: 1px solid var(--border, #c5dbf5); background: var(--muted, #eef6ff); }
        .msg.ok { border-color: #b7e4c7; background: #f0fff4; }
        .msg.error { border-color: #f5c2c0; background: #fff1f0; color: #8a1f11; }
        .table-wrap { overflow-x: auto; border: 1px solid var(--border, #ddd); border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { padding: .65rem .75rem; border-bottom: 1px solid var(--border, #eee); text-align: left; vertical-align: top; }
        th { background: var(--muted, #f7f7f7); }
        .key { color: var(--muted-foreground, #888); font-size: .8rem; }
        .err { color: #8a1f11; max-width: 16rem; word-break: break-word; }
        .status { display: inline-block; padding: .15rem .45rem; border-radius: 3px; background: #eee; font-size: .8rem; }
        .status--ok, .status--success { background: #d8f3dc; }
        .status--error { background: #ffccd5; }
        .status--skipped { background: #fff3cd; }
      </style>
      <div class="dash">
        <p class="help">${this._esc(this._t('help', 'Monitor source synchronization status and run maintenance actions.'))}</p>
        <p class="count"><strong>${this._esc(this._t('events', 'Events in database'))}:</strong> ${this._esc((this._status && this._status.event_count) || 0)}</p>
        <div class="actions">
          <button type="button" class="btn" data-action="sync" ${this._busy ? 'disabled' : ''}>${this._esc(this._t('sync', 'Synchronize now'))}</button>
          <button type="button" class="btn" data-action="rebuild" ${this._busy ? 'disabled' : ''}>${this._esc(this._t('rebuild', 'Rebuild database'))}</button>
          <button type="button" class="btn" data-action="clear-cache" ${this._busy ? 'disabled' : ''}>${this._esc(this._t('clear_cache', 'Clear cache'))}</button>
          <button type="button" class="btn secondary" data-action="status" ${this._busy ? 'disabled' : ''}>${this._esc(this._t('refresh', 'Refresh status'))}</button>
        </div>
        <div class="upload">
          <h3>${this._esc(this._t('upload_title', 'Upload calendar file'))}</h3>
          <p class="help">${this._esc(this._t('upload_help', 'Upload an .ics / .ical or .json calendar. Stored under user/data/opencalendar/uploads and imported immediately.'))}</p>
          <div class="upload-row">
            <label><span>${this._esc(this._t('upload_name', 'Source name'))}</span>
              <input type="text" data-name placeholder="${this._esc(this._t('upload_name_ph', 'e.g. Club calendar'))}" /></label>
            <label><span>${this._esc(this._t('upload_file', 'Calendar file'))}</span>
              <input type="file" data-file accept=".ics,.ical,.json,text/calendar,application/json" /></label>
            <button type="button" class="btn" data-upload ${this._busy ? 'disabled' : ''}>${this._esc(this._t('upload_submit', 'Upload and import'))}</button>
          </div>
        </div>
        ${this._message ? `<div class="msg ${this._messageType}">${this._esc(this._message)}</div>` : ''}
        <div class="table-wrap"><table><thead><tr>
          <th>Source</th><th>Status</th><th>Last sync</th><th>Duration</th><th>Imported</th><th>Updated</th><th>Deleted</th><th>Error</th><th></th>
        </tr></thead><tbody>${rows}</tbody></table></div>
      </div>
    `;

    this.shadowRoot.querySelectorAll('[data-action]').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const action = btn.getAttribute('data-action');
        const source = btn.getAttribute('data-source') || '';
        if (action === 'status') this.refresh();
        else this.run(action, source);
      });
    });
    const uploadBtn = this.shadowRoot.querySelector('[data-upload]');
    if (uploadBtn) uploadBtn.addEventListener('click', (e) => { e.preventDefault(); this.upload(); });
  }
}

customElements.define(TAG, OpenCalendarSyncField);
