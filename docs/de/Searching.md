# Suche

> English: [Searching](../en/Searching.md)

OpenCalendar bietet Volltextsuche über normalisierte Terminfelder in SQLite.

## Suche aktivieren

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

Ist die Suche deaktiviert, liefern Such-UI und API-Parameter `q` `ERROR_SEARCH_DISABLED`.

## Durchsuchbare Felder

| Feld | Hinweise |
|------|----------|
| `title` | Termin-Titel (Summary) |
| `description` | Vollständiger Beschreibungstext |
| `location` | Ort oder Adresse |
| `categories` | Zusammengefügte Kategorie-Tags |

Felder aus der Liste entfernen, um sie von Indexierung und Abfragen auszuschließen.

## Frontend-Suche

Per Shortcode einbinden:

```
[opencalendar-search /]
```

Oder im vollständigen Widget aktivieren:

```
[opencalendar show_search="true" /]
```

Platzhaltertext kommt aus `PLUGIN_OPENCALENDAR.FRONTEND_SEARCH_PLACEHOLDER`.

## Abfrageverhalten

- Groß-/Kleinschreibung ignorierende Teilstring-Suche (FTS5 wenn verfügbar, LIKE-Fallback)
- Abfragen kürzer als `min_query_length` liefern `ERROR_QUERY_TOO_SHORT`
- Ergebnisse sortiert nach Relevanz, dann Startdatum
- `highlight: true` umschließt Treffer mit `<mark class="opencalendar__highlight">`

## Kombination mit Filtern

Die Suche respektiert aktive Quellen-, Kategorie- und Datumsfilter. Beispiel: Suche nach „standup“ bei Filter auf eine Quelle durchsucht nur Termine dieser Quelle.

## Twig-Nutzung

Zukünftiger Filter/Funktion `opencalendar_search(query, options)` spiegelt API-Verhalten. Bis dahin API oder Shortcodes verwenden.

## API-Suche

```
GET /opencalendar/api/events?q=standup&limit=10
```

Siehe [API.md](API.md).

## Performance

FTS5-Index wird beim Sync aktualisiert. Große Datenbanken (>50k Termine) können nach Massenimporten längere Sync-Zeiten benötigen.

Tipps:

- `max_results` für UI-Reaktionsfähigkeit angemessen halten
- Nur Felder indexieren, die tatsächlich durchsucht werden

## Sprachunterstützung

Suche ist Unicode-fähig über SQLite FTS. Deutsche Umlaute und englischer Text im gleichen Index funktionieren ohne separate Konfiguration.

## Verwandte Dokumentation

- [Filtering.md](Filtering.md)
- [SQLite.md](SQLite.md)
- [API.md](API.md)
