# Sicherheitsrichtlinie

> English: [Security Policy](SECURITY.md)

## Unterstützte Versionen

| Version | Unterstützt |
|---------|-------------|
| 1.0.x   | Ja          |

Sicherheitsfixes erscheinen für die jeweils neueste Minor-Version. Aktualisieren Sie nach Möglichkeit auf das neueste Patch-Release.

## Schwachstelle melden

**Bitte melden Sie Sicherheitslücken nicht über öffentliche GitHub-Issues.**

Melden Sie sie stattdessen privat per E-Mail an den Maintainer oder über ein [GitHub Security Advisory](https://github.com/TimUx/grav-plugin-opencalendar/security/advisories/new).

Bitte angeben:

- Beschreibung der Schwachstelle und mögliche Auswirkungen
- Schritte zur Reproduktion
- Betroffene Versionen
- Proof-of-Concept-Code oder Logs (Secrets schwärzen)
- Vorgeschlagene Behebung, falls vorhanden

Sie sollten innerhalb von **72 Stunden** eine Bestätigung erhalten. Wir arbeiten mit Ihnen an einer Behebung und koordinieren den Zeitpunkt der Veröffentlichung.

## Geltungsbereich

Folgendes liegt im Scope von Sicherheitsmeldungen:

- Umgehung von Authentifizierung oder Autorisierung in der OpenCalendar-API
- SQL-Injection oder unsichere Deserialisierung in Storage-Schichten
- Server-Side Request Forgery (SSRF) über Kalender-Quell-URLs
- Cross-Site Scripting (XSS) in gerenderten Terminausgaben oder Admin-Formularen
- Offenlegung von für CalDAV-Quellen gespeicherten Zugangsdaten

Nicht im Scope:

- Denial of Service durch absichtlich große ICS-Dateien, wenn kein Rate Limiting dokumentiert ist
- Probleme in Grav Core oder Drittanbieter-Themes, sofern nicht direkt durch OpenCalendar ausgelöst
- Social Engineering oder Szenarien mit physischem Zugriff

## Sichere Konfiguration

- CalDAV-Zugangsdaten in umgebungsspezifischen Config-Overrides speichern, nicht in der Versionskontrolle.
- API-Zugriff einschränken, wenn OpenCalendar auf öffentlichen Sites exponiert ist (siehe [docs/de/API.md](docs/de/API.md)).
- HTTPS für remote ICS- und CalDAV-Endpunkte verwenden.
- PHP und Grav aktuell halten.

## Anerkennung

Reporter werden in Release Notes genannt, wenn sie dem zustimmen. Derzeit gibt es kein Bug-Bounty-Programm.
