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
        $ics = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.ics');
        self::assertIsString($ics);

        $events = $this->parser->parse($ics, $this->config, 1);
        $standup = array_values(array_filter(
            $events,
            static fn ($e): bool => $e->uid === 'recurring-standup@example.com'
        ));

        // COUNT=5 with one EXDATE => 4 instances when expanded
        self::assertCount(4, $standup);

        foreach ($standup as $event) {
            self::assertNotSame('2026-07-03', $event->startAt->format('Y-m-d'));
        }
    }

    public function testRejectsInvalidPayload(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse('not-an-ics-file', $this->config, 1);
    }
}
