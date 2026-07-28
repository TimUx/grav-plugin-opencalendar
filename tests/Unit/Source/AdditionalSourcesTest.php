<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Source;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Http\HttpResponse;
use Grav\Plugin\OpenCalendar\Source\CalDavSource;
use Grav\Plugin\OpenCalendar\Source\IcsParser;
use Grav\Plugin\OpenCalendar\Source\JsonParser;
use Grav\Plugin\OpenCalendar\Source\JsonSource;
use Grav\Plugin\OpenCalendar\Source\LocalSource;
use PHPUnit\Framework\TestCase;

final class AdditionalSourcesTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/oc-sources-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
    }

    public function testJsonParserAcceptsEventsArray(): void
    {
        $parser = new JsonParser('Europe/Berlin');
        $config = $this->config('json-api', SourceType::Json, 'https://example.com/events.json');
        $payload = json_encode([
            'events' => [
                [
                    'uid' => 'json-1',
                    'title' => 'JSON Drill',
                    'start' => '2026-08-04T19:00:00+02:00',
                    'end' => '2026-08-04T21:00:00+02:00',
                    'location' => 'Station',
                    'categories' => ['Training'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $events = $parser->parse($payload, $config, 1);
        self::assertCount(1, $events);
        self::assertSame('JSON Drill', $events[0]->title);
        self::assertSame('2026-08-04 19:00', $events[0]->startAt->format('Y-m-d H:i'));
        self::assertSame(['Training'], $events[0]->categories);
    }

    public function testLocalSourceReadsJsonFile(): void
    {
        $file = $this->tmpDir . '/events.json';
        file_put_contents($file, json_encode([
            [
                'id' => 'local-1',
                'title' => 'Local JSON Event',
                'start' => '2026-09-01T10:00:00+02:00',
                'all_day' => false,
            ],
        ], JSON_THROW_ON_ERROR));

        $http = $this->unusedHttp();
        $source = new LocalSource(
            $http,
            $this->tmpDir,
            new IcsParser('Europe/Berlin', false, 30),
            new JsonParser('Europe/Berlin'),
        );
        $config = $this->config('local-json', SourceType::Local, 'events.json');
        $fetch = $source->fetch($config);
        $events = $source->parse($fetch->body, $config, 3);

        self::assertFalse($fetch->notModified);
        self::assertCount(1, $events);
        self::assertSame('Local JSON Event', $events[0]->title);
    }

    public function testLocalSourceRejectsPathEscape(): void
    {
        $http = $this->unusedHttp();
        $source = new LocalSource($http, $this->tmpDir, new IcsParser('UTC'), new JsonParser('UTC'));
        $config = $this->config('escape', SourceType::Local, '../outside.ics');

        $this->expectException(\RuntimeException::class);
        $source->fetch($config);
    }

    public function testCalDavSourceParsesMultistatusCalendarData(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
  <D:response>
    <D:href>/calendars/user/personal/event-1.ics</D:href>
    <D:propstat>
      <D:prop>
        <C:calendar-data>BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:caldav-1@example.com
DTSTART:20260804T170000Z
DTEND:20260804T180000Z
SUMMARY:CalDAV Meeting
END:VEVENT
END:VCALENDAR</C:calendar-data>
      </D:prop>
      <D:status>HTTP/1.1 200 OK</D:status>
    </D:propstat>
  </D:response>
</D:multistatus>
XML;

        $http = new class($xml) implements HttpClientInterface {
            public function __construct(private readonly string $xml)
            {
            }

            public function get(
                string $url,
                array $headers = [],
                array $auth = [],
                int $timeout = 30,
                bool $verifySsl = true,
                int $maxRedirects = 3,
                string $userAgent = 'OpenCalendar/1.0',
            ): HttpResponse {
                return $this->request('GET', $url, null, $headers, $auth, $timeout, $verifySsl, $maxRedirects, $userAgent);
            }

            public function request(
                string $method,
                string $url,
                ?string $body = null,
                array $headers = [],
                array $auth = [],
                int $timeout = 30,
                bool $verifySsl = true,
                int $maxRedirects = 3,
                string $userAgent = 'OpenCalendar/1.0',
            ): HttpResponse {
                if ($method !== 'REPORT' || $body === null || !str_contains($body, 'calendar-query')) {
                    throw new \RuntimeException('Unexpected CalDAV request');
                }

                return new HttpResponse(207, $this->xml, ['ETag' => '"dav-1"']);
            }
        };

        $source = new CalDavSource($http, new IcsParser('Europe/Berlin', false, 60), [], 60);
        $config = $this->config(
            'caldav',
            SourceType::CalDav,
            'https://cloud.example.com/remote.php/dav/calendars/user/personal/'
        );
        $fetch = $source->fetch($config);
        $events = $source->parse($fetch->body, $config, 2);

        self::assertSame(207, $fetch->httpStatus);
        self::assertSame('"dav-1"', $fetch->etag);
        self::assertCount(1, $events);
        self::assertSame('CalDAV Meeting', $events[0]->title);
        self::assertSame('2026-08-04 19:00', $events[0]->startAt->format('Y-m-d H:i'));
    }

    public function testJsonSourceFetchUsesHttp(): void
    {
        $payload = json_encode([
            'data' => [
                [
                    'uid' => 'http-json-1',
                    'summary' => 'Remote JSON',
                    'start' => '2026-10-01T08:00:00+02:00',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $http = new class($payload) implements HttpClientInterface {
            public function __construct(private readonly string $payload)
            {
            }

            public function get(
                string $url,
                array $headers = [],
                array $auth = [],
                int $timeout = 30,
                bool $verifySsl = true,
                int $maxRedirects = 3,
                string $userAgent = 'OpenCalendar/1.0',
            ): HttpResponse {
                return $this->request('GET', $url, null, $headers, $auth, $timeout, $verifySsl, $maxRedirects, $userAgent);
            }

            public function request(
                string $method,
                string $url,
                ?string $body = null,
                array $headers = [],
                array $auth = [],
                int $timeout = 30,
                bool $verifySsl = true,
                int $maxRedirects = 3,
                string $userAgent = 'OpenCalendar/1.0',
            ): HttpResponse {
                return new HttpResponse(200, $this->payload, ['ETag' => '"json-1"']);
            }
        };

        $source = new JsonSource($http, new JsonParser('Europe/Berlin'));
        $config = $this->config('json', SourceType::Json, 'https://example.com/api/events');
        $fetch = $source->fetch($config);
        $events = $source->parse($fetch->body, $config, 1);

        self::assertCount(1, $events);
        self::assertSame('Remote JSON', $events[0]->title);
    }

    private function config(string $key, SourceType $type, string $url): SourceConfig
    {
        return new SourceConfig(
            key: $key,
            name: 'Test ' . $key,
            enabled: true,
            type: $type,
            url: $url,
            refresh: '15',
            color: '#112233',
            description: null,
        );
    }

    private function unusedHttp(): HttpClientInterface
    {
        return new class implements HttpClientInterface {
            public function get(
                string $url,
                array $headers = [],
                array $auth = [],
                int $timeout = 30,
                bool $verifySsl = true,
                int $maxRedirects = 3,
                string $userAgent = 'OpenCalendar/1.0',
            ): HttpResponse {
                return $this->request('GET', $url, null, $headers, $auth, $timeout, $verifySsl, $maxRedirects, $userAgent);
            }

            public function request(
                string $method,
                string $url,
                ?string $body = null,
                array $headers = [],
                array $auth = [],
                int $timeout = 30,
                bool $verifySsl = true,
                int $maxRedirects = 3,
                string $userAgent = 'OpenCalendar/1.0',
            ): HttpResponse {
                throw new \RuntimeException('HTTP unused');
            }
        };
    }
}
