# Searching

> Deutsch: [Suche](../de/Searching.md)

OpenCalendar provides full-text search across normalized event fields stored in SQLite.

## Enabling search

```yaml
search:
  enabled: true
  min_query_length: 2
  max_results: 25
  fields:
    - title
    - description
    - location
    - categories
  highlight: true
```

When disabled, search UI and API `q` parameter return `ERROR_SEARCH_DISABLED`.

## Searchable fields

| Field | Notes |
|-------|-------|
| `title` | Event summary |
| `description` | Full description text |
| `location` | Venue or address |
| `categories` | Joined category tags |

Remove fields from the list to exclude them from indexing and queries.

## Frontend search

Embed via shortcode:

```
[opencalendar-search /]
```

Or enable on full widget:

```
[opencalendar show_search="true" /]
```

Placeholder text comes from `PLUGIN_OPENCALENDAR.FRONTEND_SEARCH_PLACEHOLDER`.

## Query behavior

- Case-insensitive substring match (FTS5 when available, LIKE fallback)
- Queries shorter than `min_query_length` show `ERROR_QUERY_TOO_SHORT`
- Results sorted by relevance then start date
- `highlight: true` wraps matches in `<mark class="opencalendar__highlight">`

## Combining with filters

Search respects active source, category, and date filters. Example: searching "standup" while filtering to one source only searches within that source's events.

## Twig usage

Future filter/function `opencalendar_search(query, options)` will mirror API behavior. Until then, use API or shortcodes.

## API search

```
GET /opencalendar/api/events?q=standup&limit=10
```

See [API.md](API.md).

## Performance

FTS5 index updates on sync. Large databases (>50k events) may need longer sync times after bulk imports.

Tips:

- Keep `max_results` reasonable for UI responsiveness
- Index only fields you actually search

## Language support

Search is Unicode-aware via SQLite FTS. German umlauts and English text in the same index work without separate configuration.

## Related documentation

- [Filtering.md](Filtering.md)
- [SQLite.md](SQLite.md)
- [API.md](API.md)
