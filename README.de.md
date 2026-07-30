# OpenCalendar

> English: [README](README.md)

[![CI](https://github.com/TimUx/grav-plugin-opencalendar/actions/workflows/ci.yml/badge.svg)](https://github.com/TimUx/grav-plugin-opencalendar/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

**OpenCalendar** ist ein Grav-CMS-Plugin, das Termine aus ICS-, CalDAV-, JSON- und lokalen Quellen in einen einheitlichen, durchsuchbaren Kalender mit SQLite-Speicher, flexiblen Anzeigeoptionen und optionalem REST-API-Zugriff aggregiert.

## Überblick

Moderne Websites beziehen Termine oft gleichzeitig aus Google Calendar, Nextcloud, eigenen APIs und statischen Dateien. OpenCalendar normalisiert diese Feeds in einen abfragbaren Speicher und rendert sie über Kalenderansichten, Listen, Twig-Templates, Shortcodes oder JSON-Endpunkte — alles konfigurierbar im Grav Admin oder per YAML.

Entwickelt für PHP 8.2+ mit strikter Typisierung, PHPStan Level 8 und PSR-12-Code-Stil.

## Features

- **Mehrere Quelltypen** — ICS/iCalendar, CalDAV, JSON-APIs, lokale Dateien
- **Admin-Datei-Upload** — `.ics` / `.json` im Synchronisierungs-Dashboard nach SQLite importieren
- **SQLite-Speicher** — schnelle Abfragen, FTS-Suche, keine externe Datenbank nötig
- **Hintergrund-Sync** — konfigurierbare Intervalle mit Grav-Scheduler-Integration
- **Zwei Ansichten** — interaktiver Kalender (Monat/Woche/Tag) und gruppierte Listen
- **Suche & Filter** — Volltextsuche plus Filter nach Quelle, Kategorie und Datum
- **Twig & Shortcodes** — Einbindung überall im Theme oder Seiteninhalt
- **Optionale REST-API** — schreibgeschütztes JSON mit Rate Limiting
- **Internationalisierung** — Admin- und Frontend-Strings auf Englisch und Deutsch
- **Caching** — Parse- und Render-Cache für Produktionsleistung
- **Admin-UI** — tabbed Konfiguration (General, Storage, Sources, Display, Search, Filters, Synchronization, Advanced)

## Architektur

OpenCalendar nutzt ein Schichtenmodell: Quell-Adapter holen und parsen Feeds, eine Sync-Pipeline schreibt nach SQLite, Services übernehmen Abfragen/Suche/Filter, und die Grav-Integration (Twig, Shortcodes, API) liegt am Rand.

```
Sources (ICS/CalDAV/JSON/Local) → Sync → SQLite → Services → Twig / Shortcodes / API
```

Details: [docs/de/Architecture.md](docs/de/Architecture.md)

## Installation

### Voraussetzungen

- Grav 1.7.0+
- PHP 8.2+ mit `pdo`, `pdo_sqlite`, `json`, `mbstring`

### GPM

```bash
bin/gpm install opencalendar
```

### Manuell

```bash
git clone https://github.com/TimUx/grav-plugin-opencalendar.git user/plugins/opencalendar
cd user/plugins/opencalendar
composer install --no-dev --optimize-autoloader
bin/grav cache
```

Aktivieren unter **Admin → Plugins → OpenCalendar** oder `enabled: true` in der Config setzen.

Vollständiger Leitfaden: [docs/de/Installation.md](docs/de/Installation.md)

## Aktualisieren

1. `user/config/plugins/opencalendar.yaml` und `data/opencalendar.db` sichern
2. Plugin-Dateien aktualisieren (GPM oder git pull)
3. `composer install` ausführen, wenn sich Abhängigkeiten geändert haben
4. Cache leeren: `bin/grav cache`

Schema-Migrationen laufen automatisch. Siehe [docs/de/Migration.md](docs/de/Migration.md).

## Konfiguration

Defaults stehen in `opencalendar.yaml`. Überschreiben in `user/config/plugins/opencalendar.yaml`:

```yaml
enabled: true
timezone: Europe/Berlin
sync_interval: 15
sources:
  - name: Team Calendar
    enabled: true
    type: ics
    url: 'https://example.com/calendar.ics'
    color: '#3788d8'
```

Alles konfigurierbar unter **Admin → Plugins → OpenCalendar** mit Tabs für General, Storage, Sources, Display, Search, Filters, Synchronization und Advanced.

Kalenderdatei manuell importieren: **Synchronization → Kalenderdatei hochladen** (`.ics` / `.ical` / `.json`). Leitfaden: [docs/de/Synchronization.md](docs/de/Synchronization.md#kalenderdatei-hochladen).

Vollständige Referenz: [docs/de/Configuration.md](docs/de/Configuration.md)

## Ansichten

| Ansicht | Beschreibung |
|---------|--------------|
| **Calendar** | Monats-, Wochen-, Tages- oder Listen-Wochen-Layouts mit Navigation |
| **List** | Gruppierte chronologische Liste mit Paginierung |

Standard über `display.default_view` oder pro Seite mit Shortcodes setzen.

## Twig

```twig
{% set events = opencalendar_events({ from: 'now', to: '+2 months', limit: 10 }) %}
{% for event in events %}
  <article>{{ event.title }} — {{ event.start|opencalendar_format_datetime('medium') }}</article>
{% endfor %}
```

Plugin-Partials einbinden oder im Theme überschreiben.

Leitfaden: [docs/de/Twig.md](docs/de/Twig.md)

## Shortcodes

```
[opencalendar view="calendar" show_filters="true" /]
[opencalendar view="list" limit="5" from="now" to="+30 days" /]
[opencalendar-search /]
```

Leitfaden: [docs/de/Shortcodes.md](docs/de/Shortcodes.md)

## Suche

Volltextsuche über Titel, Beschreibung, Ort und Kategorien. Konfiguration im Admin oder per YAML:

```yaml
search:
  enabled: true
  min_query_length: 2
  max_results: 25
  highlight: true
```

Leitfaden: [docs/de/Searching.md](docs/de/Searching.md)

## Filter

Filtern nach Quelle, Kategorie und Datumsbereich. URL-Persistenz ermöglicht teilbare gefilterte Ansichten.

Leitfaden: [docs/de/Filtering.md](docs/de/Filtering.md)

## Synchronisation

Remote-Feeds synchronisieren nach Zeitplan (Standard: alle 15 Minuten). Pro-Quelle-Refresh-Overrides, Deduplizierung, Wiederholungs-Expansion und Cleanup-Richtlinien halten die Datenbank aktuell ohne unbegrenztes Wachstum. Im Admin-Tab Synchronization sind außerdem Force-Sync, Rebuild und **manueller Kalender-Upload** möglich.

Leitfaden: [docs/de/Synchronization.md](docs/de/Synchronization.md)

## SQLite

Termine liegen in `data/opencalendar.db` mit WAL-Modus, FTS5-Suche und automatischen Schema-Migrationen.

Leitfaden: [docs/de/SQLite.md](docs/de/SQLite.md)

## Caching

Parse-Cache vermeidet erneutes Lesen unveränderter Feeds; Render-Cache beschleunigt Twig- und API-Antworten. TTL für Traffic und Update-Frequenz anpassen.

Leitfaden: [docs/de/Caching.md](docs/de/Caching.md)

## Performance

- WAL-Modus und Caching in Produktion aktivieren
- Sinnvolle Sync-Intervalle (nicht alle 5 Minuten pollen, wenn nicht nötig)
- Wiederholungs-Horizont bei großen unendlichen Serien begrenzen
- Listenansichten und API-Antworten paginieren

## REST-API

Optionale schreibgeschützte JSON-API (standardmäßig deaktiviert):

```
GET /opencalendar/api/events?from=2026-07-01&to=2026-07-31
```

Leitfaden: [docs/de/API.md](docs/de/API.md)

## Fehlerbehebung

Häufige Probleme: Sync-Fehler, leere Kalender, Berechtigungsfehler, Scheduler läuft nicht.

Leitfaden: [docs/de/Troubleshooting.md](docs/de/Troubleshooting.md)

## FAQ

Kurzantworten zu Quellen, Google Calendar, SQLite, Lizenzierung und mehr.

Leitfaden: [docs/de/FAQ.md](docs/de/FAQ.md)

## Dokumentation

Vollständiger Index (DE + EN): [docs/README.md](docs/README.md)

| Dokument | Thema |
|----------|-------|
| [Installation](docs/de/Installation.md) | Einrichtung und Voraussetzungen |
| [Configuration](docs/de/Configuration.md) | Alle Konfigurationsoptionen |
| [Architecture](docs/de/Architecture.md) | Code-Struktur |
| [Sources](docs/de/Sources.md) | ICS, CalDAV, JSON, lokal, Admin-Upload |
| [ICS](docs/de/ICS.md) | iCalendar-Details |
| [Synchronization](docs/de/Synchronization.md) | Sync, Cleanup, Admin-Upload |
| [SQLite](docs/de/SQLite.md) | Datenbankspeicher |
| [Twig](docs/de/Twig.md) | Template-Integration |
| [Shortcodes](docs/de/Shortcodes.md) | Seiten-Shortcodes |
| [Subscribe](docs/de/Subscribe.md) | Netzwerk-/Abonnement-Kalender |
| [Searching](docs/de/Searching.md) | Volltextsuche |
| [Filtering](docs/de/Filtering.md) | Terminfilter |
| [Caching](docs/de/Caching.md) | Cache-Schichten |
| [API](docs/de/API.md) | REST-Endpunkte |
| [Development](docs/de/Development.md) | Leitfaden für Mitwirkende |
| [Migration](docs/de/Migration.md) | Upgrades |
| [Troubleshooting](docs/de/Troubleshooting.md) | Problemlösung |
| [FAQ](docs/de/FAQ.md) | Häufige Fragen |
| [Publishing / GPM](docs/de/Publishing-GPM.md) | Offizielle Grav-Repository-Aufnahme |

## Roadmap

- [x] CalDAV-Discovery und Multi-Collection-Sync
- [x] JSON-API- und Local-File-Source-Adapter
- [x] Admin-Dashboard-Widget mit Sync-Status
- [x] Webhook-getriggerter Sync für Push-Updates
- [x] Export nach ICS
- [x] Zusätzliche Sprachpakete
- [x] Event-Pipeline-Hooks für Custom Processing

Fortschritt auf [GitHub Issues](https://github.com/TimUx/grav-plugin-opencalendar/issues).

## Mitwirken

Beiträge sind willkommen! Lesen Sie [CONTRIBUTING.de.md](CONTRIBUTING.de.md), führen Sie `composer check` aus und reichen Sie einen Pull Request ein.

## Lizenz

[MIT](LICENSE) — Copyright (c) 2026 TimUx

## Autor

**TimUx** — [github.com/TimUx](https://github.com/TimUx)

Repository: [github.com/TimUx/grav-plugin-opencalendar](https://github.com/TimUx/grav-plugin-opencalendar)
