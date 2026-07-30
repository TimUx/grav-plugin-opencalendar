# ICS / iCalendar Support

OpenCalendar uses [Sabre VObject](https://github.com/sabre-io/vobject) to parse ICS (iCalendar) feeds — the standard format for `.ics` files and many public calendar subscriptions.

## Supported components

| Component | Support |
|-----------|---------|
| `VEVENT` | Full import |
| `VTIMEZONE` | Applied to floating and zoned times |
| `VALARM` | Stored when present; display optional |
| `VCALENDAR` wrappers | Multiple calendars in one file |

## Recurring events

When `advanced.import.expand_recurring` is enabled:

- `RRULE` occurrences are materialized into individual database rows
- Expansion horizon: `advanced.import.recurring_horizon_days` (default 365)
- `EXDATE` and `RECURRENCE-ID` exceptions are honored

Disable expansion for feeds with very long infinite recurrences if database size is a concern.

## Timezones

Priority for event start/end:

1. `TZID` parameter on `DTSTART`/`DTEND`
2. `VTIMEZONE` definitions in the feed
3. Plugin default `timezone` from config
4. UTC fallback

Ensure `timezone` in config matches your audience when feeds use floating times.

## Fields mapped to events

| ICS property | Event field |
|--------------|-------------|
| `SUMMARY` | title |
| `DESCRIPTION` | description |
| `LOCATION` | location |
| `DTSTART` / `DTEND` | start / end |
| `UID` | uid (deduplication key) |
| `CATEGORIES` | categories |
| `URL` | external link |
| `STATUS` | status (confirmed, cancelled, …) |

Cancelled events (`STATUS:CANCELLED`) are imported but may be hidden in frontend views depending on filter defaults.

## All-day events

All-day events (`VALUE=DATE`) render with the translated "All day" label and span full calendar days in month view.

## Common feed sources

| Provider | Notes |
|----------|-------|
| Google Calendar | Use "Public address in iCal format" from calendar settings |
| Outlook / Microsoft 365 | Publish ICS link from calendar sharing |
| Apple iCloud | Shared read-only ICS URL |
| Nextcloud | Public link or CalDAV (CalDAV preferred for private calendars) |

## Fetch considerations

- Many providers rate-limit aggressive polling — use `refresh: 30` or higher
- Google public ICS URLs are stable but may lag behind the web UI by hours
- Large ICS files (>5 MB) may require increased `advanced.http.timeout`

## Validation errors

Malformed ICS produces parse errors logged at warning level. The previous successful import remains served until the feed parses again.

Debugging steps:

1. Download ICS manually: `curl -o /tmp/feed.ics 'URL'`
2. Validate with external tools (e.g. `icalendar` Python library)
3. Enable `advanced.debug` and re-sync

## HTML in descriptions

Some feeds embed HTML in `DESCRIPTION`. By default HTML is preserved. Set `advanced.import.strip_html: true` to store plain text only.

## Exporting / subscribing (network calendars)

OpenCalendar publishes an aggregated `text/calendar` feed from SQLite for **subscription** in phones and mail apps:

```yaml
export:
  enabled: true
  route: /opencalendar/calendar.ics
  calendar_name: OpenCalendar
  refresh_minutes: 60
  default_from: '-30 days'
  default_to: '+365 days'
```

```twig
<a href="{{ opencalendar_webcal_url() }}">Subscribe</a>
<code>{{ opencalendar_export_url({ source: 'team' }) }}</code>
```

Full device instructions: [Subscribe.md](Subscribe.md).

When the JSON API is enabled, the same feed is also available at `{api.route}/export.ics`.

Exported fields include UID, SUMMARY, DESCRIPTION, LOCATION, URL, STATUS, CATEGORIES, DTSTART/DTEND (UTC or VALUE=DATE for all-day), `REFRESH-INTERVAL`, and optional X-OPENCALENDAR-SOURCE metadata.

## Related documentation

- [Sources.md](Sources.md)
- [Synchronization.md](Synchronization.md)
- [Troubleshooting.md](Troubleshooting.md)
