# Twig Integration

OpenCalendar exposes Twig functions and filters for rendering events in theme templates.

## Enabling in templates

Ensure the plugin is enabled and events are synchronized. Templates live in your theme or can extend plugin partials from `templates/partials/`.

## Functions

### `opencalendar_events(options)`

Returns a collection of event DTOs for the given query options.

```twig
{% set events = opencalendar_events({
  from: 'now',
  to: '+3 months',
  sources: ['Team Holidays'],
  limit: 20
}) %}

<ul class="upcoming-events">
  {% for event in events %}
    <li>
      <strong>{{ event.title }}</strong>
      — {{ event.start|date('M j, Y') }}
      {% if event.location %}
        <span class="location">{{ event.location }}</span>
      {% endif %}
    </li>
  {% endfor %}
</ul>
```

#### Options for `opencalendar()` / list helpers

| Option | Type | Description |
|--------|------|-------------|
| `view` | string | `calendar` or `list` (also `month`/`week`/`day`/`agenda`) |
| `calendar_view` | string | Initial calendar layout (`dayGridMonth`, …) |
| `from` | string\|DateTime | Range start (`strtotime` syntax) |
| `to` | string\|DateTime | Range end |
| `sources` / `source` | string\|array | Filter by source name/key |
| `categories` | string\|array | Filter by category |
| `limit` | int | Events per page (list) |
| `max_events` | int | Hard cap on total events shown |
| `no_pagination` | bool | Hide list pagination; single page up to `max_events`/`limit` |
| `show_past` | bool | Include ended events (`include_expired` alias) |
| `future_only` | bool | Only future starts |
| `sort` | `asc`\|`desc` | Sort by start time |
| `group_by` | string | List grouping: `none`, `day`, `week`, `month`, `year` |
| `show_filters` | bool | Toggle filter UI |
| `show_search` | bool | Toggle search box |
| `theme` | string | `auto` / `light` / `dark` |
| `locale` | string | Date locale |
| `height` | string\|int | Calendar height |

See [Shortcodes.md](Shortcodes.md) for the full attribute list and pagination rules.


### `opencalendar_sources()`

Returns configured sources with metadata (name, color, enabled state).

```twig
{% for source in opencalendar_sources() %}
  <span class="badge" style="background: {{ source.color }}">{{ source.name }}</span>
{% endfor %}
```

## Filters

### `opencalendar_format_datetime`

Formats an event datetime respecting plugin locale and timezone settings.

```twig
{{ event.start|opencalendar_format_datetime('full') }}
```

Formats: `short`, `medium`, `full`, `time_only`, `date_only`.

### `opencalendar_truncate`

Truncates description text per `display.event.truncate_description`.

```twig
{{ event.description|opencalendar_truncate }}
```

## Including plugin partials

```twig
{% include 'partials/opencalendar/calendar.html.twig' with {
  view: 'dayGridMonth',
  height: 600
} %}
```

```twig
{% include 'partials/opencalendar/list.html.twig' with {
  group_by: 'month',
  show_past: false
} %}
```

Partials respect global config defaults; passed variables override for that render only.

## Theme integration

Override plugin templates by copying to your theme:

```
user/themes/your-theme/templates/partials/opencalendar/
```

Grav resolves theme templates before plugin templates.

## Assets

Register calendar assets in your page front matter or let partials auto-load:

```yaml
opencalendar:
  load_assets: true
  theme: dark
```

CSS/JS files ship under `assets/css` and `assets/js`.

## Translation keys

Use language strings in custom templates:

```twig
{{ 'PLUGIN_OPENCALENDAR.FRONTEND_NO_EVENTS'|t }}
```

See `languages/*.yaml` (`en`, `de`, `fr`, `es`, `nl`, `it`) for available keys.


## Caching

Twig output may be cached by Grav's page cache. Event collections use plugin render cache (`cache.ttl`). Clear cache after sync for immediate updates:

```bash
bin/grav cache
```

## Related documentation

- [Shortcodes.md](Shortcodes.md)
- [Views](../README.md#views)
- [Caching.md](Caching.md)
