# Synchronisation

> English: [Synchronization](../en/Synchronization.md)

OpenCalendar hält eine lokale SQLite-Kopie der Ereignisse mit den konfigurierten Quellen synchron. Dieses Dokument erklärt Sync-Auslöser, Intervalle, Fehlerbehandlung und Bereinigung.

## Sync-Auslöser

Ereignisse werden aktualisiert durch:

1. **Grav Scheduler** — für Produktion empfohlen (siehe `advanced.scheduler.enabled`)
2. **Cache-Clear-Hook** — optionaler Sync beim Leeren des Grav-Caches (`advanced.scheduler.on_cache_clear`)
3. **Manueller CLI-Befehl** — für Betrieb und Debugging (Implementierung ausstehend)
4. **Admin-Aktion** — erzwungener Sync für eine einzelne Quelle (Implementierung ausstehend)

## Sync-Intervalle

Das globale Intervall wird durch `sync_interval` festgelegt:

| Wert | Verhalten |
|------|-----------|
| `5`, `10`, `15`, `30`, `60` | Alle N Minuten ausführen |
| `daily` | Einmal täglich (Mitternacht in der Site-Zeitzone) |

Quell-spezifischer Override über `sources[].refresh`:

- `inherit` — globales Intervall verwenden
- Beliebiger Intervallwert — nur für diese Quelle überschreiben

Gestaffelte Planung verhindert, dass alle Quellen bei gleichen Intervallen gleichzeitig Remote-Server anfragen.

## Sync-Pipeline

```
Trigger → Load config → For each enabled source:
    → Fetch (HTTP/CalDAV/file)
    → Parse → Normalize events
    → Upsert into SQLite
    → Mark missing events as deleted
→ Apply deduplication
→ Invalidate caches
→ Log summary
```

### Abruf-Verhalten

HTTP-Optionen stammen aus `advanced.http`:

- `timeout` — maximale Sekunden pro Anfrage
- `verify_ssl` — ungültige Zertifikate bei `true` ablehnen
- `user_agent` — wird mit ausgehenden Anfragen gesendet
- `max_redirects` — Weiterleitungen bis zu diesem Limit folgen

Authentifizierung pro Quelle über `auth.type`:

- `none` — keine Zugangsdaten
- `basic` — HTTP Basic (`username` / `password`)
- `bearer` — `Authorization: Bearer`-Header im Passwortfeld

### Parsen und Import

- ICS-Feeds werden mit Sabre VObject geparst
- Wiederkehrende Ereignisse werden expandiert, wenn `advanced.import.expand_recurring` `true` ist
- Horizont gesteuert durch `advanced.import.recurring_horizon_days`
- HTML in Beschreibungen optional entfernt über `advanced.import.strip_html`

### Deduplizierung

Wenn `advanced.deduplicate.enabled` `true` ist, werden Ereignisse, die konfigurierte Felder (Standard: `uid`, `start`, `end`) aus verschiedenen Quellen übereinstimmen, zu einer Zeile zusammengeführt, wobei mehrere Quellzuordnungen erhalten bleiben.

## Löschungen und Bereinigung

Wenn ein Ereignis aus einem Feed verschwindet:

1. Es wird als gelöscht markiert (Soft Delete) mit Zeitstempel.
2. Die Bereinigungsrichtlinie (`cleanup`) bestimmt die endgültige Entfernung:

| Richtlinie | Wirkung |
|------------|---------|
| `never` | Soft-gelöschte Zeilen unbegrenzt behalten |
| `immediate` | Hard Delete beim nächsten Sync |
| `1`, `7`, `30`, `90` | Hard Delete nach N Tagen |

Optional führt `storage.vacuum_on_cleanup` nach Bereinigungs-Batches SQLite `VACUUM` aus.

## Fehlerbehandlung

Fehlgeschlagene Quellen blockieren andere Quellen nicht. Fehler werden in `advanced.log_file` auf `advanced.log_level` protokolliert.

Häufige Fehlermodi:

| Symptom | Wahrscheinliche Ursache |
|---------|-------------------------|
| HTTP 403/401 | Fehlende oder falsche Zugangsdaten |
| HTTP 404 | Ungültige Feed-URL |
| Parse-Fehler | Fehlerhaftes ICS/JSON |
| Speicherfehler | Berechtigungen oder voller Speicher |

Das Frontend liefert weiterhin die zuletzt erfolgreichen Sync-Daten, bis die TTL abläuft.

## Überwachung

`advanced.debug` vorübergehend aktivieren, um Abruf-URLs (Zugangsdaten geschwärzt), Antwortcodes und Parse-Anzahlen zu protokollieren.

Logs prüfen:

```bash
tail -f logs/opencalendar.log
```

## Performance-Tipps

- Quell-spezifische Refresh-Overrides für langsame CalDAV-Server verwenden
- `cache.parse_cache` aktivieren, um unveränderte Payloads nicht erneut zu parsen
- `recurring_horizon_days` angemessen halten (Standard 365)

## Verwandte Dokumentation

- [Sources.md](Sources.md)
- [Caching.md](Caching.md)
- [Troubleshooting.md](Troubleshooting.md)
