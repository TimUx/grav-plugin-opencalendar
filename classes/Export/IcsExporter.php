<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Export;

use Grav\Plugin\OpenCalendar\Models\Event;
use Sabre\VObject\Component\VCalendar;

/**
 * Builds an RFC5545 VCALENDAR document from normalized Event models.
 */
final class IcsExporter
{
    public function __construct(
        private readonly string $productId = '-//OpenCalendar//Grav Plugin//EN',
        private readonly string $calendarName = 'OpenCalendar',
        private readonly int $refreshMinutes = 60,
        private readonly string $calendarDescription = 'Exported by OpenCalendar',
    ) {
    }

    /**
     * @param list<Event> $events
     */
    public function export(array $events, ?string $calendarName = null): string
    {
        $vcalendar = new VCalendar([
            'PRODID' => $this->productId,
            'VERSION' => '2.0',
            'CALSCALE' => 'GREGORIAN',
            'METHOD' => 'PUBLISH',
        ]);

        $name = $calendarName ?? $this->calendarName;
        $vcalendar->add('X-WR-CALNAME', $name);
        $vcalendar->add('X-WR-CALDESC', $this->calendarDescription);

        $minutes = max(5, $this->refreshMinutes);
        $duration = 'PT' . $minutes . 'M';
        // Hint for clients that poll subscribed calendars.
        $refresh = $vcalendar->add('REFRESH-INTERVAL', $duration);
        $refresh['VALUE'] = 'DURATION';
        $vcalendar->add('X-PUBLISHED-TTL', $duration);

        $seen = [];
        foreach ($events as $event) {
            if (!$event instanceof Event || $event->deletedAt !== null) {
                continue;
            }

            $dedupeKey = $event->uid . '|' . ($event->recurrenceId ?? '') . '|' . $event->startAt->format('c');
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $this->addEvent($vcalendar, $event);
        }

        return $vcalendar->serialize();
    }

    private function addEvent(VCalendar $vcalendar, Event $event): void
    {
        $props = [
            'UID' => $event->uid !== '' ? $event->uid : 'opencalendar-' . ($event->id ?? uniqid('', true)),
            'SUMMARY' => $event->title,
            'DTSTAMP' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ];

        if ($event->allDay) {
            $props['DTSTART'] = $event->startAt->format('Ymd');
        } else {
            $props['DTSTART'] = $event->startAt->setTimezone(new \DateTimeZone('UTC'));
        }

        if ($event->endAt !== null) {
            if ($event->allDay) {
                $props['DTEND'] = $event->endAt->format('Ymd');
            } else {
                $props['DTEND'] = $event->endAt->setTimezone(new \DateTimeZone('UTC'));
            }
        }

        $vevent = $vcalendar->add('VEVENT', $props);
        if (!$vevent instanceof \Sabre\VObject\Component\VEvent) {
            return;
        }

        if ($event->allDay) {
            $vevent->DTSTART['VALUE'] = 'DATE';
            if (isset($vevent->DTEND)) {
                $vevent->DTEND['VALUE'] = 'DATE';
            }
        }

        if ($event->description !== null && $event->description !== '') {
            $vevent->add('DESCRIPTION', $event->description);
        }
        if ($event->location !== null && $event->location !== '') {
            $vevent->add('LOCATION', $event->location);
        }
        if ($event->url !== null && $event->url !== '') {
            $vevent->add('URL', $event->url);
        }
        if ($event->status !== null && $event->status !== '') {
            $vevent->add('STATUS', strtoupper($event->status));
        }
        if ($event->organizer !== null && $event->organizer !== '') {
            $vevent->add('ORGANIZER', $event->organizer);
        }
        if ($event->categories !== []) {
            $vevent->add('CATEGORIES', implode(',', $event->categories));
        }
        if ($event->color !== null && $event->color !== '') {
            $vevent->add('COLOR', $event->color);
        }
        if ($event->recurrenceId !== null && $event->recurrenceId !== '') {
            try {
                $rid = new \DateTimeImmutable($event->recurrenceId);
                $vevent->add('RECURRENCE-ID', $rid->setTimezone(new \DateTimeZone('UTC')));
            } catch (\Exception) {
                $vevent->add('RECURRENCE-ID', $event->recurrenceId);
            }
        }
        if ($event->rrule !== null && $event->rrule !== '' && ($event->recurrenceId === null || $event->recurrenceId === '')) {
            $vevent->add('RRULE', ltrim($event->rrule, 'RRULE:'));
        }

        foreach ($event->attachments as $attachment) {
            $uri = $attachment['uri'];
            if ($uri === '') {
                continue;
            }
            $attach = $vevent->add('ATTACH', $uri);
            if (!empty($attachment['filename'])) {
                $attach['FILENAME'] = (string) $attachment['filename'];
            }
            if (!empty($attachment['mime'])) {
                $attach['FMTTYPE'] = (string) $attachment['mime'];
            }
        }

        if ($event->calendarName !== null && $event->calendarName !== '') {
            $vevent->add('X-OPENCALENDAR-SOURCE', $event->calendarName);
        }
        if ($event->calendarKey !== null && $event->calendarKey !== '') {
            $vevent->add('X-OPENCALENDAR-SOURCE-KEY', $event->calendarKey);
        }
    }
}
