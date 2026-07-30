# Filter

> English: [Filtering](../en/Filtering.md)

OpenCalendar bietet Frontend-Filter, um Termine nach Quelle, Kategorie und Datumsbereich einzugrenzen.

## Filter aktivieren

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

Ist `enabled` `false`, ist die Filter-UI ausgeblendet; programmatische Filter (Twig, API) funktionieren weiterhin.

## Filtertypen

### Quellenfilter

Begrenzt Termine auf eine oder mehrere konfigurierte Quellen. Quellennamen entsprechen dem Feld `name` in der Konfiguration (Groß-/Kleinschreibung beachten).

Nützlich, wenn ein Seiten-Shortcode eine Teilmenge zeigen soll, ohne Quellen im Attribut aufzulisten:

```yaml
filters:
  default_sources:
    - Example Public Holidays
```

### Kategoriefilter

Kategorien stammen aus ICS-`CATEGORIES`-Eigenschaften oder JSON-Feed-Feldern. Der Filter zeigt eine Mehrfachauswahl distinct Werte aus der Datenbank.

### Datumsbereichsfilter

Besucher wählen Start- und Enddatum. Standardwerte:

- Start: heute (oder Seitenattribut `from`)
- Ende: +3 Monate (oder Seitenattribut `to`)

## URL-Persistenz

Ist `persist_in_url: true`, werden angewendete Filter in Query-Parameter serialisiert:

```
/events?oc_sources=Team+Calendar&oc_from=2026-07-01&oc_to=2026-07-31
```

Das ermöglicht teilbare gefilterte Ansichten. Parameter-Präfix `oc_` vermeidet Kollisionen mit anderen Plugins.

## Twig- und Shortcode-Filter

Filter inline ohne UI übergeben:

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

## API-Filter

Siehe [API.md](API.md) — Query-Parameter `source`, `category`, `from` und `to`.

## Zusammenspiel mit Suche

Filter und Suche kombinieren mit UND-Logik: Ergebnisse müssen aktiven Filtern **und** der Suchabfrage entsprechen.

## Keine Ergebnisse

Wenn keine Termine passen, zeigt die UI die übersetzte Meldung `FRONTEND_NO_EVENTS`. Prüfen:

- Quellennamen korrekt geschrieben
- Datumsbereich schließt Termindaten ein
- Termine erfolgreich synchronisiert

## Barrierefreiheit

Filter-Steuerelemente nutzen native Formularelemente mit Beschriftungen aus Sprachdateien. Tastaturbenutzer können durchtabben und mit Enter anwenden.

## Verwandte Dokumentation

- [Searching.md](Searching.md)
- [Shortcodes.md](Shortcodes.md)
- [Configuration.md](Configuration.md)
