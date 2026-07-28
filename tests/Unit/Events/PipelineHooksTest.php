<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Events;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\CleanupPolicy;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Enum\SyncInterval;
use Grav\Plugin\OpenCalendar\Events\EventDispatcherInterface;
use Grav\Plugin\OpenCalendar\Events\PipelineEvents;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Http\HttpResponse;
use Grav\Plugin\OpenCalendar\Models\Event;
use Grav\Plugin\OpenCalendar\Source\SourceFactory;
use Grav\Plugin\OpenCalendar\Storage\CalendarRepository;
use Grav\Plugin\OpenCalendar\Storage\Database;
use Grav\Plugin\OpenCalendar\Storage\EventRepository;
use Grav\Plugin\OpenCalendar\Storage\Migrator;
use Grav\Plugin\OpenCalendar\Sync\SyncJob;
use Grav\Plugin\OpenCalendar\Sync\SyncService;
use PHPUnit\Framework\TestCase;

final class PipelineHooksTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/opencalendar-hooks-' . uniqid('', true) . '.db';
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testListenersCanFilterEventsBeforePersist(): void
    {
        $fired = [];
        $dispatcher = new class ($fired) implements EventDispatcherInterface {
            /** @param array<int, array{0: string, 1: array<string, mixed>}> $fired */
            public function __construct(private array &$fired)
            {
            }

            public function dispatch(string $eventName, array $arguments = []): object
            {
                $this->fired[] = [$eventName, $arguments];

                $event = new class ($arguments) implements \ArrayAccess {
                    /** @param array<string, mixed> $data */
                    public function __construct(private array $data)
                    {
                    }

                    public function offsetExists(mixed $offset): bool
                    {
                        return isset($this->data[$offset]);
                    }

                    public function offsetGet(mixed $offset): mixed
                    {
                        return $this->data[$offset] ?? null;
                    }

                    public function offsetSet(mixed $offset, mixed $value): void
                    {
                        if (is_string($offset) || is_int($offset)) {
                            $this->data[$offset] = $value;
                        }
                    }

                    public function offsetUnset(mixed $offset): void
                    {
                        unset($this->data[$offset]);
                    }
                };

                if ($eventName === PipelineEvents::EVENTS_BEFORE_PERSIST) {
                    /** @var list<Event> $events */
                    $events = $event['events'] ?? [];
                    $event['events'] = array_values(array_filter(
                        $events,
                        static fn (Event $e): bool => !str_contains(strtolower($e->title), 'skip-me')
                    ));
                }

                return $event;
            }
        };

        $db = new Database($this->dbPath, true);
        (new Migrator($db, dirname(__DIR__, 3) . '/classes/Storage/Migrations'))->migrate();

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
                throw new \RuntimeException('no http');
            }
        };

        $factory = SourceFactory::createDefault(
            http: $http,
            importOptions: ['expand_recurring' => false],
            defaultTimezone: 'UTC',
            localBasePath: dirname(__DIR__, 3),
        );

        $job = new SyncJob(
            $factory,
            new CalendarRepository($db),
            new EventRepository($db),
            dispatcher: $dispatcher,
        );
        $service = new SyncService(
            job: $job,
            calendars: new CalendarRepository($db),
            events: new EventRepository($db),
            db: $db,
            interval: SyncInterval::Minutes15,
            cleanup: CleanupPolicy::Never,
            dispatcher: $dispatcher,
        );

        $config = new SourceConfig(
            key: 'hooks',
            name: 'Hooks',
            enabled: true,
            type: SourceType::Local,
            url: 'tests/Fixtures/sample.ics',
            refresh: '15',
            color: '#000',
            description: null,
        );

        $results = $service->syncAll([$config], true);
        self::assertCount(1, $results);

        $names = array_column($fired, 0);
        self::assertContains(PipelineEvents::EVENTS_PARSED, $names);
        self::assertContains(PipelineEvents::EVENTS_BEFORE_PERSIST, $names);
        self::assertContains(PipelineEvents::SYNC_SOURCE_COMPLETED, $names);
        self::assertContains(PipelineEvents::SYNC_COMPLETED, $names);
    }
}
