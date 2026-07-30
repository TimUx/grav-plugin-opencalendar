# REST-API

> English: [REST API](../en/API.md)

OpenCalendar kann schreibgeschützte JSON-Endpunkte für Headless-Integrationen, Mobile Apps oder AJAX-Clients bereitstellen.

## Aktivierung

```yaml
api:
  enabled: true
  route: /opencalendar/api
```

**Sicherheitshinweis:** Die API ist standardmäßig deaktiviert. Auf öffentlichen Sites vor der Aktivierung Webserver-Authentifizierung, Grav-ACL oder IP-Allowlists kombinieren.

## Basis-URL

```
https://your-site.com/opencalendar/api
```

Das exakte Präfix entspricht `api.route`.

## Endpunkte

### `GET /events`

Ereignisse auflisten mit optionalen Filtern.

**Query-Parameter:**

| Parameter | Beschreibung |
|-----------|--------------|
| `from` | ISO 8601 oder Unix-Zeitstempel |
| `to` | ISO 8601 oder Unix-Zeitstempel |
| `source` | Quellname (wiederholbar) |
| `category` | Kategorie (wiederholbar) |
| `q` | Suchanfrage |
| `limit` | Seitengröße (Standard aus Konfiguration) |
| `offset` | Paginierungs-Offset |
| `sort` | `asc` oder `desc` |

**Beispiel:**

```
GET /opencalendar/api/events?from=2026-07-01&to=2026-07-31&limit=50
```

**Antwort:**

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

Einzelnes Ereignis nach UID.

### `GET /calendars` (Alias: `/sources`)

Konfigurierte Quellen auflisten (Namen, Farben, enabled — ohne Zugangsdaten).

### `GET /categories`

Eindeutige Kategorien über alle Ereignisse.

### `GET /export.ics` (Alias: `/calendar.ics`)

Aggregierter ICS-Feed gespeicherter Ereignisse. Gleiche Filter wie `/events` (`from`, `to`, `source`, `category`, `q`, `limit`). Liefert `text/calendar`.

Auch als dedizierte Route verfügbar, wenn `export.enabled` `true` ist (Standard `/opencalendar/calendar.ics`) — funktioniert auch, wenn die JSON-API deaktiviert ist.

## Webhook-Sync

Unabhängig von der JSON-API. Aktivierung unter `webhook:`:

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

Optional: `?source=my-calendar` oder JSON-Body `{"source":"my-calendar"}` zum Sync einer Quelle. Akzeptiert auch `Authorization: Bearer …` oder `?token=`.

## Rate Limiting

Wenn `api.rate_limit.enabled` `true` ist:

- Standard: 60 Anfragen pro Minute pro Client-IP
- HTTP 429 bei Überschreitung
- `Retry-After`-Header enthalten

Konfiguration:

```yaml
api:
  rate_limit:
    enabled: true
    max_requests: 60
    per_minutes: 1
```

## CORS

Für Browser-Clients auf anderen Origins:

```yaml
api:
  cors:
    enabled: true
    allowed_origins:
      - 'https://app.example.com'
```

## Paginierung

```yaml
api:
  pagination:
    default_limit: 50
    max_limit: 200
```

Anfragen über `max_limit` werden still auf das Maximum begrenzt.

## Fehlerantworten

| Status | Bedeutung |
|--------|-----------|
| 400 | Ungültige Query-Parameter |
| 404 | Ereignis oder Route nicht gefunden |
| 429 | Rate Limit überschritten |
| 503 | API deaktiviert |

Fehler-Body:

```json
{
  "error": {
    "code": "API_DISABLED",
    "message": "The OpenCalendar API is disabled."
  }
}
```

Meldungen verwenden Sprachschlüssel aus `PLUGIN_OPENCALENDAR.ERROR_*`.

## Caching

API-Antworten respektieren Render-Cache-Header, wenn `cache.enabled` `true` ist. Clients sollten `If-None-Match` senden, wenn bereitgestellt.

## Verwandte Dokumentation

- [Configuration.md](Configuration.md)
- [Searching.md](Searching.md)
- [Filtering.md](Filtering.md)
