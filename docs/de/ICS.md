# ICS / iCalendar-Unterstützung

> English: [ICS / iCalendar Support](../en/ICS.md)

OpenCalendar verwendet [Sabre VObject](https://github.com/sabre-io/vobject), um ICS- (iCalendar-)Feeds zu parsen — das Standardformat für `.ics`-Dateien und viele öffentliche Kalender-Abonnements.

## Unterstützte Komponenten

| Komponente | Unterstützung |
|------------|---------------|
| `VEVENT` | Vollständiger Import |
| `VTIMEZONE` | Wird auf schwebende und zonierte Zeiten angewendet |
| `VALARM` | Gespeichert, wenn vorhanden; Anzeige optional |
| `VCALENDAR`-Wrapper | Mehrere Kalender in einer Datei |

## Wiederkehrende Ereignisse

Wenn `advanced.import.expand_recurring` aktiviert ist:

- `RRULE`-Vorkommen werden in einzelne Datenbankzeilen materialisiert
- Expansionshorizont: `advanced.import.recurring_horizon_days` (Standard 365)
- `EXDATE`- und `RECURRENCE-ID`-Ausnahmen werden berücksichtigt

Expansion für Feeds mit sehr langen unendlichen Wiederholungen deaktivieren, wenn die Datenbankgröße ein Anliegen ist.

## Zeitzonen

Priorität für Ereignis-Start/Ende:

1. `TZID`-Parameter bei `DTSTART`/`DTEND`
2. `VTIMEZONE`-Definitionen im Feed
3. Plugin-Standard-`timezone` aus der Konfiguration
4. UTC-Fallback

Stellen Sie sicher, dass `timezone` in der Konfiguration zu Ihrer Zielgruppe passt, wenn Feeds schwebende Zeiten verwenden.

## Auf Ereignisse abgebildete Felder

| ICS-Eigenschaft | Ereignisfeld |
|-----------------|--------------|
| `SUMMARY` | title |
| `DESCRIPTION` | description |
| `LOCATION` | location |
| `DTSTART` / `DTEND` | start / end |
| `UID` | uid (Deduplizierungsschlüssel) |
| `CATEGORIES` | categories |
| `URL` | external link |
| `STATUS` | status (confirmed, cancelled, …) |

Abgesagte Ereignisse (`STATUS:CANCELLED`) werden importiert, können in Frontend-Ansichten je nach Filter-Standard ausgeblendet sein.

## Ganztägige Ereignisse

Ganztägige Ereignisse (`VALUE=DATE`) werden mit der übersetzten Bezeichnung „All day“ angezeigt und erstrecken sich in der Monatsansicht über volle Kalendertage.

## Häufige Feed-Quellen

| Anbieter | Hinweise |
|----------|----------|
| Google Calendar | „Public address in iCal format“ in den Kalendereinstellungen verwenden |
| Outlook / Microsoft 365 | ICS-Link aus der Kalenderfreigabe veröffentlichen |
| Apple iCloud | Geteilte schreibgeschützte ICS-URL |
| Nextcloud | Öffentlicher Link oder CalDAV (CalDAV bevorzugt für private Kalender) |

## Abruf-Hinweise

- Viele Anbieter begrenzen aggressives Polling — `refresh: 30` oder höher verwenden
- Öffentliche Google-ICS-URLs sind stabil, können aber Stunden hinter der Web-UI zurückliegen
- Große ICS-Dateien (>5 MB) erfordern ggf. erhöhtes `advanced.http.timeout`

## Validierungsfehler

Fehlerhaftes ICS erzeugt Parse-Fehler, die auf Warnstufe protokollliert werden. Der zuletzt erfolgreiche Import bleibt verfügbar, bis der Feed wieder parst.

Debugging-Schritte:

1. ICS manuell herunterladen: `curl -o /tmp/feed.ics 'URL'`
2. Mit externen Tools validieren (z. B. Python-Bibliothek `icalendar`)
3. `advanced.debug` aktivieren und erneut synchronisieren

## HTML in Beschreibungen

Manche Feeds betten HTML in `DESCRIPTION` ein. Standardmäßig bleibt HTML erhalten. Mit `advanced.import.strip_html: true` nur Klartext speichern.

## Export / Abonnieren (Netzwerk-Kalender)

OpenCalendar veröffentlicht einen aggregierten `text/calendar`-Feed aus SQLite zum **Abonnieren** in Smartphones und Mail-Apps:

```yaml
export:
  enabled: true
  route: /opencalendar/calendar.ics
  calendar_name: OpenCalendar
  refresh_minutes: 60
  default_from: '-30 days'
  default_to: '+365 days'
```

```twig
<a href="{{ opencalendar_webcal_url() }}">Subscribe</a>
<code>{{ opencalendar_export_url({ source: 'team' }) }}</code>
```

Vollständige Geräteanleitungen: [Subscribe.md](Subscribe.md).

Wenn die JSON-API aktiviert ist, ist derselbe Feed auch unter `{api.route}/export.ics` verfügbar.

Exportierte Felder umfassen UID, SUMMARY, DESCRIPTION, LOCATION, URL, STATUS, CATEGORIES, DTSTART/DTEND (UTC oder VALUE=DATE für ganztägig), `REFRESH-INTERVAL` und optionale X-OPENCALENDAR-SOURCE-Metadaten.

## Verwandte Dokumentation

- [Sources.md](Sources.md)
- [Synchronization.md](Synchronization.md)
- [Troubleshooting.md](Troubleshooting.md)
