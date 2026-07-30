# Migration

> English: [Migration](../en/Migration.md)

Richtlinien für OpenCalendar-Upgrades und die Migration von Daten zwischen Umgebungen.

## Versionsnummerierung

OpenCalendar folgt [Semantic Versioning](https://semver.org/):

- **Patch** (1.0.x) — Fehlerbehebungen, sicheres Upgrade
- **Minor** (1.x.0) — neue Funktionen, abwärtskompatible Konfiguration
- **Major** (x.0.0) — Breaking Changes, Release Notes lesen

Vor jedem Upgrade [CHANGELOG.md](../../CHANGELOG.md) lesen.

## Standard-Upgrade-Vorgehen

1. SQLite-Datenbank sichern:

   ```bash
   cp user/plugins/opencalendar/data/opencalendar.db ~/backup/opencalendar.db
   ```

2. Website-Konfiguration sichern:

   ```bash
   cp user/config/plugins/opencalendar.yaml ~/backup/
   ```

3. Plugin-Dateien über GPM oder git pull aktualisieren.

4. Composer bei Bedarf ausführen:

   ```bash
   cd user/plugins/opencalendar && composer install --no-dev
   ```

5. Grav-Cache leeren:

   ```bash
   bin/grav cache
   ```

6. Admin → Plugins öffnen und Version prüfen.

Schema-Migrationen laufen automatisch beim nächsten Request oder Sync.

## Konfigurationsmigration

Wenn in einem Release neue Konfigurationsschlüssel hinzukommen, werden Defaults aus `opencalendar.yaml` mit der bestehenden `user/config/plugins/opencalendar.yaml` zusammengeführt. Neue Schlüssel müssen nicht manuell kopiert werden, es sei denn, Nicht-Standardwerte sind gewünscht.

Umbenannte Schlüssel werden im CHANGELOG mit Vorher/Nachher-Beispielen dokumentiert.

## Datenbankmigration

Migrationen liegen in `classes/Storage/Migrations/`. Jede hat eine Versionsnummer und wird idempotent angewendet.

Schlägt eine Migration fehl:

1. `logs/opencalendar.log` prüfen
2. Datenbank-Backup wiederherstellen
3. Issue mit Migrationsversion und Fehler melden

## Umzug zwischen Servern

1. `user/config/plugins/opencalendar.yaml` kopieren
2. `user/plugins/opencalendar/data/opencalendar.db` kopieren (oder aus Quellen neu synchronisieren)
3. PHP-Erweiterungen und Composer-vendor-Ordner auf dem Zielsystem installieren
4. Dateiberechtigungen für `data/` anpassen

Alternativ die Datenbank weglassen und vollständig neu synchronisieren — geeignet, wenn die Quellen maßgeblich sind.

## Downgrade

Downgrade unterhalb einer Migrationsversion wird nicht unterstützt. Aus Backup wiederherstellen oder Datenbank löschen und neu synchronisieren.

## Von anderen Kalender-Plugins

Es gibt keinen automatischen Importer von Kalender-Plugins Dritter für Grav. Migrationsweg:

1. Termine als ICS aus dem alten System exportieren
2. Als `local`- oder gehostete ICS-Quelle in OpenCalendar hinzufügen
3. Synchronisieren und prüfen

## Richtlinie für Breaking Changes

Major-Releases können ändern:

- Namen von Konfigurationsschlüsseln
- Twig-Funktionssignaturen
- Formen von API-Antworten
- Datenbankschema (mit Migration)

Deprecations-Warnungen erscheinen nach Möglichkeit ein Minor-Release vor Entfernung in den Logs.

## Verwandte Dokumentation

- [Installation.md](Installation.md)
- [SQLite.md](SQLite.md)
- [CHANGELOG.md](../../CHANGELOG.md)
