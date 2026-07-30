# Caching

> English: [Caching](../en/Caching.md)

OpenCalendar nutzt mehrere Cache-Ebenen, damit Kalenderseiten schnell bleiben, ohne externe Feeds zu überlasten.

## Cache-Ebenen

```
Remote feed → Parse cache → SQLite → Render cache → Grav page cache → Browser
```

| Ebene | Konfiguration | Zweck |
|-------|---------------|-------|
| Parse-Cache | `cache.parse_cache`, `cache.parse_ttl` | Identische ICS-/JSON-Payloads nicht erneut parsen |
| Render-Cache | `cache.enabled`, `cache.ttl` | Abfrageergebnisse und Twig-fertige Sammlungen cachen |
| Grav-Cache | Grav Core | Vollständiger Seiten-Cache, wenn in der Website-Konfiguration aktiviert |

## Render-Cache

```yaml
cache:
  enabled: true
  ttl: 3600
```

- TTL in Sekunden; `0` invalidiert beim nächsten Sync
- Schlüssel enthalten Quellenliste, Filter-Hash und Locale
- Wird nach erfolgreichem Sync mit betroffenen Quellen automatisch invalidiert

### Render-Cache umgehen

Pro Seite im Front Matter:

```yaml
opencalendar:
  cache: false
```

Oder global während der Entwicklung deaktivieren:

```yaml
cache:
  enabled: false
```

## Parse-Cache

```yaml
cache:
  parse_cache: true
  parse_ttl: 86400
```

Speichert gehashte Roh-Payloads und geparste Zwischenstrukturen. Nützlich, wenn:

- Mehrere Quellen identische Feeds teilen
- Sync-Intervall kurz ist, Feed-Inhalt sich aber selten ändert

Bei Parser-Debugging deaktivieren.

## Invalidierungsauslöser

Render- und Parse-Cache werden geleert bei:

- Erfolgreichem Sync mit Änderungen
- Manuellem Cache-Leeren (`bin/grav cache`), wenn `advanced.scheduler.on_cache_clear` true ist
- Plugin-Konfiguration im Admin speichern (Implementierung)

Sie werden **nicht** bei fehlgeschlagenem Sync geleert — veraltete Daten sind einem leeren Kalender vorzuziehen.

## Interaktion mit Grav-Seiten-Cache

Ist Grav-Seiten-Caching site-weit aktiviert, können Kalenderseiten statisch wirken, bis der Grav-Cache abläuft. Optionen:

1. Seiten-Cache für Terminseiten per Front Matter deaktivieren
2. Kürzere Grav-Cache-Lebensdauer für dynamische Routen
3. AJAX/API für clientseitige Aktualisierung nutzen (fortgeschritten)

## HTTP-Caching (API)

Ist die REST-API aktiviert, enthalten Antworten:

- `Cache-Control: private, max-age=<ttl>`
- `ETag` für bedingte Anfragen

Siehe [API.md](API.md).

## Performance-Tuning

| Szenario | Empfehlung |
|----------|------------|
| Hoher Traffic, stabile Feeds | `ttl: 3600` oder höher |
| Häufig aktualisierte Feeds | Kürzeres Sync-Intervall, moderate TTL |
| Entwicklung | Alle Plugin-Caches deaktivieren |
| Viele wiederkehrende Termine | Parse-Cache an, sinnvoller Horizont |

## Cache-Wirksamkeit überwachen

Debug-Logging vorübergehend aktivieren:

```yaml
advanced:
  debug: true
  log_level: debug
```

Log-Einträge zeigen Cache-Hits/Misses beim Rendern und Synchronisieren.

## Verwandte Dokumentation

- [Synchronization.md](Synchronization.md)
- [Configuration.md](Configuration.md)
- [Architecture.md](Architecture.md)
