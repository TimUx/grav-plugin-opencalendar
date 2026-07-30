# Konfiguration

> English: [Configuration](../en/Configuration.md)

OpenCalendar wird über `opencalendar.yaml` (Plugin-Standardwerte) und `user/config/plugins/opencalendar.yaml` (Website-Überschreibungen) konfiguriert. Das Admin-Panel stellt alle Optionen über ein tabbed Formular bereit, das in `blueprints.yaml` definiert ist.

## Konfigurationsebenen

| Ebene | Pfad | Zweck |
|-------|------|-------|
| Standardwerte | `user/plugins/opencalendar/opencalendar.yaml` | Mitgelieferte Defaults |
| Überschreibung | `user/config/plugins/opencalendar.yaml` | Website-spezifische Einstellungen |
| Umgebung | Umgebungsvariablen (zukünftig) | Geheimnisse und deployment-spezifische Werte |

Sensible Werte (CalDAV-Passwörter, API-Tokens) immer in der Website-Konfiguration oder per Umgebungsvariable setzen, niemals im Plugin-Ordner, der in Git eingecheckt wird.

## Allgemeine Einstellungen

```yaml
enabled: true
locale: auto          # auto | en | de
timezone: Europe/Berlin         # IANA-Zeitzone
theme: auto           # auto | light | dark
sync_interval: 15     # 5 | 10 | 15 | 30 | 60 | daily
cleanup: 30           # never | immediate | 1 | 7 | 30 | 90
```

### Cache

```yaml
cache:
  enabled: true
  ttl: 3600           # Lebensdauer des Render-Caches (Sekunden)
  parse_cache: true
  parse_ttl: 86400    # Cache für geparste Feeds (Sekunden)
```

Cache während der Entwicklung deaktivieren, um Änderungen sofort zu sehen. In der Produktion Caching für Listen- und Kalenderansichten aktiviert lassen.

## Speicher

```yaml
storage:
  path: data/opencalendar.db
  wal_mode: true
  vacuum_on_cleanup: false
```

Einen absoluten Pfad verwenden, wenn die Datenbank außerhalb des Plugin-Verzeichnisses liegen soll (z. B. auf einem persistenten Volume).

## Quellen

Jeder Quelleneintrag unterstützt:

| Feld | Beschreibung |
|------|--------------|
| `name` | Anzeigename in Admin und Frontend-Badges |
| `enabled` | Sync überspringen, wenn `false` |
| `type` | `ics`, `caldav`, `json` oder `local` |
| `url` | Remote-URL oder lokaler relativer Pfad |
| `refresh` | `inherit` oder Minuten/`daily`-Überschreibung |
| `color` | Hex-Farbe für die Kalenderdarstellung |
| `description` | Optionale Admin-Notiz |
| `auth` | `none`, `basic` oder `bearer`-Anmeldedaten |

Beispiel für deaktivierte ICS-Quelle (in den Defaults enthalten):

```yaml
sources:
  - name: Disabled Legacy Calendar
    enabled: false
    type: ics
    url: 'https://example.com/calendar.ics'
    refresh: 60
    color: '#9E9E9E'
    auth:
      type: none
```

Details nach Quellentyp siehe [Sources.md](Sources.md).

## Darstellung

Steuert die Standarddarstellung von Kalender und Liste. Wichtige Optionen:

- `display.default_view` — `calendar` oder `list`
- `display.calendar.initial_view` — Monat, Woche, Tag oder Listenwoche
- `display.list.limit` — Seitengröße bei Paginierung
- `display.event.truncate_description` — Zeichenlimit (0 = kein Limit)

## Suche und Filter

```yaml
search:
  enabled: true
  min_query_length: 2
  max_results: 25
  fields: [title, description, location, categories]

filters:
  enabled: true
  show_source_filter: true
  show_category_filter: true
  show_date_range_filter: true
  persist_in_url: true
```

## API

```yaml
api:
  enabled: false
  route: /opencalendar/api
  rate_limit:
    enabled: true
    max_requests: 60
    per_minutes: 1
```

API auf öffentlichen Websites deaktiviert lassen, sofern keine Authentifizierung auf Webserver- oder Grav-Ebene hinzugefügt wird.

## Erweitert

```yaml
advanced:
  debug: false
  log_level: warning
  http:
    timeout: 30
    verify_ssl: true
  scheduler:
    enabled: true
  deduplicate:
    enabled: true
  import:
    expand_recurring: true
    recurring_horizon_days: 365
```

## Admin vs. YAML

Alle Blueprint-Felder entsprechen 1:1 den YAML-Schlüsseln; verschachtelte Werte nutzen Punktnotation (z. B. `cache.ttl` im Admin → `cache: { ttl: 3600 }` in YAML).

Nach manueller YAML-Bearbeitung Cache leeren:

```bash
bin/grav cache
```

## Verwandte Dokumentation

- [Sources.md](Sources.md) — Quellentypen und Authentifizierung
- [Caching.md](Caching.md) — Cache-Verhalten
- [API.md](API.md) — REST-Endpunkte
