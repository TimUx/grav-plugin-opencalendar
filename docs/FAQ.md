# FAQ

Frequently asked questions about OpenCalendar.

## General

### What is OpenCalendar?

OpenCalendar is a Grav CMS plugin that aggregates events from ICS, CalDAV, JSON, and local file sources into a SQLite database and displays them via calendar views, lists, Twig, shortcodes, and an optional REST API.

### Which Grav versions are supported?

Grav 1.7.0 and higher. PHP 8.2+ is required.

### Does it work without Grav Admin?

Yes. Configure via `user/config/plugins/opencalendar.yaml` manually. Admin provides a convenient UI but is not required.

## Sources and sync

### How often are calendars updated?

By default every 15 minutes via the Grav Scheduler. Change `sync_interval` globally or per source.

### Can I mix ICS and CalDAV sources?

Yes. Each source entry has its own type and credentials.

### Why are my events missing?

Common causes: source disabled, sync error, date filter excluding events, or recurring horizon too short. See [Troubleshooting.md](Troubleshooting.md).

### Does Google Calendar work?

Yes, using the public ICS address from Google Calendar settings. Private Google calendars require a shareable ICS link or CalDAV with credentials.

## Display

### Can I use my own theme styles?

Yes. Override templates under `user/themes/your-theme/templates/partials/opencalendar/` and add CSS targeting `.opencalendar` classes.

### Calendar vs list view?

Set `display.default_view` or use shortcode `view="calendar"` / `view="list"`.

### Are recurring events supported?

Yes, when `advanced.import.expand_recurring` is enabled (default).

## Technical

### Why SQLite?

Fast local queries, full-text search, no external database server, easy backups. See [SQLite.md](SQLite.md).

### Is the API secure?

The API is **disabled by default**. Enable only with appropriate access controls. See [API.md](API.md) and [SECURITY.md](../SECURITY.md).

### Can I commit composer.lock?

Yes. Grav plugins commonly commit `composer.lock` for reproducible installs.

## Licensing

OpenCalendar is released under the MIT License. Sabre VObject has its own license — see vendor documentation.

## Getting help

- [GitHub Issues](https://github.com/TimUx/grav-plugin-opencalendar/issues)
- [Documentation](../docs/)
- [Troubleshooting.md](Troubleshooting.md)
