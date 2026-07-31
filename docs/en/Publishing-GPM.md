# Publishing to the Grav Plugin Repository (GPM)

> Deutsch: [Veröffentlichung im Grav Plugin Repository (GPM)](../de/Publishing-GPM.md)

This document tracks what is required to list OpenCalendar in the official Grav Package Manager.

## Official requirements

From the [Grav Theme/Plugin Release Process](https://learn.getgrav.org/17/advanced/grav-development#themeplugin-release-process):

1. Open source with an MIT-compatible `LICENSE`
2. `README.md` with install/config/usage docs
3. `blueprints.yaml` with required identity fields (`name`, `slug`, `type`, `version`, …)
4. `CHANGELOG.md` in the Grav changelog format
5. Attribution for third-party libraries
6. A **GitHub Release / tag** (GPM will not see untagged commits)
7. An issue on [getgrav/grav](https://github.com/getgrav/grav/issues) titled `[add-resource] …` asking the Grav team to add the repository

After the first acceptance, later tagged releases are picked up automatically.

## OpenCalendar checklist

| Item | Status |
|------|--------|
| Public GitHub repo `grav-plugin-opencalendar` | Done |
| `LICENSE` (MIT) | Done |
| `README.md` + `docs/` | Done |
| `blueprints.yaml` (`slug: opencalendar`) | Done |
| `CHANGELOG.md` (Grav format) | Done |
| `opencalendar.php` / `opencalendar.yaml` | Done |
| Languages EN/DE | Done |
| CI / PHPUnit | Done |
| GitHub Release tag | Create before / with submission |
| Grav issue `[add-resource]` | Create after release |
| Grav 2.0 compatibility flag | Optional; currently declared `1.7` only |

## Commands used for submission

```bash
# 1) Tag and release
git tag -a v1.0.1 -m "OpenCalendar 1.0.1"
git push origin v1.0.1
gh release create v1.0.1 --title "v1.0.1" --notes-file CHANGELOG.md

# 2) Ask Grav to list the plugin
gh issue create -R getgrav/grav \
  --title "[add-resource] New Plugin: OpenCalendar" \
  --body-file docs/en/gpm-submission-issue.md
```

## Distribution package contents

Release ZIPs used by GPM / GitHub “Source code” downloads are filtered by [`.gitattributes`](../../.gitattributes) (`export-ignore`). Composer dist archives use the matching `archive.exclude` list in `composer.json`.

**Included (runtime + Grav requirements):** plugin PHP/YAML, `classes/`, `languages/`, `templates/`, `assets/`, `admin/`, `blueprints*`, `composer.json` / `composer.lock`, `CHANGELOG.md`, `LICENSE`, `README.md`.

**Excluded (repo / GitHub only):** `docs/`, `tests/`, `.github/`, contributing/security/code-of-conduct files, `README.de.md`, PHPUnit/PHPStan/PHPCS configs, editor metadata.

Cloning the repository still returns the full tree for development. Prefer GPM or the release ZIP for production installs.

Verify locally:

```bash
git archive --format=tar --worktree-attributes HEAD | tar -t | grep -E '^(docs|tests|\.github)/' || echo 'dev paths excluded'
```

## After listing

- Bump `version` in `blueprints.yaml` and add a CHANGELOG section for every release
- Tag consistently (`v1.0.2`, `v1.1.0`, …) — GPM compares tags
- Grav 2.0: OpenCalendar declares `compatibility.grav: ['1.7', '2.0']`. Admin Next uses `admin-next/fields/opencalendar_sync.js` and `/api/v1/opencalendar/*` (requires the Grav API plugin shipped with 2.0).
