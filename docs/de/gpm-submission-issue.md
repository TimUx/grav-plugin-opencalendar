# GPM-Einreichung

> English: [gpm-submission-issue](../en/gpm-submission-issue.md)

> **Hinweis zum GitHub-Titel:** Das Issue auf getgrav/grav muss mit `[add-resource] New Plugin: OpenCalendar` beginnen (siehe [Publishing-GPM.md](Publishing-GPM.md)).

## Plugin-Einreichung

**Repository:** https://github.com/TimUx/grav-plugin-opencalendar
**Name:** OpenCalendar
**Slug:** `opencalendar`
**Version:** 1.0.1
**License:** MIT

## Beschreibung

OpenCalendar aggregiert Termine aus ICS-, CalDAV-, JSON- und lokalen Quellen in SQLite und zeigt sie über Kalender-/Listenansichten, Twig-Helfer, Shortcodes und eine optionale REST-API an. Enthalten sind Admin-Konfiguration, EN/DE-Übersetzungen, Paginierung, Suche/Filter sowie PHPUnit/CI.

## Release

Release: https://github.com/TimUx/grav-plugin-opencalendar/releases/tag/v1.0.1

## Demo

https://www.feuerwehr-willingshausen.de/de/termine

## Kompatibilität

* Grav 1.7 (PHP 8.2+)
* Deklariert in `blueprints.yaml` via `compatibility.grav: ['1.7']`

## Hinweise für Reviewer

* Installationspfad: `user/plugins/opencalendar`
* Composer-Abhängigkeiten im Plugin-Ordner erforderlich (`composer install --no-dev`) — primär `sabre/vobject`
* SQLite + PDO-SQLite-Erweiterung erforderlich
* Docs: https://github.com/TimUx/grav-plugin-opencalendar/tree/main/docs
