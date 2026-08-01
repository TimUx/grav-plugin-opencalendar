# Installation

> English: [Installation](../en/Installation.md)

Diese Anleitung beschreibt die Installation von OpenCalendar auf einer Grav-CMS-Website.

## Voraussetzungen

| Anforderung | Version |
|-------------|---------|
| Grav | 1.7.0 oder höher |
| PHP | 8.2 oder höher |
| Erweiterungen | `pdo`, `pdo_sqlite`, `json`, `mbstring` |

Optional, aber empfohlen:

- [Grav Scheduler](https://github.com/trilbymedia/grav-plugin-scheduler) für zuverlässige Hintergrund-Synchronisation
- HTTPS für externe Kalenderquellen

## Installation über GPM (empfohlen)

Sobald OpenCalendar im Grav Plugin Repository veröffentlicht ist:

```bash
bin/gpm install opencalendar
bin/gpm direct-install -y TimUx/grav-plugin-opencalendar
```

Oder über das Admin-Panel: **Plugins → Hinzufügen** und nach OpenCalendar suchen.

GPM-Pakete enthalten den Produktions-Composer-Ordner `vendor/` (`sabre/vobject` und Klassen-Autoload). Auf dem Server ist kein `composer install` nötig. Nach der Installation ggf. den Grav-Cache leeren, falls Admin-UI oder Shortcodes noch fehlen:

```bash
bin/grav cache
```

## Manuelle Installation

Bevorzugt das **Release-ZIP** von [GitHub Releases](https://github.com/TimUx/grav-plugin-opencalendar/releases) (dasselbe gefilterte Paket wie GPM). Es enthält Laufzeitdateien plus Produktions-`vendor/` — nicht `docs/`, `tests/`, CI-Konfiguration oder GitHub-Community-Dateien. Die vollständige Dokumentation bleibt auf GitHub.

1. Aktuelles Release-Archiv herunterladen (oder GPM / `bin/gpm direct-install` nutzen).

2. Nach `opencalendar` in den Grav-Plugins-Ordner entpacken:

   ```bash
   unzip opencalendar-*.zip -d /path/to/grav/user/plugins/opencalendar
   ```

3. Grav-Cache leeren:

   ```bash
   cd /path/to/grav
   bin/grav cache
   ```

4. Plugin aktivieren unter **Admin → Plugins → OpenCalendar** oder in `user/config/plugins/opencalendar.yaml` eintragen:

   ```yaml
   enabled: true
   ```

### Entwicklungs-Checkout

Repository nur zum Mitwirken klonen. Dieser Checkout enthält Docs, Tests und Tooling. Der Produktions-`vendor/` liegt bereits bei; Composer ergänzt PHPUnit/PHPStan u. a.:

```bash
git clone https://github.com/TimUx/grav-plugin-opencalendar.git
composer install   # ergänzt require-dev (erweiterten vendor-Baum nicht committen)
```

Wenn `vendor/autoload.php` fehlt (unvollständige Kopie), Abhängigkeiten so wiederherstellen:

```bash
composer install --no-dev --optimize-autoloader
```

## Dateiberechtigungen

Der Webserver muss Schreibrechte haben für:

- `user/plugins/opencalendar/data/` (SQLite-Datenbank)
- `logs/` (Plugin-Logdatei, wenn konfiguriert)

Beispiel:

```bash
chown -R www-data:www-data user/plugins/opencalendar/data
chmod 775 user/plugins/opencalendar/data
```

## Installation prüfen

1. **Admin → Plugins** öffnen — OpenCalendar sollte als aktiviert erscheinen.
2. Mindestens eine Kalenderquelle unter dem Tab **Sources** hinzufügen **oder** unter **Synchronization → Kalenderdatei hochladen** eine `.ics`-/`.json`-Datei importieren.
3. Im Tab **Synchronization** auf **Jetzt synchronisieren** klicken (oder den Upload-Import nutzen) und prüfen, dass Termine in der Status-Tabelle erscheinen.
4. Eine Kalenderseite mit Twig oder Shortcodes anlegen (siehe [Twig.md](Twig.md) und [Shortcodes.md](Shortcodes.md)).

`user/data/opencalendar/` muss beschreibbar sein, wenn Sie Admin-Uploads oder den Standard-SQLite-Pfad nutzen.

## Aktualisierung

Siehe die [README](../../README.md#updating) und [Migration.md](Migration.md) beim Wechsel zwischen Hauptversionen.

## Deinstallation

1. Plugin im Admin deaktivieren.
2. `user/plugins/opencalendar` entfernen.
3. Optional `user/config/plugins/opencalendar.yaml`, die SQLite-Datenbank und `user/data/opencalendar/uploads/` löschen.

Zwischengespeicherte Termine im allgemeinen Grav-Cache werden beim Deaktivieren des Plugins geleert.
