<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Sync;

use Grav\Plugin\OpenCalendar\Dto\FetchResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\CleanupPolicy;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Enum\SyncInterval;
use Grav\Plugin\OpenCalendar\Enum\SyncStatus;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Http\HttpResponse;
use Grav\Plugin\OpenCalendar\Source\IcsParser;
use Grav\Plugin\OpenCalendar\Source\IcsSource;
use Grav\Plugin\OpenCalendar\Source\SourceFactory;
use Grav\Plugin\OpenCalendar\Storage\CalendarRepository;
use Grav\Plugin\OpenCalendar\Storage\Database;
use Grav\Plugin\OpenCalendar\Storage\EventRepository;
use Grav\Plugin\OpenCalendar\Storage\Migrator;
use Grav\Plugin\OpenCalendar\Sync\SyncJob;
use Grav\Plugin\OpenCalendar\Sync\SyncService;
use PHPUnit\Framework\TestCase;

final class SyncServiceTest extends TestCase
{
    private string $dbPath;
    private string $icsPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/opencalendar-sync-' . uniqid('', true) . '.db';
        $this->icsPath = sys_get_temp_dir() . '/opencalendar-' . uniqid('', true) . '.ics';
        $fixture = dirname(__DIR__, 2) . '/Fixtures/sample.ics';
        self::assertFileExists($fixture);
        copy($fixture, $this->icsPath);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm', $this->icsPath] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testSyncImportsEventsAndSkipsUnchangedHash(): void
    {
        $db = new Database($this->dbPath, true);
        (new Migrator($db, dirname(__DIR__, 3) . '/classes/Storage/Migrations'))->migrate();

        $calendars = new CalendarRepository($db);
        $events = new EventRepository($db);
        $http = new class implements HttpClientInterface {
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
                throw new \RuntimeException('HTTP should not be called for local file sync');
            }
        };

        $factory = new SourceFactory([
            new IcsSource($http, new IcsParser('UTC', true, 365)),
        ]);

        $job = new SyncJob($factory, $calendars, $events);
        $service = new SyncService(
            $job,
            $calendars,
            $events,
            $db,
            SyncInterval::Minutes15,
            CleanupPolicy::Never,
        );

        $config = new SourceConfig(
            key: 'local-ics',
            name: 'Local ICS',
            enabled: true,
            type: SourceType::Ics,
            url: $this->icsPath,
            refresh: '15',
            color: '#0a0',
            description: null,
        );

        $first = $service->syncAll([$config], true);
        self::assertCount(1, $first);
        self::assertSame(SyncStatus::Success, $first[0]->status);
        self::assertGreaterThan(0, $first[0]->imported);

        $second = $service->syncAll([$config], true);
        self::assertSame(SyncStatus::Skipped, $second[0]->status);
        self::assertTrue($second[0]->skippedParse);

        self::assertGreaterThan(0, $events->countActive());
    }

    public function testFetchResultNotModifiedHelper(): void
    {
        $result = FetchResult::notModified(304, '"abc"', 'Mon, 01 Jan 2026 00:00:00 GMT', 'hash');
        self::assertTrue($result->notModified);
        self::assertSame('', $result->body);
    }
}
