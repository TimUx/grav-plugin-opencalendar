# Caching

OpenCalendar uses multiple cache layers to keep calendar pages fast without hammering remote feeds.

## Cache layers

```
Remote feed → Parse cache → SQLite → Render cache → Grav page cache → Browser
```

| Layer | Config | Purpose |
|-------|--------|---------|
| Parse cache | `cache.parse_cache`, `cache.parse_ttl` | Avoid re-parsing identical ICS/JSON payloads |
| Render cache | `cache.enabled`, `cache.ttl` | Cache query results and Twig-ready collections |
| Grav cache | Grav core | Full page cache when enabled in site config |

## Render cache

```yaml
cache:
  enabled: true
  ttl: 3600
```

- TTL in seconds; `0` invalidates on next sync
- Keys include source list, filter hash, and locale
- Invalidated automatically after successful sync affecting included sources

### Bypassing render cache

Per-page in front matter:

```yaml
opencalendar:
  cache: false
```

Or disable globally during development:

```yaml
cache:
  enabled: false
```

## Parse cache

```yaml
cache:
  parse_cache: true
  parse_ttl: 86400
```

Stores hashed raw payloads and parsed intermediate structures. Useful when:

- Multiple sources share identical feeds
- Sync interval is short but feed content changes infrequently

Disable if debugging parser issues.

## Invalidation triggers

Render and parse caches clear on:

- Successful sync completing with changes
- Manual cache clear (`bin/grav cache`) when `advanced.scheduler.on_cache_clear` is true
- Plugin config save in Admin (implementation)

They do **not** clear on failed sync — stale data is preferred over empty calendars.

## Grav page cache interaction

If Grav page caching is enabled site-wide, calendar pages may appear static until Grav cache expires. Options:

1. Disable page cache for event pages via front matter
2. Use shorter Grav cache lifetime for dynamic routes
3. Rely on AJAX/API for client-side refresh (advanced)

## HTTP caching (API)

When the REST API is enabled, responses include:

- `Cache-Control: private, max-age=<ttl>`
- `ETag` for conditional requests

See [API.md](API.md).

## Performance tuning

| Scenario | Recommendation |
|----------|----------------|
| High traffic, stable feeds | `ttl: 3600` or higher |
| Frequently updated feeds | Lower sync interval, moderate TTL |
| Development | Disable all plugin caches |
| Many recurring events | Parse cache on, reasonable horizon |

## Monitoring cache effectiveness

Enable debug logging temporarily:

```yaml
advanced:
  debug: true
  log_level: debug
```

Log entries indicate cache hits/misses during render and sync.

## Related documentation

- [Synchronization.md](Synchronization.md)
- [Configuration.md](Configuration.md)
- [Architecture.md](Architecture.md)
