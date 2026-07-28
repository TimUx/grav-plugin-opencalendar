<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Events;

/**
 * Grav event names fired during the OpenCalendar sync pipeline.
 *
 * Other plugins can subscribe via getSubscribedEvents(), e.g.:
 *
 *   'opencalendar.events.parsed' => ['onOpenCalendarEventsParsed', 0],
 *
 * Listeners receive a RocketTheme\Toolbox\Event\Event (ArrayAccess) with
 * the documented payload keys. Mutable keys can be replaced to alter behaviour.
 */
final class PipelineEvents
{
    /** Fired after a source payload is parsed into Event models (before persist). */
    public const EVENTS_PARSED = 'opencalendar.events.parsed';

    /** Fired immediately before events are written to SQLite. */
    public const EVENTS_BEFORE_PERSIST = 'opencalendar.events.before_persist';

    /** Fired after a single source sync finishes (success, skip, or error). */
    public const SYNC_SOURCE_COMPLETED = 'opencalendar.sync.source.completed';

    /** Fired after syncAll() / rebuild completes. */
    public const SYNC_COMPLETED = 'opencalendar.sync.completed';

    private function __construct()
    {
    }
}
