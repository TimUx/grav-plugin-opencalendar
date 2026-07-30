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

## After listing

- Bump `version` in `blueprints.yaml` and add a CHANGELOG section for every release
- Tag consistently (`v1.0.2`, `v1.1.0`, …) — GPM compares tags
- To support Grav 2.0 later: test on PHP 8.3 + Grav 2.0, then add `'2.0'` under `compatibility.grav`
