# GPM submission issue body

> Deutsch: [GPM-Einreichung](../de/gpm-submission-issue.md)

## Plugin Submission

**Repository:** https://github.com/TimUx/grav-plugin-opencalendar
**Name:** OpenCalendar
**Slug:** `opencalendar`
**Version:** 1.0.1
**License:** MIT

## Description

OpenCalendar aggregates events from ICS, CalDAV, JSON, and local sources into SQLite and displays them via calendar/list views, Twig helpers, shortcodes, and an optional REST API. It includes Admin configuration, EN/DE translations, pagination, search/filters, and PHPUnit/CI.

## Release

Release: https://github.com/TimUx/grav-plugin-opencalendar/releases/tag/v1.0.1

## Demo

https://www.feuerwehr-willingshausen.de/de/termine

## Compatibility

* Grav 1.7 (PHP 8.2+)
* Declared in `blueprints.yaml` via `compatibility.grav: ['1.7']`

## Notes for reviewers

* Install path: `user/plugins/opencalendar`
* Requires Composer dependencies in the plugin folder (`composer install --no-dev`) — primarily `sabre/vobject`
* SQLite + PDO SQLite extension required
* Docs: https://github.com/TimUx/grav-plugin-opencalendar/tree/main/docs
