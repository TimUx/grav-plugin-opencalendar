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

## Grav CMS 1.7 → 2.0

OpenCalendar **1.3.0+** unterstützt beide Grav-Major-Versionen in einem Paket (`compatibility.grav: ['1.7', '2.0']`). Ein separates `opencalendar2`-Plugin ist **nicht** nötig.

| Bereich | Grav 1.7 | Grav 2.0 |
|---------|----------|----------|
| Config-Blueprints | Classic Admin | Admin Next (gleiche YAML) |
| Sync-Dashboard-Feld | Twig `opencalendar_sync` | Web Component `admin-next/fields/opencalendar_sync.js` |
| Sync-/Upload-Aktionen | `/admin/plugins/opencalendar/*` | `/api/v1/opencalendar/*` (Grav-API-Plugin) |
| Dashboard | Classic-Widget | Admin-Next-Benachrichtigungen |
| Frontend Twig / Shortcodes | Unverändert | Sandbox-Allow-List via `onBuildTwigSandboxPolicy` |

Bei der Migration mit dem offiziellen Migrate-to-Grav-2.0-Wizard gilt OpenCalendar ab 1.3.0+ als 2.0-kompatibel. Config und SQLite unter `user/data/opencalendar/` werden wie andere Plugin-Daten übernommen.

Voraussetzungen unter Grav 2.0: PHP 8.3+ (Plattform-Minimum), Grav-API-Plugin aktiv (liegt 2.0 bei).

## Verwandte Dokumentation

- [Installation.md](Installation.md)
- [SQLite.md](SQLite.md)
- [CHANGELOG.md](../../CHANGELOG.md)
