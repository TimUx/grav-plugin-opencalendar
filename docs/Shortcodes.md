# Shortcodes

OpenCalendar provides page shortcodes for embedding calendars and event lists without writing Twig.

## Syntax

Grav shortcodes use square brackets:

```
[opencalendar /]
```

Options are passed as key=value pairs:

```
[opencalendar view="list" limit="10" sources="Team Holidays,Personal Calendar" /]
```

## `[opencalendar]`

Embeds the full calendar or list widget.

| Attribute | Default | Description |
|-----------|---------|-------------|
| `view` | config default | `calendar` or `list` |
| `calendar_view` | `dayGridMonth` | Initial calendar layout |
| `limit` | config default | Max events (list view) |
| `sources` | all enabled | Comma-separated source names |
| `categories` | all | Comma-separated categories |
| `from` | today | Start date (strtotime) |
| `to` | +3 months | End date |
| `show_filters` | config default | Enable filter UI |
| `show_search` | config default | Enable search box |
| `theme` | config default | `auto`, `light`, or `dark` |
| `height` | auto | Calendar height in pixels |

### Examples

Month calendar with filters:

```
[opencalendar view="calendar" calendar_view="dayGridMonth" show_filters="true" /]
```

Upcoming events list:

```
[opencalendar view="list" limit="5" from="now" to="+30 days" show_past="false" /]
```

Single source:

```
[opencalendar sources="Example Public Holidays" /]
```

## `[opencalendar-event]`

Renders a single event by UID.

```
[opencalendar-event uid="abc123@example.com" /]
```

| Attribute | Required | Description |
|-----------|----------|-------------|
| `uid` | Yes | Event UID from ICS or API |
| `show_description` | config | Show full description |
| `show_location` | config | Show location line |

## `[opencalendar-search]`

Standalone search box that filters events on the same page.

```
[opencalendar-search placeholder="Find events…" /]
```

Requires `search.enabled: true` in config.

## Page front matter

Combine shortcodes with page-level options:

```yaml
title: Events
opencalendar:
  load_assets: true
  cache: false
```

Setting `cache: false` on a page bypasses render cache for dynamic event pages.

## Markdown vs HTML

Shortcodes work in Markdown page content and HTML modules. In modular pages, place shortcodes in the module body.

## Styling

Shortcodes emit BEM-style classes:

- `.opencalendar`
- `.opencalendar--list`
- `.opencalendar__event`
- `.opencalendar__filters`

Override in your theme SCSS/CSS.

## Accessibility

Rendered widgets include:

- Keyboard navigation for calendar views
- ARIA labels from language files
- Focus management in filter dialogs

## Related documentation

- [Twig.md](Twig.md)
- [Searching.md](Searching.md)
- [Filtering.md](Filtering.md)
