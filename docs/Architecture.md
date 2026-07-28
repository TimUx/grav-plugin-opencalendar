# Architecture

OpenCalendar follows a layered architecture designed for Grav CMS integration, testability, and incremental source-type support.

## High-level overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Grav CMS (Pages, Twig)                   │
├─────────────────────────────────────────────────────────────┤
│  opencalendar.php  │  Twig extensions  │  Shortcodes        │
├─────────────────────────────────────────────────────────────┤
│              Controllers / API (HTTP boundary)               │
├─────────────────────────────────────────────────────────────┤
│     Services (EventQuery, Search, Filter, SyncOrchestrator)  │
├─────────────────────────────────────────────────────────────┤
│   Source adapters (ICS, CalDAV, JSON, Local)  │  Sync jobs   │
├─────────────────────────────────────────────────────────────┤
│        Storage (SQLite repository, migrations)               │
├─────────────────────────────────────────────────────────────┤
│   Models / DTOs / Enums (domain types, no I/O)               │
└─────────────────────────────────────────────────────────────┘
```

## Directory layout

| Path | Responsibility |
|------|----------------|
| `opencalendar.php` | Grav plugin entry, event hook registration |
| `classes/Source/` | Feed adapters implementing a common source interface |
| `classes/Sync/` | Sync pipeline, scheduling hooks, cleanup |
| `classes/Storage/` | SQLite access, schema migrations, repositories |
| `classes/Services/` | Business logic orchestrating storage and sources |
| `classes/Models/` | Persistent domain entities (Event, Source, etc.) |
| `classes/Dto/` | Immutable data transfer objects for API/Twig |
| `classes/Enum/` | Typed enumerations (SourceType, CleanupPolicy, …) |
| `classes/Controllers/` | Admin actions and frontend route handlers |
| `classes/Api/` | JSON API serializers and route definitions |
| `templates/` | Twig templates and partials |
| `assets/` | CSS, JS, vendored frontend libraries |
| `data/` | Default SQLite location (gitignored at runtime) |

## Namespace

All PHP classes live under:

```
Grav\Plugin\OpenCalendar\
```

PSR-4 autoloading maps `classes/` to this namespace via Composer.

## Data flow: synchronization

1. Scheduler or manual trigger starts a sync job.
2. `SyncOrchestrator` loads enabled sources from config.
3. Each **source adapter** fetches raw payload (HTTP, CalDAV, filesystem).
4. Parser normalizes events into `Event` models (using Sabre VObject for ICS).
5. Storage layer upserts events, tracks deletions, applies deduplication rules.
6. Render cache keys are invalidated for affected sources.

## Data flow: frontend render

1. Page request hits Twig template or shortcode handler.
2. Service layer queries SQLite with filters (date range, source, category).
3. Results map to DTOs for Twig (no raw database rows in templates).
4. Optional render cache returns pre-built collections when TTL allows.

## Key design decisions

### SQLite as canonical store

Remote feeds are normalized into a local SQLite database for fast querying, full-text search, and offline resilience. See [SQLite.md](SQLite.md).

### Source adapter pattern

Each source type implements a shared interface:

- `fetch()` — retrieve raw data
- `parse()` — produce normalized events
- `getLastModified()` — support conditional requests

New source types add an adapter without changing storage or display code.

### Configuration-driven behavior

Runtime behavior is driven by `opencalendar.yaml` with no hard-coded feed URLs. Admin blueprint and YAML stay in sync.

### Grav integration points

| Grav hook | Usage |
|-----------|-------|
| `onPluginsInitialized` | Register services, routes |
| `onTwigExtensions` | Add filters/functions |
| `onPageInitialized` | Shortcode processing |
| `onSchedulerInitialized` | Register sync/cleanup jobs |
| `onAssetsInitialized` | Register CSS/JS |

## Dependencies

| Package | Role |
|---------|------|
| `sabre/vobject` | ICS/iCalendar parsing and validation |

Grav core provides HTTP client utilities, caching, logging, and Twig.

## Extension points

- **Custom source types** — implement adapter + register in source factory
- **Twig filters** — documented in [Twig.md](Twig.md)
- **Event pipeline hooks** — future hooks before/after import (planned)

## Testing strategy

- **Unit tests** — models, parsers, query builders (no Grav bootstrap)
- **Integration tests** — SQLite repository against fixture ICS files
- **Manual QA** — Admin forms, frontend views in a Grav instance

Run `composer check` before every pull request.

## Related documentation

- [Synchronization.md](Synchronization.md)
- [Sources.md](Sources.md)
- [Development.md](Development.md)
