# Twig-Integration

> English: [Twig Integration](../en/Twig.md)

OpenCalendar stellt Twig-Funktionen und -Filter bereit, um Ereignisse in Theme-Vorlagen darzustellen.

## Aktivierung in Vorlagen

Stellen Sie sicher, dass das Plugin aktiviert ist und Ereignisse synchronisiert wurden. Vorlagen liegen in Ihrem Theme oder können Plugin-Partials aus `templates/partials/` erweitern.

## Funktionen

### `opencalendar_events(options)`

Liefert eine Sammlung von Ereignis-DTOs für die angegebenen Abfrageoptionen.

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

#### Optionen für `opencalendar()` / Listen-Hilfsfunktionen

| Option | Typ | Beschreibung |
|--------|-----|--------------|
| `view` | string | `calendar` oder `list` (auch `month`/`week`/`day`/`agenda`) |
| `calendar_view` | string | Initiales Kalenderlayout (`dayGridMonth`, …) |
| `from` | string\|DateTime | Bereichsstart (`strtotime`-Syntax) |
| `to` | string\|DateTime | Bereichsende |
| `sources` / `source` | string\|array | Nach Quellname/-key filtern |
| `categories` | string\|array | Nach Kategorie filtern |
| `limit` | int | Ereignisse pro Seite (Liste) |
| `max_events` | int | Harte Obergrenze für angezeigte Ereignisse gesamt |
| `no_pagination` | bool | Listen-Paginierung ausblenden; eine Seite bis `max_events`/`limit` |
| `show_past` | bool | Beendete Ereignisse einschließen (`include_expired`-Alias) |
| `future_only` | bool | Nur zukünftige Starts |
| `sort` | `asc`\|`desc` | Nach Startzeit sortieren |
| `group_by` | string | Listen-Gruppierung: `none`, `day`, `week`, `month`, `year` |
| `show_filters` | bool | Filter-UI ein-/ausblenden |
| `show_search` | bool | Suchfeld ein-/ausblenden |
| `theme` | string | `auto` / `light` / `dark` |
| `locale` | string | Datums-Locale |
| `height` | string\|int | Kalenderhöhe |

Vollständige Attributliste und Paginierungsregeln: [Shortcodes.md](Shortcodes.md).


### `opencalendar_sources()`

Liefert konfigurierte Quellen mit Metadaten (Name, Farbe, Aktivierungsstatus).

```twig
{% for source in opencalendar_sources() %}
  <span class="badge" style="background: {{ source.color }}">{{ source.name }}</span>
{% endfor %}
```

## Filter

### `opencalendar_format_datetime`

Formatiert ein Ereignis-Datum/-Zeit unter Berücksichtigung von Plugin-Locale und -Zeitzone.

```twig
{{ event.start|opencalendar_format_datetime('full') }}
```

Formate: `short`, `medium`, `full`, `time_only`, `date_only`.

### `opencalendar_truncate`

Kürzt Beschreibungstext gemäß `display.event.truncate_description`.

```twig
{{ event.description|opencalendar_truncate }}
```

## Plugin-Partials einbinden

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

Partials respektieren globale Konfigurations-Standards; übergebene Variablen überschreiben nur für dieses Rendering.

## Theme-Integration

Plugin-Vorlagen überschreiben, indem Sie sie ins Theme kopieren:

```
user/themes/your-theme/templates/partials/opencalendar/
```

Grav löst Theme-Vorlagen vor Plugin-Vorlagen auf.

## Assets

Kalender-Assets in Ihrem Page-Frontmatter registrieren oder Partials automatisch laden lassen:

```yaml
opencalendar:
  load_assets: true
  theme: dark
```

CSS-/JS-Dateien liegen unter `assets/css` und `assets/js`.

## Übersetzungsschlüssel

Sprachstrings in eigenen Vorlagen verwenden:

```twig
{{ 'PLUGIN_OPENCALENDAR.FRONTEND_NO_EVENTS'|t }}
```

Verfügbare Schlüssel: `languages/*.yaml` (`en`, `de`, `fr`, `es`, `nl`, `it`).


## Caching

Twig-Ausgabe kann vom Grav-Seiten-Cache gecacht werden. Ereignissammlungen nutzen den Plugin-Render-Cache (`cache.ttl`). Cache nach Sync leeren für sofortige Updates:

```bash
bin/grav cache
```

## Verwandte Dokumentation

- [Shortcodes.md](Shortcodes.md)
- [Views](../../README.de.md#views)
- [Caching.md](Caching.md)
