<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Storage;

use Grav\Plugin\OpenCalendar\Dto\EventQuery;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Models\Event;
use Grav\Plugin\OpenCalendar\Storage\CalendarRepository;
use Grav\Plugin\OpenCalendar\Storage\Database;
use Grav\Plugin\OpenCalendar\Storage\EventRepository;
use Grav\Plugin\OpenCalendar\Storage\Migrator;
use PHPUnit\Framework\TestCase;

final class EventRepositoryTest extends TestCase
{
    private string $dbPath;
    private EventRepository $events;
    private CalendarRepository $calendars;
    private int $calendarId;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/opencalendar-test-' . uniqid('', true) . '.db';
        $db = new Database($this->dbPath, true);
        $migrator = new Migrator($db, dirname(__DIR__, 3) . '/classes/Storage/Migrations');
        $migrator->migrate();

        $this->calendars = new CalendarRepository($db);
        $this->events = new EventRepository($db);

        $calendar = $this->calendars->upsertFromConfig(new SourceConfig(
            key: 'firebrigade',
            name: 'Fire Brigade',
            enabled: true,
            type: SourceType::Ics,
            url: 'https://example.com/cal.ics',
            refresh: '15',
            color: '#c00',
            description: null,
        ));
        $this->calendarId = (int) $calendar->id;
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testUpsertSearchFilterPaginationAndCleanup(): void
    {
        $now = new \DateTimeImmutable('2026-07-28T10:00:00+00:00');
        $batch = [];
        for ($i = 0; $i < 5; $i++) {
            $start = $now->modify('+' . $i . ' days');
            $batch[] = new Event(
                id: null,
                calendarId: $this->calendarId,
                uid: 'evt-' . $i,
                recurrenceId: null,
                title: 'Drill ' . $i,
                description: 'Training session ' . $i,
                location: 'Station ' . ($i % 2),
                organizer: 'Chief',
                url: null,
                status: 'CONFIRMED',
                categories: $i % 2 === 0 ? ['Training'] : ['Ops'],
                color: null,
                attachments: [],
                startAt: $start,
                endAt: $start->modify('+1 hour'),
                allDay: false,
                timezone: 'UTC',
                isRecurring: false,
                rrule: null,
                contentHash: 'hash-' . $i,
            );
        }

        $stats = $this->events->syncCalendarEvents($this->calendarId, 'Fire Brigade', $batch);
        self::assertSame(5, $stats['imported']);
        self::assertSame(0, $stats['updated']);

        $page = $this->events->search(new EventQuery(limit: 2, offset: 0, sort: 'asc'));
        self::assertSame(5, $page->total);
        self::assertCount(2, $page->items);
        self::assertSame(3, $page->totalPages());

        $filtered = $this->events->search(new EventQuery(
            categories: ['Training'],
            limit: 50,
        ));
        self::assertSame(3, $filtered->total);

        $search = $this->events->search(new EventQuery(search: 'Drill 2', limit: 10));
        self::assertSame(1, $search->total);
        self::assertSame('Drill 2', $search->items[0]->title);

        $byCal = $this->events->search(new EventQuery(calendarKeys: ['firebrigade'], limit: 50));
        self::assertSame(5, $byCal->total);

        // Update one event
        $batch[0] = new Event(
            id: null,
            calendarId: $this->calendarId,
            uid: 'evt-0',
            recurrenceId: null,
            title: 'Drill 0 Updated',
            description: 'Training session 0',
            location: 'Station 0',
            organizer: 'Chief',
            url: null,
            status: 'CONFIRMED',
            categories: ['Training'],
            color: null,
            attachments: [],
            startAt: $now,
            endAt: $now->modify('+1 hour'),
            allDay: false,
            timezone: 'UTC',
            isRecurring: false,
            rrule: null,
            contentHash: 'hash-0-updated',
        );

        // Remove last event from feed
        array_pop($batch);
        $stats2 = $this->events->syncCalendarEvents($this->calendarId, 'Fire Brigade', $batch);
        self::assertSame(0, $stats2['imported']);
        self::assertSame(1, $stats2['updated']);
        self::assertSame(1, $stats2['deleted']);

        $active = $this->events->search(new EventQuery(limit: 50));
        self::assertSame(4, $active->total);

        $purged = $this->events->purgeExpired(0);
        self::assertGreaterThanOrEqual(1, $purged);
    }
}
