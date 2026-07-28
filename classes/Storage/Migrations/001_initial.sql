-- OpenCalendar initial schema
CREATE TABLE IF NOT EXISTS calendars (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_key TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    type TEXT NOT NULL,
    url TEXT,
    enabled INTEGER NOT NULL DEFAULT 1,
    color TEXT,
    description TEXT,
    refresh TEXT NOT NULL DEFAULT 'inherit',
    etag TEXT,
    last_modified TEXT,
    content_hash TEXT,
    last_sync_at TEXT,
    last_success_at TEXT,
    last_sync_duration_ms INTEGER,
    last_http_status INTEGER,
    last_error TEXT,
    status TEXT NOT NULL DEFAULT 'idle',
    imported_count INTEGER NOT NULL DEFAULT 0,
    updated_count INTEGER NOT NULL DEFAULT 0,
    deleted_count INTEGER NOT NULL DEFAULT 0,
    config_json TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendar_id INTEGER NOT NULL,
    uid TEXT NOT NULL,
    recurrence_id TEXT NOT NULL DEFAULT '',
    title TEXT NOT NULL,
    description TEXT,
    location TEXT,
    organizer TEXT,
    url TEXT,
    status TEXT,
    categories_json TEXT NOT NULL DEFAULT '[]',
    color TEXT,
    attachments_json TEXT NOT NULL DEFAULT '[]',
    start_at TEXT NOT NULL,
    end_at TEXT,
    all_day INTEGER NOT NULL DEFAULT 0,
    timezone TEXT,
    is_recurring INTEGER NOT NULL DEFAULT 0,
    rrule TEXT,
    content_hash TEXT,
    deleted_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (calendar_id) REFERENCES calendars(id) ON DELETE CASCADE,
    UNIQUE (calendar_id, uid, recurrence_id)
);

CREATE INDEX IF NOT EXISTS idx_events_start_at ON events(start_at);
CREATE INDEX IF NOT EXISTS idx_events_end_at ON events(end_at);
CREATE INDEX IF NOT EXISTS idx_events_calendar_id ON events(calendar_id);
CREATE INDEX IF NOT EXISTS idx_events_deleted_at ON events(deleted_at);
CREATE INDEX IF NOT EXISTS idx_events_uid ON events(uid);

CREATE TABLE IF NOT EXISTS sync_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    calendar_id INTEGER,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    status TEXT NOT NULL,
    imported_count INTEGER NOT NULL DEFAULT 0,
    updated_count INTEGER NOT NULL DEFAULT 0,
    deleted_count INTEGER NOT NULL DEFAULT 0,
    http_status INTEGER,
    duration_ms INTEGER,
    message TEXT,
    FOREIGN KEY (calendar_id) REFERENCES calendars(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_sync_log_calendar_id ON sync_log(calendar_id);
CREATE INDEX IF NOT EXISTS idx_sync_log_started_at ON sync_log(started_at);

CREATE VIRTUAL TABLE IF NOT EXISTS events_fts USING fts5(
    title,
    description,
    location,
    organizer,
    categories,
    calendar_name
);
