# Shortcodes

OpenCalendar provides a page shortcode for embedding calendars and event lists without writing Twig.

## Syntax

```
[opencalendar /]
```

Options are quoted key/value pairs:

```
[opencalendar view="list" limit="10" sources="Team Holidays,Personal Calendar" /]
```

## `[opencalendar]` parameters

All attributes below are optional. Defaults come from plugin config (`opencalendar.yaml` / Admin).

| Attribute | Default | Description |
|-----------|---------|-------------|
| `view` | `display.default_view` | `calendar` or `list`. Shortcuts: `month`, `week`, `day`, `agenda` (force calendar layouts). |
| `calendar_view` | `display.calendar.initial_view` | Initial calendar layout: `dayGridMonth`, `timeGridWeek`, `timeGridDay`, `listWeek`. Alias: `view_mode`. |
| `limit` | `display.list.limit` | Events **per page** in list view (page size). |
| `max_events` | none (uncapped window) | Maximum **total** events to include. Caps the result set before paging. |
| `no_pagination` | `false` | `true` hides page navigation. Shows a single page of up to `max_events` (or `limit` if `max_events` is omitted). |
| `sources` | all enabled | Comma-separated source names/keys. Aliases: `source`, `calendar`. |
| `categories` | all | Comma-separated category names. |
| `from` | none | Range start (`strtotime` / relative, e.g. `now`, `2026-08-01`). |
| `to` | none | Range end (e.g. `+30 days`). |
| `show_past` | `display.list.show_past` | `true`/`false` — include events that already ended. Alias: `include_expired`. |
| `future_only` | `false` | `true` — only events starting in the future. |
| `sort` | `display.list.sort` | `asc` or `desc` by start time. |
| `show_filters` | `filters.enabled` | `true`/`false` — show source/category/date filter UI. |
| `show_search` | `search.enabled` | `true`/`false` — show search box. |
| `theme` | `theme` | `auto`, `light`, or `dark`. |
| `locale` | `locale` | Locale for date formatting (`auto` follows Grav language). |
| `height` | `auto` | Calendar height (CSS value, e.g. `600` or `auto`). |
| `offset` | `0` | Skip N events (mainly for non-list / API-style queries). |

### Pagination rules (list view)

1. `limit` = page size.
2. `max_events` = hard cap on how many events enter the widget.
3. Pagination links appear when **more than one page** is needed (`total > limit`) **and** `no_pagination` is not set.
4. If `max_events <= limit`, there is only one page → no pagination UI.
5. If `no_pagination="true"`, pagination is never shown; the list shows up to `max_events` (or `limit`).

## Examples

Month calendar with filters:

```
[opencalendar view="calendar" calendar_view="dayGridMonth" show_filters="true" /]
```

Upcoming events (10 per page, past hidden):

```
[opencalendar view="list" limit="10" from="now" to="+30 days" show_past="false" /]
```

At most 5 events, no pager:

```
[opencalendar view="list" max_events="5" no_pagination="true" show_past="false" /]
```

Up to 25 events, 10 per page (pagination shown):

```
[opencalendar view="list" limit="10" max_events="25" from="now" show_past="false" /]
```

Single source, hide chrome:

```
[opencalendar sources="Example Public Holidays" show_filters="false" show_search="false" /]
```

## Twig equivalent

The same options work with the Twig helper:

```twig
{{ opencalendar({
  view: 'list',
  limit: 10,
  max_events: 25,
  no_pagination: false,
  show_past: false,
  from: 'now',
  to: '+30 days'
}) }}
```

## Page front matter

```yaml
title: Events
opencalendar:
  load_assets: true
  cache: false
```

Setting `cache: false` (or using list pagination) avoids stale page cache for dynamic lists.

## Styling

- `.opencalendar` / `.oc-root`
- `.opencalendar--list` / `.oc-list`
- `.oc-pagination` / `ul.pagination`
- `.oc-filters`

## Related documentation

- [Twig.md](Twig.md)
- [Searching.md](Searching.md)
- [Filtering.md](Filtering.md)
