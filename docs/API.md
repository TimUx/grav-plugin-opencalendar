# REST API

OpenCalendar can expose read-only JSON endpoints for headless integrations, mobile apps, or AJAX clients.

## Enabling

```yaml
api:
  enabled: true
  route: /opencalendar/api
```

**Security note:** The API is disabled by default. On public sites, combine with web server authentication, Grav ACL, or IP allowlists before enabling.

## Base URL

```
https://your-site.com/opencalendar/api
```

Exact prefix matches `api.route`.

## Endpoints

### `GET /events`

List events with optional filters.

**Query parameters:**

| Parameter | Description |
|-----------|-------------|
| `from` | ISO 8601 or Unix timestamp |
| `to` | ISO 8601 or Unix timestamp |
| `source` | Source name (repeatable) |
| `category` | Category (repeatable) |
| `q` | Search query |
| `limit` | Page size (default from config) |
| `offset` | Pagination offset |
| `sort` | `asc` or `desc` |

**Example:**

```
GET /opencalendar/api/events?from=2026-07-01&to=2026-07-31&limit=50
```

**Response:**

```json
{
  "meta": {
    "total": 42,
    "limit": 50,
    "offset": 0
  },
  "data": [
    {
      "uid": "abc@example.com",
      "title": "Team meeting",
      "start": "2026-07-28T10:00:00+02:00",
      "end": "2026-07-28T11:00:00+02:00",
      "allDay": false,
      "location": "Room 1",
      "description": "Weekly sync",
      "categories": ["work"],
      "source": {
        "name": "Team Calendar",
        "color": "#3788d8"
      },
      "url": "https://example.com/event/abc"
    }
  ]
}
```

### `GET /events/{uid}`

Single event by UID.

### `GET /calendars` (alias: `/sources`)

List configured sources (names, colors, enabled — no credentials).

### `GET /categories`

Distinct categories across all events.

### `GET /export.ics` (alias: `/calendar.ics`)

Aggregated ICS feed of stored events. Same filters as `/events` (`from`, `to`, `source`, `category`, `q`, `limit`). Returns `text/calendar`.

Also available as a dedicated route when `export.enabled` is true (default `/opencalendar/calendar.ics`) — this works even if the JSON API is disabled.

## Webhook sync

Independent of the JSON API. Enable under `webhook:`:

```yaml
webhook:
  enabled: true
  route: /opencalendar/webhook
  secret: 'change-me'
  allow_source_param: true
```

```
POST /opencalendar/webhook
X-OpenCalendar-Token: change-me
```

Optional: `?source=my-calendar` or JSON body `{"source":"my-calendar"}` to sync one source. Accepts `Authorization: Bearer …` or `?token=` as well.

## Rate limiting

When `api.rate_limit.enabled` is `true`:

- Default: 60 requests per minute per client IP
- HTTP 429 returned when exceeded
- `Retry-After` header included

Configure:

```yaml
api:
  rate_limit:
    enabled: true
    max_requests: 60
    per_minutes: 1
```

## CORS

For browser clients on other origins:

```yaml
api:
  cors:
    enabled: true
    allowed_origins:
      - 'https://app.example.com'
```

## Pagination

```yaml
api:
  pagination:
    default_limit: 50
    max_limit: 200
```

Requests exceeding `max_limit` are capped silently.

## Error responses

| Status | Meaning |
|--------|---------|
| 400 | Invalid query parameters |
| 404 | Event or route not found |
| 429 | Rate limit exceeded |
| 503 | API disabled |

Error body:

```json
{
  "error": {
    "code": "API_DISABLED",
    "message": "The OpenCalendar API is disabled."
  }
}
```

Messages use language keys from `PLUGIN_OPENCALENDAR.ERROR_*`.

## Caching

API responses respect render cache headers when `cache.enabled` is true. Clients should send `If-None-Match` when provided.

## Related documentation

- [Configuration.md](Configuration.md)
- [Searching.md](Searching.md)
- [Filtering.md](Filtering.md)
