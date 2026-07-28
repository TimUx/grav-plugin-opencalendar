# Synchronization

OpenCalendar keeps a local SQLite copy of events synchronized with configured sources. This document explains sync triggers, intervals, error handling, and cleanup.

## Sync triggers

Events are refreshed by:

1. **Grav Scheduler** — recommended for production (see `advanced.scheduler.enabled`)
2. **Cache clear hook** — optional sync when Grav cache is cleared (`advanced.scheduler.on_cache_clear`)
3. **Manual CLI command** — for operations and debugging (implementation pending)
4. **Admin action** — force sync for a single source (implementation pending)

## Sync intervals

Global interval is set by `sync_interval`:

| Value | Behavior |
|-------|----------|
| `5`, `10`, `15`, `30`, `60` | Run every N minutes |
| `daily` | Run once per day (midnight site timezone) |

Per-source override via `sources[].refresh`:

- `inherit` — use global interval
- Any interval value — override for that source only

Staggered scheduling prevents all sources from hitting remote servers simultaneously when intervals align.

## Sync pipeline

```
Trigger → Load config → For each enabled source:
    → Fetch (HTTP/CalDAV/file)
    → Parse → Normalize events
    → Upsert into SQLite
    → Mark missing events as deleted
→ Apply deduplication
→ Invalidate caches
→ Log summary
```

### Fetch behavior

HTTP options come from `advanced.http`:

- `timeout` — maximum seconds per request
- `verify_ssl` — reject invalid certificates when `true`
- `user_agent` — sent with outbound requests
- `max_redirects` — follow redirects up to this limit

Authentication per source uses `auth.type`:

- `none` — no credentials
- `basic` — HTTP Basic (`username` / `password`)
- `bearer` — `Authorization: Bearer` header in password field

### Parse and import

- ICS feeds parsed with Sabre VObject
- Recurring events expanded when `advanced.import.expand_recurring` is `true`
- Horizon controlled by `advanced.import.recurring_horizon_days`
- HTML in descriptions optionally stripped via `advanced.import.strip_html`

### Deduplication

When `advanced.deduplicate.enabled` is `true`, events matching configured fields (default: `uid`, `start`, `end`) from different sources collapse to one row, preserving multiple source associations.

## Deletions and cleanup

When an event disappears from a feed:

1. It is marked deleted (soft delete) with a timestamp.
2. Cleanup policy (`cleanup`) determines permanent removal:

| Policy | Effect |
|--------|--------|
| `never` | Soft-deleted rows kept indefinitely |
| `immediate` | Hard delete on next sync |
| `1`, `7`, `30`, `90` | Hard delete after N days |

Optional `storage.vacuum_on_cleanup` runs SQLite `VACUUM` after cleanup batches.

## Error handling

Failed sources do not block other sources. Errors are logged to `advanced.log_file` at `advanced.log_level`.

Common failure modes:

| Symptom | Likely cause |
|---------|--------------|
| HTTP 403/401 | Missing or wrong credentials |
| HTTP 404 | Invalid feed URL |
| Parse error | Malformed ICS/JSON |
| Storage error | Permissions or disk full |

Frontend continues serving last successful sync data until TTL expires.

## Monitoring

Enable `advanced.debug` temporarily to log fetch URLs (redacted credentials), response codes, and parse counts.

Check logs:

```bash
tail -f logs/opencalendar.log
```

## Performance tips

- Use per-source refresh overrides for slow CalDAV servers
- Enable `cache.parse_cache` to skip re-parsing unchanged payloads
- Keep `recurring_horizon_days` reasonable (365 default)

## Related documentation

- [Sources.md](Sources.md)
- [Caching.md](Caching.md)
- [Troubleshooting.md](Troubleshooting.md)
