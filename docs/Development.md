# Development

Guide for contributors working on OpenCalendar locally.

## Environment setup

```bash
git clone https://github.com/TimUx/grav-plugin-opencalendar.git
cd grav-plugin-opencalendar
composer install
```

Symlink into Grav:

```bash
ln -s "$(pwd)" /path/to/grav/user/plugins/opencalendar
```

## Quality toolchain

| Tool | Command | Config |
|------|---------|--------|
| PHPUnit | `composer test` | `phpunit.xml` |
| PHPStan | `composer phpstan` | `phpstan.neon` (level 8) |
| PHPCS | `composer phpcs` | `phpcs.xml` (PSR-12) |
| All | `composer check` | `composer.json` scripts |

CI runs the same checks on PHP 8.2 and 8.3 (see `.github/workflows/ci.yml`).

## Project structure

Implement new code under `classes/` using namespace `Grav\Plugin\OpenCalendar\`.

| Subnamespace / folder | Guidelines |
|-----------------------|------------|
| `Models` | Pure data objects, no I/O |
| `Dto` | Immutable outward-facing shapes |
| `Enum` | Backed enums for config values |
| `Storage` | SQLite only; no HTTP |
| `Source` | Feed fetching and parsing |
| `Services` | Orchestration, inject dependencies |
| `Controllers` | Grav request/response boundary |
| `Api` | JSON serialization |

Do not put business logic in `opencalendar.php` — use it only for hook registration and DI wiring.

## Coding standards

- PHP 8.2+ features allowed (readonly, enums, typed constants)
- Strict types in every file: `declare(strict_types=1);`
- PSR-12 formatting
- PHPStan level 8 must pass without baseline ignores unless justified in PR

## Testing

Place unit tests in `tests/Unit/` mirroring class structure:

```
tests/Unit/Source/IcsParserTest.php
```

Bootstrap: `tests/bootstrap.php` loads Composer autoloader only — no Grav required for pure unit tests.

Integration tests that need Grav should be tagged and documented separately when added.

## Adding configuration

1. Add defaults to `opencalendar.yaml`
2. Add Admin fields to `blueprints.yaml`
3. Add translation keys to all packs under `languages/` (`en`, `de`, `fr`, `es`, `nl`, `it`)

4. Document in `docs/Configuration.md`

Keep YAML and blueprint field paths synchronized.

## Frontend assets

- CSS: `assets/css/opencalendar.css`
- JS: `assets/js/opencalendar.js`
- Vendor libs: `assets/vendor/` (commit minified builds, document licenses)

Register assets in the plugin PHP entry via `onAssetsInitialized`.

## Debugging

```yaml
advanced:
  debug: true
  log_level: debug
```

Logs write to `logs/opencalendar.log` relative to Grav root.

## Release checklist

1. Update version in `blueprints.yaml`
2. Update `CHANGELOG.md`
3. Run `composer check`
4. Tag release: `git tag v1.0.1`

## Related documentation

- [Architecture.md](Architecture.md)
- [CONTRIBUTING.md](../CONTRIBUTING.md)
- [Migration.md](Migration.md)
