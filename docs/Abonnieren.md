# Kalender auf dem Smartphone abonnieren

OpenCalendar stellt die importierten Termine als **Live-ICS-Feed** bereit. Smartphones und Tablets können diese Adresse als **Netzwerk-/Abonnement-Kalender** einbinden. Die Apps laden den Kalender regelmäßig neu — ohne manuelles erneutes Importieren.

> Englische Fassung: [Subscribe.md](Subscribe.md)

## 1. Feed-Adresse ermitteln

1. Im Grav-Admin: **Plugins → OpenCalendar → Advanced → ICS export / subscription**
2. Sicherstellen, dass **Kalender-Abonnement-Feed aktivieren** eingeschaltet ist
3. Die Feed-URL lautet standardmäßig:

```text
https://IHRE-DOMAIN.de/opencalendar/calendar.ics
```

Optional nur eine Quelle:

```text
https://IHRE-DOMAIN.de/opencalendar/calendar.ics?source=feuerwehr
```

(`source` = Name oder Key der Quelle aus der OpenCalendar-Konfiguration.)

**Tipp:** Auf der Website den Shortcode mit `show_subscribe="true"` nutzen — dann erscheinen ein Abonnieren-Link und die kopierbare URL:

```text
[opencalendar view="list" show_subscribe="true" /]
```

Die Adresse muss von außen erreichbar sein (**HTTPS** empfohlen) und die Termine öffentlich lesbar machen.

---

## 2. iPhone / iPad (Apple Kalender)

### Variante A — über die Einstellungen (zuverlässig)

1. ICS-URL kopieren (z. B. von der Website oder aus dem Browser).
2. **Einstellungen** öffnen → **Kalender** → **Accounts**  
   (ältere iOS: **Einstellungen → Mail → Accounts** bzw. **Kalender → Accounts**).
3. **Account hinzufügen** → **Andere** → **Kalenderabo hinzufügen**.
4. Unter **Server** die HTTPS-URL einfügen (beginnend mit `https://…`).
5. **Weiter** → Name ggf. anpassen → **Sichern**.

Der Kalender erscheint in der App **Kalender** unter den Abonnements. Aktualisierung erfolgt automatisch im Hintergrund.

### Variante B — Ein-Tipp mit `webcal://`

Wenn die Website einen Link „Kalender abonnieren“ mit `webcal://…` anbietet:

1. Link tippen.
2. Bestätigen, dass der Kalender hinzugefügt werden soll.

### Aktualisierung / Entfernen (iOS)

- **Aktualisieren:** Apple holt Abonnements periodisch; erzwungenes Neu-Laden ist eingeschränkt. Bei Bedarf Abonnement entfernen und neu hinzufügen.
- **Entfernen:** **Einstellungen → Kalender → Accounts** → Abonnement wählen → **Account löschen**.

---

## 3. Android mit Google Kalender

Google Kalender erlaubt das Abonnieren einer ICS-URL am zuverlässigsten **über die Web-Oberfläche** (einmal am PC oder im Browser am Handy):

1. Im Browser [calendar.google.com](https://calendar.google.com) öffnen und anmelden (dasselbe Google-Konto wie auf dem Handy).
2. Links neben **Andere Kalender** auf **+** → **Per URL**.
3. Die HTTPS-Adresse einfügen, z. B.  
   `https://IHRE-DOMAIN.de/opencalendar/calendar.ics`
4. **Kalender hinzufügen**.

Danach erscheint der Kalender in der **Google-Kalender-App** auf dem Android-Gerät (Synchronisation kann ein paar Minuten dauern). Unter **Kalender-Einstellungen** den neuen Eintrag sichtbar schalten, falls er ausgeblendet ist.

### Samsung-Kalender / andere Hersteller-Apps

Viele Hersteller-Apps nutzen Google-Konten. Am einfachsten: wie oben bei Google abonnieren — die Termine erscheinen dann auch in der Samsung-Kalender-App, sofern das Google-Konto dort eingebunden ist.

Manche Apps bieten „Kalender per URL / ICS“ direkt an — dann dieselbe HTTPS-URL verwenden.

### Aktualisierung / Entfernen (Google)

- Google aktualisiert URL-Kalender selbstständig (Intervall liegt bei Google, oft im Stundenbereich).
- **Entfernen:** [calendar.google.com](https://calendar.google.com) → Einstellungen des Kalenders → **Entfernen**.

---

## 4. Weitere Clients (Kurz)

| Client | Vorgehen |
|--------|----------|
| **Outlook** (Windows / Microsoft 365) | Kalender hinzufügen → **Aus dem Internet** → HTTPS-ICS-URL |
| **Outlook** (iOS/Android-App) | Oft über das Microsoft-Konto / Outlook.com im Web abonnieren, dann syncen |
| **Thunderbird** | Neuer Kalender → **Im Netzwerk** → Format iCalendar → URL |
| **macOS Kalender** | Datei → Neues Kalenderabo… → URL einfügen |

---

## 5. Typische Probleme

| Problem | Lösung |
|---------|--------|
| „Ungültige URL“ / kein Kalender | HTTPS prüfen, Feed im Browser öffnen — muss mit `BEGIN:VCALENDAR` starten |
| Keine Termine sichtbar | OpenCalendar-Sync im Admin ausführen; Zeitraum `default_from` / `default_to` prüfen |
| Termine veraltet | Scheduler aktiv? Quelle aktiv? Client-Cache: Abonnement neu anlegen |
| Nur eine Feuerwehr-Quelle gewünscht | `?source=…` an die URL hängen |
| Privatsphäre | Feed ist öffentlich lesbar — keine sensiblen Daten ohne Absicherung (Auth/VPN) |

---

## 6. So bleibt der Kalender aktuell

```text
Externe Quellen (ICS/CalDAV/…)
        ↓  OpenCalendar-Sync (Scheduler / Webhook)
   SQLite in Grav
        ↓  Abruf durchs Smartphone
  /opencalendar/calendar.ics
```

1. OpenCalendar synchronisiert die importierten Quellen in die Datenbank.
2. Das Smartphone fragt regelmäßig den ICS-Feed ab.
3. Der Feed enthält ein Refresh-Intervall (Admin: **Empfohlenes Refresh-Intervall**); Clients entscheiden selbst, wie oft sie laden.

Es ist **kein** Push an Apple/Google nötig — Abonnement-Kalender arbeiten per Abruf (Pull).

---

## Verwandte Dokumentation

- [Subscribe.md](Subscribe.md) — English guide & Twig helpers
- [ICS.md](ICS.md) — Import und Export technisch
- [Synchronization.md](Synchronization.md) — Sync & Scheduler
- [Shortcodes.md](Shortcodes.md) — `show_subscribe`
