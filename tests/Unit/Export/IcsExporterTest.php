<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Export;

use Grav\Plugin\OpenCalendar\Export\IcsExporter;
use Grav\Plugin\OpenCalendar\Models\Event;
use PHPUnit\Framework\TestCase;

final class IcsExporterTest extends TestCase
{
    public function testExportBuildsValidVcalendar(): void
    {
        $start = new \DateTimeImmutable('2026-08-04T17:00:00+00:00');
        $end = new \DateTimeImmutable('2026-08-04T18:00:00+00:00');

        $event = new Event(
            id: 1,
            calendarId: 1,
            uid: 'meeting@example.com',
            recurrenceId: null,
            title: 'Team Meeting',
            description: 'Weekly sync',
            location: 'Room 1',
            organizer: 'mailto:lead@example.com',
            url: 'https://example.com/meet',
            status: 'CONFIRMED',
            categories: ['work', 'sync'],
            color: '#3788d8',
            attachments: [],
            startAt: $start,
            endAt: $end,
            allDay: false,
            timezone: 'UTC',
            isRecurring: false,
            rrule: null,
            contentHash: 'abc',
            calendarName: 'Team',
            calendarKey: 'team',
        );

        $ics = (new IcsExporter(calendarName: 'Export Test'))->export([$event]);

        self::assertStringContainsString('BEGIN:VCALENDAR', $ics);
        self::assertStringContainsString('BEGIN:VEVENT', $ics);
        self::assertStringContainsString('UID:meeting@example.com', $ics);
        self::assertStringContainsString('SUMMARY:Team Meeting', $ics);
        self::assertStringContainsString('LOCATION:Room 1', $ics);
        self::assertStringContainsString('X-WR-CALNAME:Export Test', $ics);
        self::assertStringContainsString('REFRESH-INTERVAL', $ics);
        self::assertStringContainsString('X-PUBLISHED-TTL', $ics);
        self::assertStringContainsString('END:VCALENDAR', $ics);
    }

    public function testAllDayEventsUseDateValue(): void
    {
        $event = new Event(
            id: 2,
            calendarId: 1,
            uid: 'holiday@example.com',
            recurrenceId: null,
            title: 'Holiday',
            description: null,
            location: null,
            organizer: null,
            url: null,
            status: null,
            categories: [],
            color: null,
            attachments: [],
            startAt: new \DateTimeImmutable('2026-12-25'),
            endAt: new \DateTimeImmutable('2026-12-26'),
            allDay: true,
            timezone: null,
            isRecurring: false,
            rrule: null,
            contentHash: null,
        );

        $ics = (new IcsExporter())->export([$event]);
        self::assertMatchesRegularExpression('/DTSTART;VALUE=DATE:20261225/', $ics);
        self::assertMatchesRegularExpression('/DTEND;VALUE=DATE:20261226/', $ics);
    }
}
