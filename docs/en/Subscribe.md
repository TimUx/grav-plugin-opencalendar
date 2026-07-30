# Calendar subscription (network calendars)

> Deutsch: [Kalender auf dem Smartphone abonnieren](../de/Subscribe.md)

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

You should see a `text/calendar` document starting with `BEGIN:VCALENDAR`.

Optional single source:

```
https://your-site.example/opencalendar/calendar.ics?source=feuerwehr
```

## Subscribe on smartphones

### iPhone / iPad

1. Copy the HTTPS feed URL.
2. Open **Settings → Calendar → Accounts → Add Account → Other → Add Subscribed Calendar**.
3. Paste the URL → **Next** → **Save**.

Alternatively, tap a site link that uses `webcal://…` to open the subscribe dialog directly.

The calendar appears in the **Calendar** app under subscriptions and refreshes in the background.

### Android (Google Calendar)

1. On phone or PC, open [calendar.google.com](https://calendar.google.com) (same Google account as on the device).
2. Next to **Other calendars**, click **+ → From URL**.
3. Paste `https://your-site.example/opencalendar/calendar.ics` → **Add calendar**.
4. Open the **Google Calendar** app; enable the new calendar under settings if it is hidden.

Samsung and other manufacturer calendar apps usually show the same Google-subscribed calendar once that account is linked.

### Outlook / Thunderbird / macOS

| Client | Steps |
|--------|--------|
| Outlook (desktop / M365) | **Add calendar → From internet** → paste HTTPS ICS URL |
| Thunderbird | New calendar → **On the Network** → iCalendar → URL |
| macOS Calendar | **File → New Calendar Subscription…** → paste URL |

## Twig helpers

```twig
{{ opencalendar_export_url() }}
<a href="{{ opencalendar_webcal_url() }}">Subscribe</a>
<a href="{{ opencalendar_webcal_url({ source: 'feuerwehr' }) }}">One source</a>
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
4. The ICS body includes `REFRESH-INTERVAL` as a suggested poll interval.

No push to Apple/Google is required — subscription calendars are pull-based.

## Troubleshooting

| Issue | What to check |
|-------|----------------|
| Invalid URL | Open the feed in a browser; it must start with `BEGIN:VCALENDAR` and use HTTPS |
| Empty calendar | Run a sync in Admin; check `default_from` / `default_to` |
| Stale events | Scheduler enabled? Re-add the subscription if the client cached aggressively |
| Privacy | The feed is publicly readable when enabled — protect sensitive data |

## Related

- [Kalender abonnieren (DE)](../de/Subscribe.md) — German smartphone guide
- [ICS.md](ICS.md) — import + export details
- [Synchronization.md](Synchronization.md) — how sources stay fresh
- [Shortcodes.md](Shortcodes.md) — `show_subscribe`
