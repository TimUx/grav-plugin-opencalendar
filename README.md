# OpenCalendar

> Deutsch: [README.de.md](README.de.md)

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

See [docs/en/Architecture.md](docs/en/Architecture.md) for details.

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

Full guide: [docs/en/Installation.md](docs/en/Installation.md)

## Updating

1. Back up `user/config/plugins/opencalendar.yaml` and `data/opencalendar.db`
2. Update plugin files (GPM or git pull)
3. Run `composer install` if dependencies changed
4. Clear cache: `bin/grav cache`

Schema migrations apply automatically. See [docs/en/Migration.md](docs/en/Migration.md).

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

Full reference: [docs/en/Configuration.md](docs/en/Configuration.md)

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

Guide: [docs/en/Twig.md](docs/en/Twig.md)

## Shortcodes

```
[opencalendar view="calendar" show_filters="true" /]
[opencalendar view="list" limit="5" from="now" to="+30 days" /]
[opencalendar-search /]
```

Guide: [docs/en/Shortcodes.md](docs/en/Shortcodes.md)

## Searching

Full-text search across title, description, location, and categories. Configure in Admin or YAML:

```yaml
search:
  enabled: true
  min_query_length: 2
  max_results: 25
  highlight: true
```

Guide: [docs/en/Searching.md](docs/en/Searching.md)

## Filtering

Filter by source, category, and date range. URL persistence enables shareable filtered views.

Guide: [docs/en/Filtering.md](docs/en/Filtering.md)

## Synchronization

Remote feeds sync on a schedule (default: every 15 minutes). Per-source refresh overrides, deduplication, recurring expansion, and cleanup policies keep the database accurate without unbounded growth.

Guide: [docs/en/Synchronization.md](docs/en/Synchronization.md)

## SQLite

Events persist in `data/opencalendar.db` with WAL mode, FTS5 search, and automatic schema migrations.

Guide: [docs/en/SQLite.md](docs/en/SQLite.md)

## Caching

Parse cache avoids re-reading unchanged feeds; render cache speeds up Twig and API responses. Tune TTL for your traffic and update frequency.

Guide: [docs/en/Caching.md](docs/en/Caching.md)

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

Guide: [docs/en/API.md](docs/en/API.md)

## Troubleshooting

Common issues: sync failures, empty calendars, permission errors, scheduler not running.

Guide: [docs/en/Troubleshooting.md](docs/en/Troubleshooting.md)

## FAQ

Quick answers on sources, Google Calendar, SQLite, licensing, and more.

Guide: [docs/en/FAQ.md](docs/en/FAQ.md)

## Documentation

Full index (EN + DE): [docs/README.md](docs/README.md)

| Document | Topic |
|----------|-------|
| [Installation](docs/en/Installation.md) | Setup and requirements |
| [Configuration](docs/en/Configuration.md) | All config options |
| [Architecture](docs/en/Architecture.md) | Code structure |
| [Sources](docs/en/Sources.md) | ICS, CalDAV, JSON, local |
| [ICS](docs/en/ICS.md) | iCalendar specifics |
| [Synchronization](docs/en/Synchronization.md) | Sync and cleanup |
| [SQLite](docs/en/SQLite.md) | Database storage |
| [Twig](docs/en/Twig.md) | Template integration |
| [Shortcodes](docs/en/Shortcodes.md) | Page shortcodes |
| [Subscribe](docs/en/Subscribe.md) / [Abonnieren](docs/de/Subscribe.md) | Network calendar / phone subscription |
| [Documentation index](docs/README.md) | English & German docs overview |
| [Searching](docs/en/Searching.md) | Full-text search |
| [Filtering](docs/en/Filtering.md) | Event filters |
| [Caching](docs/en/Caching.md) | Cache layers |
| [API](docs/en/API.md) | REST endpoints |
| [Development](docs/en/Development.md) | Contributor guide |
| [Migration](docs/en/Migration.md) | Upgrades |
| [Troubleshooting](docs/en/Troubleshooting.md) | Problem solving |
| [FAQ](docs/en/FAQ.md) | Common questions |
| [Publishing / GPM](docs/en/Publishing-GPM.md) | Official Grav repository listing |

## Roadmap

- [x] CalDAV discovery and multi-collection sync
- [x] JSON API and local file source adapters
- [x] Admin dashboard widget with sync status
- [x] Webhook-triggered sync for push updates
- [x] Export to ICS
- [x] Additional language packs
- [x] Event pipeline hooks for custom processing

Track progress on [GitHub Issues](https://github.com/TimUx/grav-plugin-opencalendar/issues).

## Contributing

Contributions welcome! Read [CONTRIBUTING.md](CONTRIBUTING.md) ([DE](CONTRIBUTING.de.md)), follow `composer check`, and submit a pull request.

## License

[MIT](LICENSE) — Copyright (c) 2026 TimUx

## Author

**TimUx** — [github.com/TimUx](https://github.com/TimUx)

Repository: [github.com/TimUx/grav-plugin-opencalendar](https://github.com/TimUx/grav-plugin-opencalendar)
