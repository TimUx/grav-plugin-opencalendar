# Installation

> Deutsch: [Installation](../de/Installation.md)

This guide covers installing OpenCalendar on a Grav CMS site.

## Requirements

| Requirement | Version |
|-------------|---------|
| Grav | 1.7.0 or higher |
| PHP | 8.2 or higher |
| Extensions | `pdo`, `pdo_sqlite`, `json`, `mbstring` |

Optional but recommended:

- [Grav Scheduler](https://github.com/trilbymedia/grav-plugin-scheduler) for reliable background sync
- HTTPS for remote calendar sources

## Install via GPM (recommended)

When OpenCalendar is published to the Grav Plugin Repository:

```bash
bin/gpm install opencalendar
bin/gpm direct-install -y TimUx/grav-plugin-opencalendar
```

Or from the Admin panel: **Plugins → Add** and search for OpenCalendar.

GPM packages include the production Composer `vendor/` folder (`sabre/vobject` and class autoload). No `composer install` is required on the server. Clear Grav cache after install if the Admin UI or shortcodes do not appear yet:

```bash
bin/grav cache
```

## Manual installation

Prefer the **release ZIP** from [GitHub Releases](https://github.com/TimUx/grav-plugin-opencalendar/releases) (same filtered package GPM uses). It contains runtime plugin files plus production `vendor/` — not `docs/`, `tests/`, CI configs, or GitHub community files. Full documentation stays on GitHub.

1. Download the latest release source archive (or use GPM / `bin/gpm direct-install`).

2. Extract into your Grav plugins folder as `opencalendar`:

   ```bash
   unzip opencalendar-*.zip -d /path/to/grav/user/plugins/opencalendar
   ```

3. Clear Grav cache:

   ```bash
   cd /path/to/grav
   bin/grav cache
   ```

4. Enable the plugin in **Admin → Plugins → OpenCalendar**, or add to `user/config/plugins/opencalendar.yaml`:

   ```yaml
   enabled: true
   ```

### Development checkout

Clone the repository only when contributing. That checkout includes docs, tests, and tooling. Production `vendor/` is already present; run Composer to add PHPUnit/PHPStan and friends:

```bash
git clone https://github.com/TimUx/grav-plugin-opencalendar.git
composer install   # adds require-dev (do not commit the expanded vendor tree)
```

If `vendor/autoload.php` is missing (incomplete copy), restore dependencies with:

```bash
composer install --no-dev --optimize-autoloader
```

## File permissions

Ensure the web server can write to:

- `user/plugins/opencalendar/data/` (SQLite database)
- `logs/` (plugin log file when configured)

Example:

```bash
chown -R www-data:www-data user/plugins/opencalendar/data
chmod 775 user/plugins/opencalendar/data
```

## Verify installation

1. Open **Admin → Plugins** — OpenCalendar should appear as enabled.
2. Add at least one calendar source under the **Sources** tab, **or** upload an `.ics` / `.json` file under **Synchronization → Upload calendar file**.
3. On the **Synchronization** tab, click **Synchronize now** (or rely on the upload import) and confirm events appear in the status table.
4. Add a calendar page using Twig or shortcodes (see [Twig.md](Twig.md) and [Shortcodes.md](Shortcodes.md)).

Ensure `user/data/opencalendar/` is writable if you use Admin uploads or the default SQLite path.

## Upgrading

See the main [README](../../README.md#updating) and [Migration.md](Migration.md) when moving between major versions.

## Uninstalling

1. Disable the plugin in Admin.
2. Remove `user/plugins/opencalendar`.
3. Optionally delete `user/config/plugins/opencalendar.yaml`, the SQLite database, and `user/data/opencalendar/uploads/`.

Cached events in Grav's general cache are cleared when the plugin is disabled.
