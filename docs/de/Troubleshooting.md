# Fehlerbehebung

> English: [Troubleshooting](../en/Troubleshooting.md)

Lösungen für häufige OpenCalendar-Probleme.

## Plugin nicht im Admin sichtbar

**Symptome:** OpenCalendar fehlt in der Plugin-Liste.

**Prüfschritte:**

1. Ordner muss `opencalendar` unter `user/plugins/` heißen
2. `blueprints.yaml` muss im Plugin-Root existieren
3. Cache leeren: `bin/grav cache`
4. Dateiberechtigungen prüfen — PHP muss den Plugin-Ordner lesen können

## Sync-Fehler

**Symptome:** Termine veraltet; Log zeigt `ERROR_SYNC_FAILED`.

### HTTP-Fehler

| Code | Maßnahme |
|------|----------|
| 401/403 | `auth`-Anmeldedaten prüfen; App-Passwörter für CalDAV verwenden |
| 404 | URL bestätigen; mit `curl -I 'URL'` testen |
| timeout | `advanced.http.timeout` erhöhen |
| SSL-Fehler | Zertifikat prüfen oder vorübergehend `verify_ssl: false` setzen (nicht für Produktion) |

### Parse-Fehler

1. Feed manuell herunterladen und Format prüfen
2. ICS-Struktur validieren
3. `advanced.debug` aktivieren und erneut synchronisieren
4. Siehe [ICS.md](ICS.md)

## Leerer Kalender im Frontend

**Prüfschritte:**

1. Plugin aktiviert: `enabled: true`
2. Mindestens eine Quelle aktiviert und synchronisiert
3. Datumsfilter / Shortcode `from`/`to` schließen Termine ein
4. `display.list.show_past: false` blendet alte Termine aus
5. Render-Cache — mit `bin/grav cache` leeren

## Datenbank- / Berechtigungsfehler

**Symptome:** `ERROR_STORAGE` in den Logs.

```bash
# Berechtigungen korrigieren
chown -R www-data:www-data user/plugins/opencalendar/data
chmod 775 user/plugins/opencalendar/data

# Integritätsprüfung
sqlite3 user/plugins/opencalendar/data/opencalendar.db "PRAGMA integrity_check;"
```

Bei Beschädigung DB-Datei umbenennen und erneut synchronisieren. Siehe [SQLite.md](SQLite.md).

## Admin-Upload schlägt fehl

**Symptome:** Upload-Formular zeigt einen Fehler; keine neue Quelle erscheint.

**Prüfen:**

1. Datei muss `.ics`, `.ical` oder `.json` sein und höchstens 10 MiB groß
2. ICS-Inhalt muss `BEGIN:VCALENDAR` enthalten; JSON muss gültig parsen
3. `user/data/opencalendar/uploads/` und `user/config/plugins/` müssen für den Webserver beschreibbar sein
4. PHP `upload_max_filesize` / `post_max_size` müssen die Dateigröße erlauben
5. Nach erfolgreichem Upload die Plugin-Seite neu laden, um die Quelle unter **Sources** zu sehen

Siehe [Synchronization.md](Synchronization.md#kalenderdatei-hochladen).

## Scheduler läuft nicht

**Symptome:** Termine werden nie automatisch aktualisiert.

1. [Grav Scheduler](https://github.com/trilbymedia/grav-plugin-scheduler) installieren
2. Prüfen, dass Cron jede Minute `bin/grav scheduler` aufruft
3. `advanced.scheduler.enabled: true` verifizieren
4. Scheduler-Admin auf registrierte OpenCalendar-Jobs prüfen

## Suche funktioniert nicht

1. `search.enabled: true`
2. Abfragelänge ≥ `min_query_length`
3. FTS-Index aufgebaut — nach Upgrade Sync ausführen
4. Feld in `search.fields` enthalten

## API liefert 503

API in der Konfiguration deaktiviert. `api.enabled: true` setzen und Cache leeren.

## API liefert 429

Rate-Limit überschritten. Warten oder `api.rate_limit.max_requests` anpassen.

## Falsche Zeitzone bei Terminen

1. `timezone` in der Konfiguration auf die Zielgruppen-Zeitzone setzen
2. Prüfen, ob Quell-ICS `VTIMEZONE` oder `TZID` enthält
3. Ganztägige Termine werden an lokalen Tagesgrenzen des Kalenders angezeigt

## Admin-Formularänderungen werden nicht gespeichert

1. Prüfen, ob `user/config/plugins/opencalendar.yaml` beschreibbar ist
2. Nach manuellen YAML-Änderungen Cache leeren
3. YAML-Syntax validieren (Einrückung, Anführungszeichen in URLs)

## Debug-Checkliste

Ausführliches Logging aktivieren:

```yaml
advanced:
  debug: true
  log_level: debug
```

Dann:

```bash
tail -f logs/opencalendar.log
tail -f logs/grav.log
```

## Immer noch blockiert?

[Bug-Report](https://github.com/TimUx/grav-plugin-opencalendar/issues/new?template=bug_report.md) mit:

- Grav-, PHP- und Plugin-Versionen
- Redigierte Konfiguration
- Relevante Log-Auszüge
- Schritte zur Reproduktion

## Verwandte Dokumentation

- [FAQ.md](FAQ.md)
- [Synchronization.md](Synchronization.md)
- [Configuration.md](Configuration.md)
