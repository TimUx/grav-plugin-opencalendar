# FAQ

> English: [FAQ](../en/FAQ.md)

Häufig gestellte Fragen zu OpenCalendar.

## Allgemein

### Was ist OpenCalendar?

OpenCalendar ist ein Grav-CMS-Plugin, das Termine aus ICS-, CalDAV-, JSON- und lokalen Dateiquellen in einer SQLite-Datenbank zusammenführt und über Kalenderansichten, Listen, Twig, Shortcodes und eine optionale REST-API anzeigt.

### Welche Grav-Versionen werden unterstützt?

Grav 1.7.0 und höher. PHP 8.2+ ist erforderlich.

### Funktioniert es ohne Grav Admin?

Ja. Konfiguration manuell über `user/config/plugins/opencalendar.yaml`. Admin bietet eine bequeme Oberfläche, ist aber nicht erforderlich.

## Quellen und Sync

### Wie oft werden Kalender aktualisiert?

Standardmäßig alle 15 Minuten über den Grav Scheduler. `sync_interval` global oder pro Quelle ändern.

### Kann ich ICS- und CalDAV-Quellen mischen?

Ja. Jeder Quelleneintrag hat eigenen Typ und eigene Anmeldedaten.

### Kann ich im Admin eine Kalenderdatei hochladen?

Ja. Unter **Plugins → OpenCalendar → Synchronization** den Bereich **Kalenderdatei hochladen** nutzen, eine `.ics`-/`.ical`-/`.json`-Datei wählen und **Hochladen und importieren** klicken. Die Datei landet unter `user/data/opencalendar/uploads/`, wird als lokale Quelle registriert und sofort importiert.

Details: [Synchronization.md](Synchronization.md#kalenderdatei-hochladen) und [Sources.md](Sources.md#admin-upload).

### Warum fehlen meine Termine?

Typische Ursachen: Quelle deaktiviert, Sync-Fehler, Datumsfilter schließt Termine aus oder Wiederholungs-Horizont zu kurz. Siehe [Troubleshooting.md](Troubleshooting.md).

### Funktioniert Google Kalender?

Ja, über die öffentliche ICS-Adresse aus den Google-Kalender-Einstellungen. Private Google-Kalender benötigen einen teilbaren ICS-Link oder CalDAV mit Anmeldedaten.

## Abonnement auf Smartphones

### Können Besucher OpenCalendar-Termine auf dem Smartphone abonnieren?

Ja. ICS-Abonnement-Feed unter **Advanced → ICS export / subscription** aktivieren. Besucher (oder Sie) fügen die Feed-URL als abonnierter/Netzwerk-Kalender hinzu.

- Deutsche Schritt-für-Schritt-Anleitung (iPhone & Android): [Subscribe.md](Subscribe.md)
- Englische Übersicht: [Subscribe.md](Subscribe.md)

Beispiel-URL: `https://your-site.example/opencalendar/calendar.ics`

## Darstellung

### Kann ich eigene Theme-Styles verwenden?

Ja. Templates unter `user/themes/your-theme/templates/partials/opencalendar/` überschreiben und CSS mit `.opencalendar`-Klassen ergänzen.

### Kalender- vs. Listenansicht?

`display.default_view` setzen oder Shortcode `view="calendar"` / `view="list"` verwenden.

### Werden wiederkehrende Termine unterstützt?

Ja, wenn `advanced.import.expand_recurring` aktiviert ist (Standard).

## Technisch

### Warum SQLite?

Schnelle lokale Abfragen, Volltextsuche, kein externer Datenbankserver, einfache Backups. Siehe [SQLite.md](SQLite.md).

### Ist die API sicher?

Die API ist **standardmäßig deaktiviert**. Nur mit passenden Zugriffskontrollen aktivieren. Siehe [API.md](API.md) und [SECURITY.md](../../SECURITY.md).

### Kann ich composer.lock committen?

Ja. Grav-Plugins committen `composer.lock` üblicherweise für reproduzierbare Installationen.

## Lizenz

OpenCalendar steht unter der MIT-Lizenz. Sabre VObject hat eine eigene Lizenz — siehe Vendor-Dokumentation.

## Hilfe

- [GitHub Issues](https://github.com/TimUx/grav-plugin-opencalendar/issues)
- [Dokumentation](../)
- [Troubleshooting.md](Troubleshooting.md)
