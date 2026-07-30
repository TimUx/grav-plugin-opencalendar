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

## Manuelle Installation

1. Repository herunterladen oder klonen:

   ```bash
   git clone https://github.com/TimUx/grav-plugin-opencalendar.git
   ```

2. In den Grav-Plugins-Ordner kopieren oder verlinken:

   ```bash
   cp -R grav-plugin-opencalendar /path/to/grav/user/plugins/opencalendar
   ```

3. PHP-Abhängigkeiten installieren:

   ```bash
   cd /path/to/grav/user/plugins/opencalendar
   composer install --no-dev --optimize-autoloader
   ```

   Für die Entwicklung `--no-dev` weglassen, um PHPUnit, PHPStan und PHPCS einzuschließen.

4. Grav-Cache leeren:

   ```bash
   cd /path/to/grav
   bin/grav cache
   ```

5. Plugin aktivieren unter **Admin → Plugins → OpenCalendar** oder in `user/config/plugins/opencalendar.yaml` eintragen:

   ```yaml
   enabled: true
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
2. Mindestens eine Kalenderquelle unter dem Tab **Sources** hinzufügen.
3. Einmal manuell synchronisieren (CLI oder Scheduler), sobald die Implementierung verfügbar ist.
4. Eine Kalenderseite mit Twig oder Shortcodes anlegen (siehe [Twig.md](Twig.md) und [Shortcodes.md](Shortcodes.md)).

## Aktualisierung

Siehe die [README](../../README.md#updating) und [Migration.md](Migration.md) beim Wechsel zwischen Hauptversionen.

## Deinstallation

1. Plugin im Admin deaktivieren.
2. `user/plugins/opencalendar` entfernen.
3. Optional `user/config/plugins/opencalendar.yaml` und die SQLite-Datenbank löschen.

Zwischengespeicherte Termine im allgemeinen Grav-Cache werden beim Deaktivieren des Plugins geleert.
