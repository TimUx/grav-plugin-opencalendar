# Shortcodes

> English: [Shortcodes](../en/Shortcodes.md)

OpenCalendar bietet einen Seiten-Shortcode zum Einbetten von Kalendern und Ereignislisten ohne Twig.

## Syntax

```
[opencalendar /]
```

Optionen sind quoted key/value pairs:

```
[opencalendar view="list" limit="10" sources="Team Holidays,Personal Calendar" /]
```

## `[opencalendar]`-Parameter

Alle Attribute unten sind optional. Standardwerte stammen aus der Plugin-Konfiguration (`opencalendar.yaml` / Admin).

| Attribut | Standard | Beschreibung |
|----------|----------|--------------|
| `view` | `display.default_view` | `calendar` oder `list`. Kurzformen: `month`, `week`, `day`, `agenda` (erzwingen Kalenderlayouts). |
| `calendar_view` | `display.calendar.initial_view` | Initiales Kalenderlayout: `dayGridMonth`, `timeGridWeek`, `timeGridDay`, `listWeek`. Alias: `view_mode`. |
| `limit` | `display.list.limit` | Ereignisse **pro Seite** in der Listenansicht (Seitengröße). |
| `max_events` | none (uncapped window) | Maximale **Gesamt**anzahl Ereignisse. Begrenzt die Ergebnismenge vor der Paginierung. |
| `no_pagination` | `false` | `true` blendet Seitennavigation aus. Zeigt eine Seite mit bis zu `max_events` (oder `limit`, wenn `max_events` fehlt). |
| `sources` | all enabled | Kommagetrennte Quellnamen/-keys. Aliase: `source`, `calendar`. |
| `categories` | all | Kommagetrennte Kategorienamen. |
| `from` | none | Bereichsstart (`strtotime` / relativ, z. B. `now`, `2026-08-01`). |
| `to` | none | Bereichsende (z. B. `+30 days`). |
| `show_past` | `display.list.show_past` | `true`/`false` — bereits beendete Ereignisse einschließen. Alias: `include_expired`. |
| `future_only` | `false` | `true` — nur Ereignisse mit Start in der Zukunft. |
| `sort` | `display.list.sort` | `asc` oder `desc` nach Startzeit. |
| `group_by` | `display.list.group_by` | Listen-Gruppierung: `none`, `day`, `week`, `month` oder `year`. |
| `show_filters` | `filters.enabled` | `true`/`false` — Quell-/Kategorie-/Datums-Filter-UI anzeigen. |
| `show_search` | `search.enabled` | `true`/`false` — Suchfeld anzeigen. |
| `theme` | `theme` | `auto`, `light` oder `dark`. |
| `locale` | `locale` | Locale für Datumsformatierung (`auto` folgt der Grav-Sprache). |
| `height` | `auto` | Kalenderhöhe (CSS-Wert, z. B. `600` oder `auto`). |
| `offset` | `0` | N Ereignisse überspringen (hauptsächlich für Nicht-Listen- / API-ähnliche Abfragen). |
| `show_subscribe` | `export.show_subscribe_links` | `true`/`false` — Links zum Kalender-Abonnieren anzeigen (`webcal` + ICS-URL kopieren). |

### Paginierungsregeln (Listenansicht)

1. `limit` = Seitengröße.
2. `max_events` = harte Obergrenze für Ereignisse im Widget.
3. Paginierungslinks erscheinen, wenn **mehr als eine Seite** nötig ist (`total > limit`) **und** `no_pagination` nicht gesetzt ist.
4. Wenn `max_events <= limit`, gibt es nur eine Seite → keine Paginierungs-UI.
5. Bei `no_pagination="true"` wird nie paginiert; die Liste zeigt bis zu `max_events` (oder `limit`).

## Beispiele

Monatskalender mit Filtern:

```
[opencalendar view="calendar" calendar_view="dayGridMonth" show_filters="true" /]
```

Bevorstehende Ereignisse (10 pro Seite, Vergangenheit ausgeblendet):

```
[opencalendar view="list" limit="10" from="now" to="+30 days" show_past="false" /]
```

Höchstens 5 Ereignisse, kein Pager, keine Gruppierung:

```
[opencalendar view="list" max_events="5" no_pagination="true" group_by="none" show_past="false" /]
```

Gruppiert nach Woche:

```
[opencalendar view="list" limit="20" group_by="week" from="now" show_past="false" /]
```

Bis zu 25 Ereignisse, 10 pro Seite (Paginierung sichtbar):

```
[opencalendar view="list" limit="10" max_events="25" from="now" show_past="false" /]
```

Einzelne Quelle, UI-Chrome ausblenden:

```
[opencalendar sources="Example Public Holidays" show_filters="false" show_search="false" /]
```

## Twig-Äquivalent

Dieselben Optionen funktionieren mit dem Twig-Helper:

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

## Page-Frontmatter

```yaml
title: Events
opencalendar:
  load_assets: true
  cache: false
```

Mit `cache: false` (oder Listen-Paginierung) vermeiden Sie veralteten Seiten-Cache für dynamische Listen.

## Styling

- `.opencalendar` / `.oc-root`
- `.opencalendar--list` / `.oc-list`
- `.oc-pagination` / `ul.pagination`
- `.oc-filters`

## Verwandte Dokumentation

- [Twig.md](Twig.md)
- [Searching.md](Searching.md)
- [Filtering.md](Filtering.md)
- [Subscribe.md](Subscribe.md) — Kalender auf dem Smartphone abonnieren (DE)
- [Subscribe.md](Subscribe.md) — network calendar subscription (EN)
