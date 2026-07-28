# Calendar Sources

OpenCalendar aggregates events from multiple source types. Each source is defined in the `sources` array in configuration.

## Common fields

| Field | Required | Description |
|-------|----------|-------------|
| `name` | Yes | Human-readable label |
| `enabled` | Yes | When `false`, source is skipped entirely |
| `type` | Yes | `ics`, `caldav`, `json`, or `local` |
| `url` | Yes* | Remote URL or local path |
| `refresh` | No | `inherit` or interval override |
| `color` | No | Hex color for UI (#RRGGBB) |
| `description` | No | Admin-only notes |
| `auth` | No | Authentication block |

*Local sources use a path relative to the plugin directory.

## ICS (iCalendar)

Best for public `.ics` feeds (Google Calendar public URL, Outlook publish link, etc.).

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

See [ICS.md](ICS.md) for format notes and troubleshooting.

## CalDAV

For authenticated calendar servers (Nextcloud, Radicale, Baikal, etc.).

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

Recommendations:

- Use app-specific passwords, not account passwords
- Store credentials in `user/config/plugins/opencalendar.yaml`, not in git
- CalDAV sync may be slower than ICS — use longer refresh intervals

## JSON

For custom HTTP APIs returning a documented JSON event schema.

Expected shape (conceptual):

```json
{
  "events": [
    {
      "uid": "unique-id",
      "title": "Team standup",
      "start": "2026-07-28T09:00:00+02:00",
      "end": "2026-07-28T09:30:00+02:00",
      "description": "Daily sync",
      "location": "Room A",
      "categories": ["work"]
    }
  ]
}
```

```yaml
- name: Internal Events API
  enabled: true
  type: json
  url: 'https://intranet.example.com/api/events'
  auth:
    type: bearer
    password: 'your-api-token'
```

## Local

For ICS or JSON files shipped with the site or dropped into the plugin folder.

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

Place files under `user/plugins/opencalendar/data/` or another path relative to the plugin root.

## Disabled example

The default config includes a disabled placeholder:

```yaml
- name: Disabled Legacy Calendar
  enabled: false
  type: ics
  url: 'https://example.com/calendar.ics'
```

Enable after replacing the URL with a valid feed.

## Source colors

Colors appear in calendar views and source badges when `display.event.show_source_badge` is enabled. Choose distinct colors when overlaying many feeds.

## Refresh inheritance

| `refresh` value | Behavior |
|-----------------|----------|
| `inherit` | Uses global `sync_interval` |
| `5`–`60`, `daily` | Source-specific schedule |

## Security

- Validate URLs before adding untrusted feeds (SSRF risk on server-side fetch)
- Never commit real credentials to version control
- Use HTTPS endpoints exclusively in production

## Related documentation

- [ICS.md](ICS.md)
- [Synchronization.md](Synchronization.md)
- [Configuration.md](Configuration.md)
