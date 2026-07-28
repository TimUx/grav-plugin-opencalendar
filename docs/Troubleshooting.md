# Troubleshooting

Solutions for common OpenCalendar problems.

## Plugin not visible in Admin

**Symptoms:** OpenCalendar missing from Plugins list.

**Checks:**

1. Folder must be named `opencalendar` under `user/plugins/`
2. `blueprints.yaml` must exist at plugin root
3. Clear cache: `bin/grav cache`
4. Verify file permissions allow PHP to read the plugin folder

## Sync failures

**Symptoms:** Events outdated; log shows `ERROR_SYNC_FAILED`.

### HTTP errors

| Code | Action |
|------|--------|
| 401/403 | Verify `auth` credentials; use app passwords for CalDAV |
| 404 | Confirm URL; test with `curl -I 'URL'` |
| timeout | Increase `advanced.http.timeout` |
| SSL error | Check certificate or temporarily set `verify_ssl: false` (not for production) |

### Parse errors

1. Download feed manually and inspect format
2. Validate ICS structure
3. Enable `advanced.debug` and re-sync
4. See [ICS.md](ICS.md)

## Empty calendar on frontend

**Checks:**

1. Plugin enabled: `enabled: true`
2. At least one source enabled and synced
3. Date filters / shortcode `from`/`to` include events
4. `display.list.show_past: false` hiding old events
5. Render cache — clear with `bin/grav cache`

## Database / permission errors

**Symptoms:** `ERROR_STORAGE` in logs.

```bash
# Fix permissions
chown -R www-data:www-data user/plugins/opencalendar/data
chmod 775 user/plugins/opencalendar/data

# Integrity check
sqlite3 user/plugins/opencalendar/data/opencalendar.db "PRAGMA integrity_check;"
```

If corrupt, rename DB file and re-sync. See [SQLite.md](SQLite.md).

## Scheduler not running

**Symptoms:** Events never update automatically.

1. Install [Grav Scheduler](https://github.com/trilbymedia/grav-plugin-scheduler)
2. Confirm cron hits `bin/grav scheduler` every minute
3. Verify `advanced.scheduler.enabled: true`
4. Check scheduler admin for registered OpenCalendar jobs

## Search not working

1. `search.enabled: true`
2. Query length ≥ `min_query_length`
3. FTS index built — run sync after upgrade
4. Field included in `search.fields`

## API returns 503

API disabled in config. Set `api.enabled: true` and clear cache.

## API returns 429

Rate limit exceeded. Wait or adjust `api.rate_limit.max_requests`.

## Wrong timezone on events

1. Set `timezone` in config to your audience timezone
2. Verify source ICS includes `VTIMEZONE` or `TZID`
3. All-day events display in calendar local day boundaries

## Admin form changes not saved

1. Check `user/config/plugins/opencalendar.yaml` is writable
2. Clear cache after manual YAML edits
3. Validate YAML syntax (indentation, quotes in URLs)

## Debug checklist

Enable verbose logging:

```yaml
advanced:
  debug: true
  log_level: debug
```

Then:

```bash
tail -f logs/opencalendar.log
tail -f logs/grav.log
```

## Still stuck?

Open a [bug report](https://github.com/TimUx/grav-plugin-opencalendar/issues/new?template=bug_report.md) with:

- Grav, PHP, and plugin versions
- Redacted config
- Relevant log excerpts
- Steps to reproduce

## Related documentation

- [FAQ.md](FAQ.md)
- [Synchronization.md](Synchronization.md)
- [Configuration.md](Configuration.md)
