# Filtering

OpenCalendar provides frontend filters to narrow events by source, category, and date range.

## Enabling filters

```yaml
filters:
  enabled: true
  show_source_filter: true
  show_category_filter: true
  show_date_range_filter: true
  persist_in_url: true
  default_sources: []
  default_categories: []
```

When `enabled` is `false`, filter UI is hidden but programmatic filters (Twig, API) still work.

## Filter types

### Source filter

Limits events to one or more configured sources. Source names match the `name` field in configuration (case-sensitive).

Useful when a page shortcode should show a subset without listing sources in the attribute:

```yaml
filters:
  default_sources:
    - Example Public Holidays
```

### Category filter

Categories originate from ICS `CATEGORIES` properties or JSON feed fields. The filter shows a multi-select of distinct values present in the database.

### Date range filter

Visitors pick start and end dates. Defaults:

- Start: today (or page `from` attribute)
- End: +3 months (or page `to` attribute)

## URL persistence

When `persist_in_url: true`, applied filters serialize to query parameters:

```
/events?oc_sources=Team+Calendar&oc_from=2026-07-01&oc_to=2026-07-31
```

This enables shareable filtered views. Parameter prefix `oc_` avoids collisions with other plugins.

## Twig and shortcode filters

Pass filters inline without UI:

```twig
{% set events = opencalendar_events({
  sources: ['Team Holidays'],
  categories: ['holiday'],
  from: '2026-01-01',
  to: '2026-12-31'
}) %}
```

```
[opencalendar sources="Team Holidays" from="2026-01-01" to="2026-12-31" show_filters="false" /]
```

## API filters

See [API.md](API.md) — `source`, `category`, `from`, and `to` query parameters.

## Interaction with search

Filters and search combine with AND logic: results must match active filters **and** the search query.

## Empty results

When no events match, the UI shows the translated `FRONTEND_NO_EVENTS` message. Verify:

- Source names spelled correctly
- Date range includes event dates
- Events synced successfully

## Accessibility

Filter controls use native form elements with labels from language files. Keyboard users can tab through and apply with Enter.

## Related documentation

- [Searching.md](Searching.md)
- [Shortcodes.md](Shortcodes.md)
- [Configuration.md](Configuration.md)
