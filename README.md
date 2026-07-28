# OpenCalendar

[![CI](https://github.com/TimUx/grav-plugin-opencalendar/actions/workflows/ci.yml/badge.svg)](https://github.com/TimUx/grav-plugin-opencalendar/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

**OpenCalendar** is a Grav CMS plugin that aggregates events from ICS, CalDAV, JSON, and local sources into a unified, searchable calendar with SQLite storage, flexible display options, and optional REST API access.

## Overview

Modern sites often pull events from Google Calendar, Nextcloud, custom APIs, and static files at the same time. OpenCalendar normalizes these feeds into one queryable store and renders them through calendar views, lists, Twig templates, shortcodes, or JSON endpoints — all configurable from the Grav Admin or YAML.

Built for PHP 8.2+ with strict typing, PHPStan level 8, and PSR-12 code style.

## Features

- **Multiple source types** — ICS/iCalendar, CalDAV, JSON APIs, local files
- **SQLite storage** — fast queries, FTS search, no external database required
- **Background sync** — configurable intervals with Grav Scheduler integration
- **Dual views** — interactive calendar (month/week/day) and grouped list views
- **Search & filters** — full-text search plus source, category, and date filters
- **Twig & shortcodes** — embed anywhere in your theme or page content
- **Optional REST API** — read-only JSON with rate limiting
- **Internationalization** — English and German admin/frontend strings
- **Caching** — parse and render caches for production performance
- **Admin UI** — tabbed configuration (General, Storage, Sources, Display, Search, Filters, Advanced)

## Architecture

OpenCalendar uses a layered design: source adapters fetch and parse feeds, a sync pipeline writes to SQLite, services handle queries/search/filters, and Grav integration (Twig, shortcodes, API) sits at the edge.

```
Sources (ICS/CalDAV/JSON/Local) → Sync → SQLite → Services → Twig / Shortcodes / API
```

See [docs/Architecture.md](docs/Architecture.md) for details.

## Installation

### Requirements

- Grav 1.7.0+
- PHP 8.2+ with `pdo`, `pdo_sqlite`, `json`, `mbstring`

### GPM

```bash
bin/gpm install opencalendar
```

### Manual

```bash
git clone https://github.com/TimUx/grav-plugin-opencalendar.git user/plugins/opencalendar
cd user/plugins/opencalendar
composer install --no-dev --optimize-autoloader
bin/grav cache
```

Enable in **Admin → Plugins → OpenCalendar** or set `enabled: true` in config.

Full guide: [docs/Installation.md](docs/Installation.md)

## Updating

1. Back up `user/config/plugins/opencalendar.yaml` and `data/opencalendar.db`
2. Update plugin files (GPM or git pull)
3. Run `composer install` if dependencies changed
4. Clear cache: `bin/grav cache`

Schema migrations apply automatically. See [docs/Migration.md](docs/Migration.md).

## Configuration

Defaults live in `opencalendar.yaml`. Override in `user/config/plugins/opencalendar.yaml`:

```yaml
enabled: true
timezone: Europe/Berlin
sync_interval: 15
sources:
  - name: Team Calendar
    enabled: true
    type: ics
    url: 'https://example.com/calendar.ics'
    color: '#3788d8'
```

Configure everything from **Admin → Plugins → OpenCalendar** with tabs for General, Storage, Sources, Display, Search, Filters, and Advanced settings.

Full reference: [docs/Configuration.md](docs/Configuration.md)

## Views

| View | Description |
|------|-------------|
| **Calendar** | Month, week, day, or list-week layouts with navigation |
| **List** | Grouped chronological list with pagination |

Set default via `display.default_view` or per-page with shortcodes.

## Twig

```twig
{% set events = opencalendar_events({ from: 'now', to: '+2 months', limit: 10 }) %}
{% for event in events %}
  <article>{{ event.title }} — {{ event.start|opencalendar_format_datetime('medium') }}</article>
{% endfor %}
```

Include plugin partials or override in your theme.

Guide: [docs/Twig.md](docs/Twig.md)

## Shortcodes

```
[opencalendar view="calendar" show_filters="true" /]
[opencalendar view="list" limit="5" from="now" to="+30 days" /]
[opencalendar-search /]
```

Guide: [docs/Shortcodes.md](docs/Shortcodes.md)

## Searching

Full-text search across title, description, location, and categories. Configure in Admin or YAML:

```yaml
search:
  enabled: true
  min_query_length: 2
  max_results: 25
  highlight: true
```

Guide: [docs/Searching.md](docs/Searching.md)

## Filtering

Filter by source, category, and date range. URL persistence enables shareable filtered views.

Guide: [docs/Filtering.md](docs/Filtering.md)

## Synchronization

Remote feeds sync on a schedule (default: every 15 minutes). Per-source refresh overrides, deduplication, recurring expansion, and cleanup policies keep the database accurate without unbounded growth.

Guide: [docs/Synchronization.md](docs/Synchronization.md)

## SQLite

Events persist in `data/opencalendar.db` with WAL mode, FTS5 search, and automatic schema migrations.

Guide: [docs/SQLite.md](docs/SQLite.md)

## Caching

Parse cache avoids re-reading unchanged feeds; render cache speeds up Twig and API responses. Tune TTL for your traffic and update frequency.

Guide: [docs/Caching.md](docs/Caching.md)

## Performance

- Enable WAL mode and caching in production
- Use reasonable sync intervals (avoid polling every 5 minutes unless needed)
- Limit recurring horizon for large infinite recurrences
- Paginate list views and API responses

## REST API

Optional read-only JSON API (disabled by default):

```
GET /opencalendar/api/events?from=2026-07-01&to=2026-07-31
```

Guide: [docs/API.md](docs/API.md)

## Troubleshooting

Common issues: sync failures, empty calendars, permission errors, scheduler not running.

Guide: [docs/Troubleshooting.md](docs/Troubleshooting.md)

## FAQ

Quick answers on sources, Google Calendar, SQLite, licensing, and more.

Guide: [docs/FAQ.md](docs/FAQ.md)

## Documentation

| Document | Topic |
|----------|-------|
| [Installation](docs/Installation.md) | Setup and requirements |
| [Configuration](docs/Configuration.md) | All config options |
| [Architecture](docs/Architecture.md) | Code structure |
| [Sources](docs/Sources.md) | ICS, CalDAV, JSON, local |
| [ICS](docs/ICS.md) | iCalendar specifics |
| [Synchronization](docs/Synchronization.md) | Sync and cleanup |
| [SQLite](docs/SQLite.md) | Database storage |
| [Twig](docs/Twig.md) | Template integration |
| [Shortcodes](docs/Shortcodes.md) | Page shortcodes |
| [Searching](docs/Searching.md) | Full-text search |
| [Filtering](docs/Filtering.md) | Event filters |
| [Caching](docs/Caching.md) | Cache layers |
| [API](docs/API.md) | REST endpoints |
| [Development](docs/Development.md) | Contributor guide |
| [Migration](docs/Migration.md) | Upgrades |
| [Troubleshooting](docs/Troubleshooting.md) | Problem solving |
| [FAQ](docs/FAQ.md) | Common questions |

## Roadmap

- [x] CalDAV discovery and multi-collection sync
- [x] JSON API and local file source adapters
- [ ] Admin dashboard widget with sync status
- [ ] Webhook-triggered sync for push updates
- [ ] Export to ICS
- [ ] Additional language packs
- [ ] Event pipeline hooks for custom processing

Track progress on [GitHub Issues](https://github.com/TimUx/grav-plugin-opencalendar/issues).

## Contributing

Contributions welcome! Read [CONTRIBUTING.md](CONTRIBUTING.md), follow `composer check`, and submit a pull request.

## License

[MIT](LICENSE) — Copyright (c) 2026 TimUx

## Author

**TimUx** — [github.com/TimUx](https://github.com/TimUx)

Repository: [github.com/TimUx/grav-plugin-opencalendar](https://github.com/TimUx/grav-plugin-opencalendar)
