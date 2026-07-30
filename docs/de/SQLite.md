# SQLite-Speicher

> English: [SQLite Storage](../en/SQLite.md)

OpenCalendar speichert normalisierte Ereignisse in SQLite für schnelle Abfragen, Suche und Resilienz, wenn Remote-Feeds nicht verfügbar sind.

## Datenbank-Speicherort

Standardpfad: `user/data/opencalendar/opencalendar.db`

Konfiguration über:

```yaml
storage:
  path: user-data://opencalendar/opencalendar.db
```

`user-data://` wird auf Gravs beschreibbares `user/data/`-Verzeichnis aufgelöst (empfohlen).

Legacy plugin-relative Pfade funktionieren weiterhin:

```yaml
storage:
  path: data/opencalendar.db
```

Dafür muss der Webserver-Benutzer (z. B. `www-data`) `user/plugins/opencalendar/data/` besitzen. Absolute Pfade werden für eigene Volumes ebenfalls unterstützt.

## Schema-Überblick

Die Datenbank enthält Tabellen für:

| Tabelle | Zweck |
|---------|-------|
| `sources` | Registrierte Quell-Metadaten und Sync-Status |
| `events` | Normalisierte Ereigniszeilen mit Start/Ende, Textfeldern |
| `event_sources` | n:m-Verknüpfung bei Deduplizierung mehrerer Feeds |
| `categories` | Kategorie-Tags pro Ereignis |
| `sync_log` | Historische Sync-Läufe zum Debugging |
| `schema_migrations` | Angewendete Migrationsversionen |

Volltextsuche nutzt SQLite FTS5 auf Titel, Beschreibung und Ort, sofern verfügbar.

## WAL-Modus

Write-Ahead Logging (`storage.wal_mode: true`) ist standardmäßig aktiviert:

- Leser blockieren Schreiber nicht
- Bessere Nebenläufigkeit für ausgelastete Sites
- Erzeugt `-wal`- und `-shm`-Begleitdateien neben der Datenbank

Nur deaktivieren, wenn Ihre Hosting-Umgebung bekannte WAL-Probleme hat (selten).

## Migrationen

Schemaänderungen werden als nummerierte Migrationen in `classes/Storage/Migrations/` ausgeliefert. Migrationen laufen automatisch bei Plugin-Init oder erstem Sync.

Die Datenbank in Produktion niemals manuell bearbeiten, außer gemäß dokumentiertem Wiederherstellungsverfahren.

## Backups

Die Datenbank in Ihre Site-Backup-Strategie einbeziehen:

```bash
sqlite3 user/plugins/opencalendar/data/opencalendar.db ".backup backup/opencalendar-$(date +%F).db"
```

Oder die Datei kopieren, während Grav im Leerlauf ist / das Plugin deaktiviert ist.

## Wartung

### Bereinigung

Soft-gelöschte Zeilen werden gemäß `cleanup`-Richtlinie entfernt. Siehe [Synchronization.md](Synchronization.md).

### VACUUM

Wenn `storage.vacuum_on_cleanup` aktiviert ist, gibt SQLite nach der Bereinigung freie Seiten zurück. Das kann die Datenbank kurz sperren — bei geringem Traffic planen.

Manuelles Vacuum:

```bash
sqlite3 user/plugins/opencalendar/data/opencalendar.db "VACUUM;"
```

### Integritätsprüfung

```bash
sqlite3 user/plugins/opencalendar/data/opencalendar.db "PRAGMA integrity_check;"
```

## Berechtigungen

Der Webserver-Benutzer benötigt Lese-/Schreibzugriff auf Datei und Verzeichnis der Datenbank:

```bash
chmod 775 user/plugins/opencalendar/data
chown www-data:www-data user/plugins/opencalendar/data/opencalendar.db
```

## Größenerwartungen

Grobe Schätzungen:

| Ereignisse | Ungefähre Größe |
|------------|-----------------|
| 1.000 | 1–2 MB |
| 10.000 | 10–20 MB |
| 100.000 | 100+ MB |

Wiederkehrende Expansion erhöht die Zeilenanzahl deutlich — `recurring_horizon_days` anpassen.

## Wiederherstellung

Wenn die Datenbank beschädigt ist:

1. Plugin deaktivieren.
2. Beschädigte Datei umbenennen oder entfernen.
3. Wieder aktivieren — ein frisches Schema wird beim nächsten Init erstellt.
4. Vollständigen Sync auslösen, um neu zu befüllen.

## Verwandte Dokumentation

- [Architecture.md](Architecture.md)
- [Synchronization.md](Synchronization.md)
- [Migration.md](Migration.md)
