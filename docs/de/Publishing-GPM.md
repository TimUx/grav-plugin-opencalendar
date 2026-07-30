# Veröffentlichung im Grav Plugin Repository (GPM)

> English: [Publishing to the Grav Plugin Repository (GPM)](../en/Publishing-GPM.md)

Dieses Dokument beschreibt, was nötig ist, um OpenCalendar im offiziellen Grav Package Manager zu listen.

## Offizielle Anforderungen

Aus dem [Grav Theme/Plugin Release Process](https://learn.getgrav.org/17/advanced/grav-development#themeplugin-release-process):

1. Open Source mit MIT-kompatibler `LICENSE`
2. `README.md` mit Installations-, Konfigurations- und Nutzungsdokumentation
3. `blueprints.yaml` mit erforderlichen Identitätsfeldern (`name`, `slug`, `type`, `version`, …)
4. `CHANGELOG.md` im Grav-Changelog-Format
5. Attribution für Drittanbieter-Bibliotheken
6. Ein **GitHub Release / Tag** (GPM erkennt nicht getaggte Commits)
7. Ein Issue auf [getgrav/grav](https://github.com/getgrav/grav/issues) mit Titel `[add-resource] …`, in dem das Grav-Team gebeten wird, das Repository aufzunehmen

Nach der ersten Aufnahme werden spätere getaggte Releases automatisch erkannt.

## OpenCalendar-Checkliste

| Punkt | Status |
|-------|--------|
| Öffentliches GitHub-Repo `grav-plugin-opencalendar` | Erledigt |
| `LICENSE` (MIT) | Erledigt |
| `README.md` + `docs/` | Erledigt |
| `blueprints.yaml` (`slug: opencalendar`) | Erledigt |
| `CHANGELOG.md` (Grav-Format) | Erledigt |
| `opencalendar.php` / `opencalendar.yaml` | Erledigt |
| Sprachen EN/DE | Erledigt |
| CI / PHPUnit | Erledigt |
| GitHub-Release-Tag | Vor / bei Einreichung anlegen |
| Grav-Issue `[add-resource]` | Nach Release anlegen |
| Grav-2.0-Kompatibilitätsflag | Optional; derzeit nur `1.7` deklariert |

## Befehle für die Einreichung

```bash
# 1) Tag und Release
git tag -a v1.0.1 -m "OpenCalendar 1.0.1"
git push origin v1.0.1
gh release create v1.0.1 --title "v1.0.1" --notes-file CHANGELOG.md

# 2) Grav bitten, das Plugin zu listen
gh issue create -R getgrav/grav \
  --title "[add-resource] New Plugin: OpenCalendar" \
  --body-file docs/de/gpm-submission-issue.md
```

## Inhalt des Distributionspakets

Release-ZIPs für GPM / GitHub-„Source code“-Downloads werden über [`.gitattributes`](../../.gitattributes) (`export-ignore`) gefiltert. Composer-Dist-Archive nutzen die passende `archive.exclude`-Liste in `composer.json`.

**Enthalten (Laufzeit + Grav-Anforderungen):** Plugin-PHP/YAML, `classes/`, `languages/`, `templates/`, `assets/`, `admin/`, `blueprints*`, `composer.json` / `composer.lock`, `CHANGELOG.md`, `LICENSE`, `README.md`.

**Ausgeschlossen (nur Repo / GitHub):** `docs/`, `tests/`, `.github/`, Contributing/Security/Code-of-Conduct, `README.de.md`, PHPUnit/PHPStan/PHPCS-Konfiguration, Editor-Metadaten.

Ein `git clone` liefert weiterhin den vollen Entwicklungsbaum. Für Produktion GPM oder das Release-ZIP verwenden.

Lokal prüfen:

```bash
git archive --format=tar --worktree-attributes HEAD | tar -t | grep -E '^(docs|tests|\.github)/' || echo 'Dev-Pfade ausgeschlossen'
```

## Nach der Aufnahme

- `version` in `blueprints.yaml` erhöhen und für jedes Release einen CHANGELOG-Abschnitt hinzufügen
- Tags konsistent setzen (`v1.0.2`, `v1.1.0`, …) — GPM vergleicht Tags
- Für Grav 2.0 später: unter PHP 8.3 + Grav 2.0 testen, dann `'2.0'` unter `compatibility.grav` ergänzen
