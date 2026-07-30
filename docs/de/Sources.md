# Kalenderquellen

> English: [Calendar Sources](../en/Sources.md)

OpenCalendar aggregiert Ereignisse aus mehreren Quelltypen. Jede Quelle wird im `sources`-Array in der Konfiguration definiert.

## Gemeinsame Felder

| Feld | Erforderlich | Beschreibung |
|------|--------------|--------------|
| `name` | Ja | Lesbare Bezeichnung |
| `enabled` | Ja | Bei `false` wird die Quelle vollständig übersprungen |
| `type` | Ja | `ics`, `caldav`, `json` oder `local` |
| `url` | Ja* | Remote-URL oder lokaler Pfad |
| `refresh` | Nein | `inherit` oder Intervall-Override |
| `color` | Nein | Hex-Farbe für die UI (#RRGGBB) |
| `description` | Nein | Nur für Admins sichtbare Notizen |
| `auth` | Nein | Authentifizierungsblock |

*Lokale Quellen verwenden einen Pfad relativ zu erlaubten Basisverzeichnissen (`user/data/opencalendar/` für Admin-Uploads oder Plugin-Stamm für `data/…`).

## ICS (iCalendar)

Am besten für öffentliche `.ics`-Feeds (öffentliche Google-Calendar-URL, Outlook-Veröffentlichungslink usw.).

```yaml
- name: Team Holidays
  enabled: true
  type: ics
  url: 'https://example.com/holidays.ics'
  refresh: inherit
  color: '#4CAF50'
  auth:
    type: none
```

Format-Hinweise und Fehlerbehebung: [ICS.md](ICS.md).

## CalDAV

Für authentifizierte Kalenderserver (Nextcloud, Radicale, Baikal, iCloud über App-Passwörter usw.).

OpenCalendar sendet einen CalDAV-`REPORT calendar-query` gegen die Collection-URL, extrahiert `calendar-data` und parst Ereignisse mit der ICS-Engine (einschließlich RRULE-Expansion).

```yaml
- name: Personal Calendar
  enabled: true
  type: caldav
  url: 'https://cloud.example.com/remote.php/dav/calendars/user/personal/'
  refresh: 30
  color: '#2196F3'
  auth:
    type: basic
    username: 'calendar-user'
    password: 'app-specific-password'
```

Empfehlungen:

- Die **Kalender-Collection-URL** verwenden (endet mit dem Kalendernamen), nicht die DAV-Wurzel
- App-spezifische Passwörter bevorzugen, keine Kontopasswörter
- Zugangsdaten in `user/config/plugins/opencalendar.yaml` speichern, nicht in Git
- CalDAV-Sync kann langsamer sein als ICS — längere Refresh-Intervalle verwenden
- Wenn REPORT nicht unterstützt wird, fällt OpenCalendar auf einen einfachen GET zurück (ICS-Export-URLs)

## JSON

Für eigene HTTP-APIs, die JSON-Ereignisse zurückgeben.

Akzeptierte Hüllen:

- `{ "events": [ ... ] }`
- `{ "data": [ ... ] }` / `{ "items": [ ... ] }` / `{ "results": [ ... ] }`
- ein rohes JSON-Array `[ ... ]`

Ereignisfelder (Aliase unterstützt):

| Feld | Aliase |
|------|--------|
| `uid` | `id` |
| `title` | `summary`, `name` |
| `start` | `start_at`, `dtstart`, `begin` |
| `end` | `end_at`, `dtend` |
| `all_day` | `allDay` |
| `description`, `location`, `organizer`, `url`, `status`, `categories`, `color`, `rrule` | |

```yaml
- name: Internal Events API
  enabled: true
  type: json
  url: 'https://intranet.example.com/api/events'
  auth:
    type: bearer
    token: 'your-api-token'
```

Bearer-Tokens können auch in `auth.password` stehen, wenn Admin-Formulare nur ein Passwortfeld anbieten.

## Local

Für ICS- oder JSON-Dateien unter den erlaubten lokalen Basispfaden (Plugin-Baum und `user/data/opencalendar/`).

```yaml
- name: Static Schedule
  enabled: true
  type: local
  url: 'data/static-schedule.ics'
  refresh: daily
  color: '#FF9800'
  auth:
    type: none
```

- Pfade werden relativ zu einem erlaubten Basisverzeichnis aufgelöst und können diese Roots nicht verlassen
- `.ics` / `.ical` → ICS-Parser
- `.json` → JSON-Parser
- Content Sniffing wird verwendet, wenn die Erweiterung mehrdeutig ist

Dateien unter `user/plugins/opencalendar/data/` ablegen **oder** den Admin-Upload nutzen (unten).

## Admin-Upload

Unter **Admin → Plugins → OpenCalendar → Synchronization** den Bereich **Kalenderdatei hochladen** nutzen.

| Detail | Wert |
|--------|------|
| Formate | `.ics`, `.ical`, `.json` |
| Max. Größe | 10 MiB |
| Speicherort | `user/data/opencalendar/uploads/` (übersteht Plugin-Updates) |
| Konfiguration | Erstellt/aktualisiert eine Quelle `type: local` in `user/config/plugins/opencalendar.yaml` |
| Import | Sofortiger erzwungener Sync nach SQLite |

Beispiel nach dem Upload:

```yaml
- name: Vereinskalender
  enabled: true
  type: local
  url: 'uploads/vereinskalender-20260730T210000Z-a1b2c3.ics'
  refresh: inherit
  description: 'Uploaded via Admin (vereinskalender.ics)'
  auth:
    type: none
```

Hinweise:

- ICS-Dateien müssen `BEGIN:VCALENDAR` enthalten; JSON muss gültiges Event-JSON sein (siehe [JSON](#json) oben).
- Erneutes Hochladen mit dem **selben Quellnamen** ersetzt Datei und Import.
- Andere Namen erzeugen zusätzliche lokale Quellen.
- Hochgeladene Dateien werden beim Entfernen einer Quelle im Tab Sources nicht automatisch gelöscht — ungenutzte Dateien unter `uploads/` bei Bedarf manuell entfernen.
- Dashboard-Schritte: [Synchronization.md](Synchronization.md#kalenderdatei-hochladen).

## Deaktiviertes Beispiel

Die Standardkonfiguration enthält einen deaktivierten Platzhalter:

```yaml
- name: Disabled Legacy Calendar
  enabled: false
  type: ics
  url: 'https://example.com/calendar.ics'
```

Nach Ersetzen der URL durch einen gültigen Feed aktivieren.

## Quellfarben

Farben erscheinen in Kalenderansichten und Quell-Badges, wenn `display.event.show_source_badge` aktiviert ist. Bei vielen überlagerten Feeds unterschiedliche Farben wählen.

## Refresh-Vererbung

| `refresh`-Wert | Verhalten |
|----------------|-----------|
| `inherit` | Verwendet globales `sync_interval` |
| `5`–`60`, `daily` | Quellspezifischer Zeitplan |

## Sicherheit

- URLs vor dem Hinzufügen nicht vertrauenswürdiger Feeds prüfen (SSRF-Risiko beim serverseitigen Abruf)
- Echte Zugangsdaten niemals in die Versionskontrolle committen
- In Produktion ausschließlich HTTPS-Endpunkte verwenden

## Verwandte Dokumentation

- [ICS.md](ICS.md)
- [Synchronization.md](Synchronization.md)
- [Configuration.md](Configuration.md)
