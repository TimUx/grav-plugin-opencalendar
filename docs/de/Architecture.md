# Architektur

> English: [Architecture](../en/Architecture.md)

OpenCalendar folgt einer geschichteten Architektur, die für die Integration in Grav CMS, Testbarkeit und schrittweise Unterstützung weiterer Quelltypen ausgelegt ist.

## Überblick auf hoher Ebene

```
┌─────────────────────────────────────────────────────────────┐
│                     Grav CMS (Pages, Twig)                   │
├─────────────────────────────────────────────────────────────┤
│  opencalendar.php  │  Twig extensions  │  Shortcodes        │
├─────────────────────────────────────────────────────────────┤
│              Controllers / API (HTTP boundary)               │
├─────────────────────────────────────────────────────────────┤
│     Services (EventQuery, Search, Filter, SyncOrchestrator)  │
├─────────────────────────────────────────────────────────────┤
│   Source adapters (ICS, CalDAV, JSON, Local)  │  Sync jobs   │
├─────────────────────────────────────────────────────────────┤
│        Storage (SQLite repository, migrations)               │
├─────────────────────────────────────────────────────────────┤
│   Models / DTOs / Enums (domain types, no I/O)               │
└─────────────────────────────────────────────────────────────┘
```

## Verzeichnisstruktur

| Pfad | Aufgabe |
|------|---------|
| `opencalendar.php` | Grav-Plugin-Einstieg, Registrierung von Event-Hooks |
| `classes/Source/` | Feed-Adapter, die eine gemeinsame Quellschnittstelle implementieren |
| `classes/Sync/` | Sync-Pipeline, Planungs-Hooks, Bereinigung |
| `classes/Storage/` | SQLite-Zugriff, Schema-Migrationen, Repositories |
| `classes/Services/` | Geschäftslogik, die Speicher und Quellen orchestriert |
| `classes/Models/` | Persistente Domänenentitäten (Event, Source usw.) |
| `classes/Dto/` | Unveränderliche Data-Transfer-Objects für API/Twig |
| `classes/Enum/` | Typisierte Aufzählungen (SourceType, CleanupPolicy, …) |
| `classes/Controllers/` | Admin-Aktionen und Frontend-Routen-Handler |
| `classes/Api/` | JSON-API-Serialisierer und Routendefinitionen |
| `templates/` | Twig-Vorlagen und Partials |
| `assets/` | CSS, JS, eingebundene Frontend-Bibliotheken |
| `data/` | Standard-Speicherort für SQLite (zur Laufzeit gitignored) |

## Namespace

Alle PHP-Klassen liegen unter:

```
Grav\Plugin\OpenCalendar\
```

PSR-4-Autoloading mappt `classes/` über Composer auf diesen Namespace.

## Datenfluss: Synchronisation

1. Scheduler oder manueller Trigger startet einen Sync-Job.
2. `SyncOrchestrator` lädt aktivierte Quellen aus der Konfiguration.
3. Jeder **Quell-Adapter** holt die Rohdaten (HTTP, CalDAV, Dateisystem).
4. Der Parser normalisiert Ereignisse in `Event`-Modelle (mit Sabre VObject für ICS).
5. Die Speicherschicht führt Upserts aus, verfolgt Löschungen und wendet Deduplizierungsregeln an.
6. Render-Cache-Schlüssel für betroffene Quellen werden invalidiert.

## Datenfluss: Frontend-Rendering

1. Seitenanfrage trifft auf Twig-Vorlage oder Shortcode-Handler.
2. Die Service-Schicht fragt SQLite mit Filtern ab (Datumsbereich, Quelle, Kategorie).
3. Ergebnisse werden für Twig auf DTOs abgebildet (keine rohen Datenbankzeilen in Vorlagen).
4. Optional liefert der Render-Cache vorgefertigte Sammlungen zurück, wenn die TTL es erlaubt.

## Wichtige Designentscheidungen

### SQLite als kanonischer Speicher

Remote-Feeds werden in einer lokalen SQLite-Datenbank normalisiert — für schnelle Abfragen, Volltextsuche und Offline-Resilienz. Siehe [SQLite.md](SQLite.md).

### Quell-Adapter-Muster

Jeder Quelltyp implementiert eine gemeinsame Schnittstelle:

- `fetch()` — Rohdaten abrufen
- `parse()` — normalisierte Ereignisse erzeugen
- `getLastModified()` — bedingte Anfragen unterstützen

Neue Quelltypen fügen einen Adapter hinzu, ohne Speicher- oder Anzeige-Code zu ändern.

### Konfigurationsgesteuertes Verhalten

Das Laufzeitverhalten wird über `opencalendar.yaml` gesteuert, ohne hart codierte Feed-URLs. Admin-Blueprint und YAML bleiben synchron.

### Grav-Integrationspunkte

| Grav-Hook | Verwendung |
|-----------|------------|
| `onPluginsInitialized` | Services, Routen registrieren |
| `onTwigExtensions` | Filter/Funktionen hinzufügen |
| `onPageContentProcessed` | Shortcode-Verarbeitung |
| `onPagesInitialized` | API-, Webhook- und ICS-Export-Routen |
| `onSchedulerInitialized` | Sync-/Cleanup-Jobs registrieren |
| `onCacheClear` | Optionaler Sync beim Leeren des Grav-Caches |
| `onAdminDashboard` | Sync-Status-Widget auf dem Admin-Dashboard |
| `onAdminTwigTemplatePaths` | Admin-Vorlagen (Sync-Feld, Dashboard) |
| `onAdminPagesInitialized` | Admin-JSON-Aktionen (`sync`, `rebuild`, `clear-cache`, `status`, `upload`) |

## Abhängigkeiten

| Paket | Rolle |
|-------|-------|
| `sabre/vobject` | ICS/iCalendar-Parsing und -Export |

Grav Core stellt HTTP-Client-Hilfsmittel, Caching, Logging und Twig bereit.

## Erweiterungspunkte

- **Eigene Quelltypen** — Adapter implementieren und in der Quell-Factory registrieren
- **Twig-Filter** — dokumentiert in [Twig.md](Twig.md)
- **Event-Pipeline-Hooks** — andere Plugins können sich anmelden:

| Event | Wann | Veränderbare Payload |
|-------|------|----------------------|
| `opencalendar.events.parsed` | Nach dem Parsen, vor dem Persistieren | `events`, `source`, `calendar`, `fetch` |
| `opencalendar.events.before_persist` | Unmittelbar vor SQLite-Upsert | `events`, `source`, `calendar` |
| `opencalendar.sync.source.completed` | Nach Abschluss jeder Quelle | `result`, `source`, `calendar` |
| `opencalendar.sync.completed` | Nach syncAll / syncOne | `results`, `sources`, `force` |

Beispiel-Listener (in einem anderen Grav-Plugin):

```php
public static function getSubscribedEvents(): array
{
    return [
        'opencalendar.events.before_persist' => ['filterEvents', 0],
    ];
}

public function filterEvents(\RocketTheme\Toolbox\Event\Event $event): void
{
    $events = $event['events'] ?? [];
    $event['events'] = array_values(array_filter(
        $events,
        static fn ($e) => !str_contains(strtolower((string) $e->title), 'internal')
    ));
}
```

## Teststrategie

- **Unit-Tests** — Modelle, Parser, Query-Builder (ohne Grav-Bootstrap)
- **Integrationstests** — SQLite-Repository gegen Fixture-ICS-Dateien
- **Manuelles QA** — Admin-Formulare, Frontend-Ansichten in einer Grav-Instanz

Vor jedem Pull Request `composer check` ausführen.

## Verwandte Dokumentation

- [Synchronization.md](Synchronization.md)
- [Sources.md](Sources.md)
- [Development.md](Development.md)
