# SQLite Storage

OpenCalendar persists normalized events in SQLite for fast queries, search, and resilience when remote feeds are unavailable.

## Database location

Default path: `user/data/opencalendar/opencalendar.db`

Configure via:

```yaml
storage:
  path: user-data://opencalendar/opencalendar.db
```

`user-data://` resolves to Grav’s writable `user/data/` directory (recommended).

Legacy plugin-relative paths still work:

```yaml
storage:
  path: data/opencalendar.db
```

Those require the web server user (e.g. `www-data`) to own `user/plugins/opencalendar/data/`. Absolute paths are also supported for custom volumes.

## Schema overview

The database includes tables for:

| Table | Purpose |
|-------|---------|
| `sources` | Registered source metadata and sync state |
| `events` | Normalized event rows with start/end, text fields |
| `event_sources` | Many-to-many link when deduplication merges feeds |
| `categories` | Category tags per event |
| `sync_log` | Historical sync runs for debugging |
| `schema_migrations` | Applied migration versions |

Full-text search uses SQLite FTS5 on title, description, and location when available.

## WAL mode

Write-Ahead Logging (`storage.wal_mode: true`) is enabled by default:

- Readers do not block writers
- Better concurrency for busy sites
- Creates `-wal` and `-shm` companion files alongside the database

Disable only if your hosting environment has known WAL issues (rare).

## Migrations

Schema changes ship as numbered migrations in `classes/Storage/Migrations/`. Migrations run automatically on plugin init or first sync.

Never edit the database manually in production unless following a documented recovery procedure.

## Backups

Include the database in your site backup strategy:

```bash
sqlite3 user/plugins/opencalendar/data/opencalendar.db ".backup backup/opencalendar-$(date +%F).db"
```

Or copy the file while Grav is idle / plugin disabled.

## Maintenance

### Cleanup

Soft-deleted rows are purged per `cleanup` policy. See [Synchronization.md](Synchronization.md).

### VACUUM

When `storage.vacuum_on_cleanup` is enabled, SQLite reclaims free pages after cleanup. This can lock the database briefly — schedule during low traffic.

Manual vacuum:

```bash
sqlite3 user/plugins/opencalendar/data/opencalendar.db "VACUUM;"
```

### Integrity check

```bash
sqlite3 user/plugins/opencalendar/data/opencalendar.db "PRAGMA integrity_check;"
```

## Permissions

The web server user needs read/write on the database file and directory:

```bash
chmod 775 user/plugins/opencalendar/data
chown www-data:www-data user/plugins/opencalendar/data/opencalendar.db
```

## Size expectations

Rough estimates:

| Events | Approximate size |
|--------|------------------|
| 1,000 | 1–2 MB |
| 10,000 | 10–20 MB |
| 100,000 | 100+ MB |

Recurring expansion increases row count significantly — tune `recurring_horizon_days`.

## Recovery

If the database is corrupted:

1. Disable the plugin.
2. Rename or remove the corrupt file.
3. Re-enable — a fresh schema is created on next init.
4. Trigger full sync to repopulate.

## Related documentation

- [Architecture.md](Architecture.md)
- [Synchronization.md](Synchronization.md)
- [Migration.md](Migration.md)
