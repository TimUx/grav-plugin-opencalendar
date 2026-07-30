# v1.2.1
## 07/30/2026

1. [](#improved)
    * Release / GPM packages exclude docs, tests, CI, and GitHub community files via `.gitattributes` `export-ignore` (and Composer `archive.exclude`)
    * README documentation links point to GitHub so installed packages do not need the `docs/` tree

# v1.2.0
## 07/30/2026

1. [](#new)
    * Admin Synchronization tab: upload `.ics` / `.ical` / `.json` calendar files, register as local sources, and import immediately
2. [](#improved)
    * Local sources may resolve under `user/data/opencalendar/` (uploads) as well as the plugin directory
    * Documented Admin upload in README, Sources, Synchronization, Configuration, FAQ, Troubleshooting, and Installation (EN/DE)

# v1.1.1
## 07/30/2026

1. [](#new)
    * Shortcode/Twig options `max_events` and `no_pagination` for list caps and pager control
    * Shortcode/Twig option `group_by` (`none`, `day`, `week`, `month`, `year`) for list headings
    * Network calendar subscription feed (`webcal://`, refresh hints, ETag, subscribe UI)
2. [](#improved)
    * Documented every usable `[opencalendar]` parameter (including `show_past`)
    * Accepted `sources` / `calendar_view` aliases and shortcode `categories`
    * Admin list grouping includes `year`
    * ICS export tuned for phone/mail auto-refresh (`REFRESH-INTERVAL`, default date window)

# v1.1.0
## 07/29/2026

1. [](#new)
    * Admin home dashboard widget with sync status summary
    * Webhook endpoint for push-triggered forced sync (shared secret)
    * Public ICS export feed (`export.route` and `/export.ics` via API)
    * Event pipeline hooks for custom processing (`opencalendar.*` Grav events)
    * Language packs: French, Spanish, Dutch, and Italian
2. [](#improved)
    * Optional sync on Grav cache clear (`advanced.scheduler.on_cache_clear`)
    * Twig helper `opencalendar_export_url()` for subscribe links

# v1.0.1
## 07/29/2026

1. [](#improved)
    * Declared Grav 1.7 compatibility for GPM listing
    * Documented CalDAV, JSON, and local source adapters
    * Linked public demo site for the plugin listing

# v1.0.0
## 07/28/2026

1. [](#new)
    * Initial public release of OpenCalendar
    * ICS source synchronization with RFC5545 parsing (RRULE, EXDATE, timezones)
    * SQLite storage with FTS5 search, pagination, and filtering
    * Calendar and list frontend views powered by Event Calendar 5.10.1
    * Twig helpers, shortcodes, optional REST API, and Admin configuration
    * Extensible source architecture with ICS, CalDAV, JSON, and local adapters
    * PHPUnit, PHPStan level 8, PHP_CodeSniffer, and GitHub Actions CI
