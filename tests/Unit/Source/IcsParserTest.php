<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Source;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Source\IcsParser;
use PHPUnit\Framework\TestCase;

final class IcsParserTest extends TestCase
{
    private IcsParser $parser;
    private SourceConfig $config;

    protected function setUp(): void
    {
        $this->parser = new IcsParser('UTC', true, 365, false);
        $this->config = new SourceConfig(
            key: 'test',
            name: 'Test Calendar',
            enabled: true,
            type: SourceType::Ics,
            url: 'file://test.ics',
            refresh: '15',
            color: '#112233',
            description: null,
        );
    }

    public function testParsesBasicEventFields(): void
    {
        $ics = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.ics');
        self::assertIsString($ics);

        $events = $this->parser->parse($ics, $this->config, 1);
        self::assertNotEmpty($events);

        $meeting = null;
        foreach ($events as $event) {
            if ($event->uid === 'simple-meeting@example.com') {
                $meeting = $event;
                break;
            }
        }

        self::assertNotNull($meeting);
        self::assertSame('Team Meeting', $meeting->title);
        self::assertSame('Room 1', $meeting->location);
        self::assertSame('Weekly sync meeting', $meeting->description);
        self::assertStringContainsString('alice@example.com', (string) $meeting->organizer);
        self::assertSame(['Work', 'Meetings'], $meeting->categories);
        self::assertFalse($meeting->allDay);
        self::assertSame('https://example.com/events/team-meeting', $meeting->url);
        self::assertSame('#3788d8', $meeting->color);
    }

    public function testParsesAllDayEvent(): void
    {
        $ics = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.ics');
        self::assertIsString($ics);

        $events = $this->parser->parse($ics, $this->config, 1);
        $holiday = null;
        foreach ($events as $event) {
            if ($event->uid === 'all-day@example.com') {
                $holiday = $event;
                break;
            }
        }

        self::assertNotNull($holiday);
        self::assertTrue($holiday->allDay);
        self::assertSame('Company Holiday', $holiday->title);
    }

    public function testExpandsRruleAndHonoursExdate(): void
    {
        // Keep instances inside IcsParser's expand window (now-30d … +horizon).
        $start = new \DateTimeImmutable('tomorrow', new \DateTimeZone('UTC'));
        $start = $start->setTime(9, 0);
        $exdate = $start->modify('+2 days');
        $fmt = static fn (\DateTimeImmutable $dt): string => $dt->format('Ymd\THis\Z');

        $ics = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//OpenCalendar//TEST//EN
BEGIN:VEVENT
UID:recurring-standup@example.com
DTSTAMP:20260701T100000Z
DTSTART:{$fmt($start)}
DTEND:{$fmt($start->modify('+15 minutes'))}
SUMMARY:Daily Standup
RRULE:FREQ=DAILY;COUNT=5
EXDATE:{$fmt($exdate)}
CATEGORIES:Work
END:VEVENT
END:VCALENDAR
ICS;

        $events = $this->parser->parse($ics, $this->config, 1);
        $standup = array_values(array_filter(
            $events,
            static fn ($e): bool => $e->uid === 'recurring-standup@example.com'
        ));

        // COUNT=5 with one EXDATE => 4 instances when expanded
        self::assertCount(4, $standup);

        $excluded = $exdate->format('Y-m-d');
        foreach ($standup as $event) {
            self::assertNotSame($excluded, $event->startAt->format('Y-m-d'));
        }
    }

    public function testParsesUtcInstantIntoDefaultTimezone(): void
    {
        $parser = new IcsParser('Europe/Berlin', true, 365, false);
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//OpenCalendar//TEST//EN
BEGIN:VEVENT
UID:utc-drill@example.com
DTSTAMP:20260701T100000Z
DTSTART:20260804T170000Z
DTEND:20260804T190000Z
SUMMARY:Uebungsdienst
END:VEVENT
END:VCALENDAR
ICS;

        $events = $parser->parse($ics, $this->config, 1);
        self::assertCount(1, $events);
        self::assertSame('2026-08-04 19:00', $events[0]->startAt->format('Y-m-d H:i'));
        self::assertSame('Europe/Berlin', $events[0]->startAt->getTimezone()->getName());
    }

    public function testParsesFloatingTimeInDefaultTimezone(): void
    {
        $parser = new IcsParser('Europe/Berlin', true, 365, false);
        $ics = <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//OpenCalendar//TEST//EN
BEGIN:VEVENT
UID:floating-drill@example.com
DTSTAMP:20260701T100000Z
DTSTART:20260804T190000
DTEND:20260804T210000
SUMMARY:Uebungsdienst
END:VEVENT
END:VCALENDAR
ICS;

        $events = $parser->parse($ics, $this->config, 1);
        self::assertCount(1, $events);
        self::assertSame('2026-08-04 19:00', $events[0]->startAt->format('Y-m-d H:i'));
    }

    public function testRejectsInvalidPayload(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse('not-an-ics-file', $this->config, 1);
    }
}
