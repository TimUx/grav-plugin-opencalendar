# Configuration

> Deutsch: [Konfiguration](../de/Configuration.md)

OpenCalendar is configured through `opencalendar.yaml` (plugin defaults) and `user/config/plugins/opencalendar.yaml` (site overrides). The Admin panel exposes all options via a tabbed form defined in `blueprints.yaml`.

## Configuration layers

| Layer | Path | Purpose |
|-------|------|---------|
| Defaults | `user/plugins/opencalendar/opencalendar.yaml` | Shipped defaults |
| Override | `user/config/plugins/opencalendar.yaml` | Site-specific settings |
| Environment | Environment variables (future) | Secrets and deployment-specific values |

Always override sensitive values (CalDAV passwords, API tokens) in the site config or environment, never in the plugin folder committed to git.

## General settings

```yaml
enabled: true
locale: auto          # auto | en | de
timezone: Europe/Berlin         # IANA timezone
theme: auto           # auto | light | dark
sync_interval: 15     # 5 | 10 | 15 | 30 | 60 | daily
cleanup: 30           # never | immediate | 1 | 7 | 30 | 90
```

### Cache

```yaml
cache:
  enabled: true
  ttl: 3600           # Render cache lifetime (seconds)
  parse_cache: true
  parse_ttl: 86400    # Parsed feed cache (seconds)
```

Disable caching during development to see changes immediately. In production, keep caching enabled for list and calendar views.

## Storage

```yaml
storage:
  path: data/opencalendar.db
  wal_mode: true
  vacuum_on_cleanup: false
```

Use an absolute path if the database should live outside the plugin directory (e.g. on a persistent volume).

## Sources

Each source entry supports:

| Field | Description |
|-------|-------------|
| `name` | Display name in Admin and frontend badges |
| `enabled` | Skip sync when `false` |
| `type` | `ics`, `caldav`, `json`, or `local` |
| `url` | Remote URL, plugin-relative path (e.g. `data/file.ics`), or `uploads/…` for Admin-uploaded files |
| `refresh` | `inherit` or minutes/`daily` override |
| `color` | Hex color for calendar rendering |
| `description` | Optional admin note |
| `auth` | `none`, `basic`, or `bearer` credentials |

Example disabled ICS source (included in defaults):

```yaml
sources:
  - name: Disabled Legacy Calendar
    enabled: false
    type: ics
    url: 'https://example.com/calendar.ics'
    refresh: 60
    color: '#9E9E9E'
    auth:
      type: none
```

See [Sources.md](Sources.md) for type-specific details.

Admin can also **upload** ICS/JSON files on the Synchronization tab; that creates `type: local` sources with `url: uploads/…`. See [Synchronization.md](Synchronization.md#upload-calendar-file).

## Display

Controls default calendar and list presentation. Key options:

- `display.default_view` — `calendar` or `list`
- `display.calendar.initial_view` — month, week, day, or list week
- `display.list.limit` — pagination size
- `display.event.truncate_description` — character limit (0 = no limit)

## Search and filters

```yaml
search:
  enabled: true
  min_query_length: 2
  max_results: 25
  fields: [title, description, location, categories]

filters:
  enabled: true
  show_source_filter: true
  show_category_filter: true
  show_date_range_filter: true
  persist_in_url: true
```

## API

```yaml
api:
  enabled: false
  route: /opencalendar/api
  rate_limit:
    enabled: true
    max_requests: 60
    per_minutes: 1
```

Keep the API disabled on public sites unless you add authentication at the web server or Grav level.

## Advanced

```yaml
advanced:
  debug: false
  log_level: warning
  http:
    timeout: 30
    verify_ssl: true
  scheduler:
    enabled: true
  deduplicate:
    enabled: true
  import:
    expand_recurring: true
    recurring_horizon_days: 365
```

## Admin vs YAML

All blueprint fields map 1:1 to YAML keys using dot notation for nested values (e.g. `cache.ttl` in Admin → `cache: { ttl: 3600 }` in YAML).

After editing YAML manually, clear cache:

```bash
bin/grav cache
```

## Related documentation

- [Sources.md](Sources.md) — source types and authentication
- [Caching.md](Caching.md) — cache behavior
- [API.md](API.md) — REST endpoints
