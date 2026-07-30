# Calendar subscription (network calendars)

OpenCalendar publishes a live **ICS feed** of events imported into SQLite. Phones, tablets, and mail/calendar apps can **subscribe** to that URL and refresh automatically — the same mechanism as Google Calendar / Outlook “Add calendar from URL”.

## Enable the feed

Admin → **Plugins → OpenCalendar → Advanced → ICS export / subscription**:

| Setting | Purpose |
|---------|---------|
| Enable calendar subscription feed | Turns the public ICS URL on (default: on) |
| Feed URL path | Default `/opencalendar/calendar.ics` |
| Calendar name | Shown as calendar title in client apps |
| Suggested refresh (minutes) | Hint embedded as `REFRESH-INTERVAL` / `X-PUBLISHED-TTL` |
| Default range start/end | Limits how much history/future is in the feed |
| Show subscribe links in widgets | Adds a subscribe bar under calendar/list embeds |

After saving, open:

```
https://your-site.example/opencalendar/calendar.ics
```

You should see a downloadable/viewable `text/calendar` document.

## Subscribe on devices

### iPhone / iPad / Mac

1. Copy the HTTPS URL (or use a `webcal://` link from the site).
2. **Settings → Calendar → Accounts → Add Account → Other → Add Subscribed Calendar**
3. Paste the URL → Next → Save.

Or tap a page link that uses `webcal://…` — Calendar opens the subscribe dialog.

### Android (Google Calendar)

1. On a computer: [calendar.google.com](https://calendar.google.com) → **Other calendars → From URL**
2. Paste `https://your-site.example/opencalendar/calendar.ics`
3. The calendar syncs to the Google Calendar app on the phone.

### Outlook (desktop / Microsoft 365)

**Add calendar → From internet** → paste the HTTPS ICS URL.

### Thunderbird / other clients

Add a network/remote calendar and paste the HTTPS URL. Clients poll on their own schedule; the feed’s refresh hint is typically 60 minutes (configurable).

## Filter a single source

```
https://your-site.example/opencalendar/calendar.ics?source=feuerwehr
```

`source` accepts the configured source name/key (comma-separated for several).

## Twig helpers

```twig
{# Absolute HTTPS URL for copy/paste #}
{{ opencalendar_export_url() }}

{# One-tap subscribe (iOS/macOS/Outlook) #}
<a href="{{ opencalendar_webcal_url() }}">Kalender abonnieren</a>

{# One imported source only #}
<a href="{{ opencalendar_webcal_url({ source: 'feuerwehr' }) }}">Feuerwehr-Termine</a>
```

## Shortcode / widget bar

```
[opencalendar view="list" show_subscribe="true" /]
```

Or enable **Show subscribe links in widgets** globally in Admin.

## How auto-update works

1. Grav Scheduler (or webhook) syncs remote ICS/CalDAV/JSON **into** OpenCalendar’s SQLite DB.
2. Subscribed clients periodically **GET** `/opencalendar/calendar.ics`.
3. The response includes `ETag` + `Cache-Control`; unchanged feeds can return **304**.
4. The ICS body includes `REFRESH-INTERVAL` so well-behaved clients know a suggested poll interval.

No push to Apple/Google is required — subscription calendars are pull-based.

## Security notes

- The feed is **public** when enabled (anyone with the URL can read events).
- Do not put private calendars on a publicly reachable URL without fronting auth (VPN, Basic Auth, or a secret path).
- Prefer HTTPS so clients accept the subscription.

## Related

- [ICS.md](ICS.md) — import + export details
- [Synchronization.md](Synchronization.md) — how sources stay fresh
- [Shortcodes.md](Shortcodes.md) — `show_subscribe`
