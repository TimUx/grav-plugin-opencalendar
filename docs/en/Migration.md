# Migration

> Deutsch: [Migration](../de/Migration.md)

Guidelines for upgrading OpenCalendar and migrating data between environments.

## Version numbering

OpenCalendar follows [Semantic Versioning](https://semver.org/):

- **Patch** (1.0.x) — bug fixes, safe to upgrade
- **Minor** (1.x.0) — new features, backward compatible config
- **Major** (x.0.0) — breaking changes, read release notes

Always read [CHANGELOG.md](../../CHANGELOG.md) before upgrading.

## Standard upgrade procedure

1. Back up the SQLite database:

   ```bash
   cp user/plugins/opencalendar/data/opencalendar.db ~/backup/opencalendar.db
   ```

2. Back up site config:

   ```bash
   cp user/config/plugins/opencalendar.yaml ~/backup/
   ```

3. Update plugin files via GPM or git pull.

4. Run Composer if needed:

   ```bash
   cd user/plugins/opencalendar && composer install --no-dev
   ```

5. Clear Grav cache:

   ```bash
   bin/grav cache
   ```

6. Visit Admin → Plugins to verify version.

Schema migrations run automatically on next request or sync.

## Config migration

When new config keys are added in a release, defaults from `opencalendar.yaml` merge with your existing `user/config/plugins/opencalendar.yaml`. You do not need to copy new keys manually unless you want non-default values.

Renamed keys will be documented in CHANGELOG with before/after examples.

## Database migration

Migrations live in `classes/Storage/Migrations/`. Each has a version number applied idempotently.

If migration fails:

1. Check `logs/opencalendar.log`
2. Restore database backup
3. Report issue with migration version and error

## Moving between servers

1. Copy `user/config/plugins/opencalendar.yaml`
2. Copy `user/plugins/opencalendar/data/opencalendar.db` (or re-sync from sources)
3. Ensure PHP extensions and Composer vendor folder are installed on target
4. Fix file permissions on `data/`

Alternatively, omit the database and trigger a full re-sync — suitable when sources are authoritative.

## Downgrading

Downgrading below a migration version is unsupported. Restore from backup or delete the database and re-sync.

## From other calendar plugins

There is no automatic importer from third-party Grav calendar plugins. Migration path:

1. Export events as ICS from the old system
2. Add as `local` or hosted ICS source in OpenCalendar
3. Sync and verify

## Breaking change policy

Major releases may change:

- Config key names
- Twig function signatures
- API response shapes
- Database schema (with migration)

Deprecation warnings appear in logs one minor release before removal when possible.

## Related documentation

- [Installation.md](Installation.md)
- [SQLite.md](SQLite.md)
- [CHANGELOG.md](../../CHANGELOG.md)
