# Mitwirken an OpenCalendar

> English: [Contributing to OpenCalendar](CONTRIBUTING.md)

Vielen Dank für Ihr Interesse an OpenCalendar! Dieses Dokument erklärt, wie Sie starten, was wir von Beiträgen erwarten und wie Sie Änderungen einreichen.

## Verhaltenskodex

Dieses Projekt folgt dem [Contributor Covenant](CODE_OF_CONDUCT.de.md) ([EN](CODE_OF_CONDUCT.md)). Mit Ihrer Teilnahme stimmen Sie zu, ein respektvolles und inklusives Umfeld zu fördern.

## Erste Schritte

### Voraussetzungen

- PHP 8.2 oder höher mit Erweiterungen: `pdo`, `pdo_sqlite`, `json`, `mbstring`
- Composer 2.x
- Eine lokale Grav-Installation (empfohlen für Integrationstests)

### Lokale Einrichtung

1. Repository forken und klonen:

   ```bash
   git clone https://github.com/TimUx/grav-plugin-opencalendar.git
   cd grav-plugin-opencalendar
   ```

2. Entwicklungs-Abhängigkeiten installieren (Produktions-`vendor/` ist für GPM bereits committed; das ergänzt PHPUnit/PHPStan usw. — den erweiterten Baum nicht committen):

   ```bash
   composer install
   ```

3. Plugin per Symlink oder Kopie in Ihre Grav-Instanz einbinden:

   ```bash
   ln -s "$(pwd)" /path/to/grav/user/plugins/opencalendar
   ```

4. Grav-Cache nach Konfigurationsänderungen leeren:

   ```bash
   bin/grav cache
   ```

### Entwicklungsbefehle

| Befehl | Beschreibung |
|--------|--------------|
| `composer test` | PHPUnit-Unit-Tests ausführen |
| `composer phpstan` | Statische Analyse (Level 8) |
| `composer phpcs` | PSR-12-Code-Stil prüfen |
| `composer check` | Alle Qualitätsprüfungen ausführen |

Siehe [docs/de/Development.md](docs/de/Development.md) für Architekturhinweise und Coding-Konventionen.

## Wie Sie mitwirken können

### Fehler melden

Nutzen Sie die [Bug-Report-Vorlage](.github/ISSUE_TEMPLATE/bug_report.md) und geben Sie an:

- Grav-Version und PHP-Version
- Plugin-Version
- Schritte zur Reproduktion
- Erwartetes vs. tatsächliches Verhalten
- Relevante Log-Auszüge aus `logs/grav.log` oder `logs/opencalendar.log`

### Features vorschlagen

Nutzen Sie die [Feature-Request-Vorlage](.github/ISSUE_TEMPLATE/feature_request.md). Beschreiben Sie das Problem, das Sie lösen möchten, und Alternativen, die Sie erwogen haben.

### Pull Requests

1. Feature-Branch von `develop` (oder `main`, falls kein develop-Branch existiert) erstellen.
2. Änderungen fokussiert halten — ein logischer Change pro Pull Request.
3. Tests hinzufügen oder aktualisieren, wenn sich das Verhalten ändert.
4. Vor dem Einreichen `composer check` ausführen.
5. `CHANGELOG.md` im passenden Abschnitt aktualisieren (`new`, `improved`, `bugfix`).
6. Pull-Request-Vorlage vollständig ausfüllen.

### Commit-Nachrichten

Klare Commit-Nachrichten im Imperativ schreiben:

```
Add CalDAV sync retry with exponential backoff

Explain why the change is needed in the body when non-obvious.
```

### Coding-Standards

- PSR-12 für PHP-Code befolgen (`composer phpcs`).
- PHPStan Level 8 anstreben (`composer phpstan`).
- Implementierungsklassen unter `classes/` mit Namespace `Grav\Plugin\OpenCalendar\` ablegen.
- Keine Secrets, Zugangsdaten oder echten Kalender-URLs aus Produktionssystemen committen.

### Dokumentation

Relevante Dateien unter `docs/de/` und `docs/en/` aktualisieren, wenn sich nutzersichtbares Verhalten, Konfigurationsoptionen oder API-Verträge ändern. `README.de.md` / `README.md` bei größeren Feature-Ergänzungen synchron halten.

## Release-Prozess

Maintainer folgen [Semantic Versioning](https://semver.org/):

- **Patch** — Fehlerbehebungen, keine API-Änderungen
- **Minor** — abwärtskompatible Features
- **Major** — Breaking Changes

Jedes Release aktualisiert `CHANGELOG.md`, erhöht die Version in `blueprints.yaml` und `opencalendar.yaml` und taggt den Commit.

## Fragen

Öffnen Sie eine [GitHub Discussion](https://github.com/TimUx/grav-plugin-opencalendar/discussions) oder ein Issue, wenn Sie vor größerer Arbeit Orientierung brauchen.
