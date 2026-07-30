# Entwicklung

> English: [Development](../en/Development.md)

Leitfaden für Mitwirkende, die OpenCalendar lokal entwickeln.

## Umgebung einrichten

```bash
git clone https://github.com/TimUx/grav-plugin-opencalendar.git
cd grav-plugin-opencalendar
composer install
```

Symlink in Grav:

```bash
ln -s "$(pwd)" /path/to/grav/user/plugins/opencalendar
```

## Qualitäts-Toolchain

| Tool | Befehl | Konfiguration |
|------|--------|---------------|
| PHPUnit | `composer test` | `phpunit.xml` |
| PHPStan | `composer phpstan` | `phpstan.neon` (Level 8) |
| PHPCS | `composer phpcs` | `phpcs.xml` (PSR-12) |
| Alle | `composer check` | `composer.json` scripts |

CI führt dieselben Prüfungen unter PHP 8.2 und 8.3 aus (siehe `.github/workflows/ci.yml`).

## Projektstruktur

Neuen Code unter `classes/` mit Namespace `Grav\Plugin\OpenCalendar\` implementieren.

| Subnamespace / Ordner | Richtlinien |
|-----------------------|-------------|
| `Models` | Reine Datenobjekte, kein I/O |
| `Dto` | Unveränderliche, nach außen gerichtete Strukturen |
| `Enum` | Backed Enums für Konfigurationswerte |
| `Storage` | Nur SQLite; kein HTTP |
| `Source` | Feed-Abruf und Parsing |
| `Services` | Orchestrierung, Abhängigkeiten injizieren |
| `Controllers` | Grav Request/Response-Grenze |
| `Api` | JSON-Serialisierung |

Keine Geschäftslogik in `opencalendar.php` — nur Hook-Registrierung und DI-Verdrahtung.

## Coding-Standards

- PHP-8.2+-Features erlaubt (readonly, enums, typisierte Konstanten)
- Strikte Typen in jeder Datei: `declare(strict_types=1);`
- PSR-12-Formatierung
- PHPStan Level 8 muss ohne Baseline-Ignores bestehen, sofern im PR nicht begründet

## Tests

Unit-Tests in `tests/Unit/` spiegeln die Klassenstruktur:

```
tests/Unit/Source/IcsParserTest.php
```

Bootstrap: `tests/bootstrap.php` lädt nur den Composer-Autoloader — für reine Unit-Tests ist Grav nicht erforderlich.

Integrationstests, die Grav benötigen, sollten getaggt und bei Hinzufügung separat dokumentiert werden.

## Konfiguration hinzufügen

1. Defaults in `opencalendar.yaml` ergänzen
2. Admin-Felder in `blueprints.yaml` hinzufügen
3. Übersetzungsschlüssel in allen Paketen unter `languages/` (`en`, `de`, `fr`, `es`, `nl`, `it`)

4. In `docs/de/Configuration.md` (und Englisch `docs/en/Configuration.md`) dokumentieren

YAML- und Blueprint-Feldpfade synchron halten.

## Frontend-Assets

- CSS: `assets/css/opencalendar.css`
- JS: `assets/js/opencalendar.js`
- Vendor-Bibliotheken: `assets/vendor/` (minifizierte Builds committen, Lizenzen dokumentieren)

Assets im Plugin-PHP-Einstieg über `onAssetsInitialized` registrieren.

## Debugging

```yaml
advanced:
  debug: true
  log_level: debug
```

Logs werden relativ zum Grav-Root nach `logs/opencalendar.log` geschrieben.

## Release-Checkliste

1. Version in `blueprints.yaml` aktualisieren
2. `CHANGELOG.md` aktualisieren
3. `composer check` ausführen
4. Release taggen: `git tag v1.0.1`

## Verwandte Dokumentation

- [Architecture.md](Architecture.md)
- [CONTRIBUTING.de.md](../../CONTRIBUTING.de.md)
- [Migration.md](Migration.md)
