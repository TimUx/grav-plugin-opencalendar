<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Services;

use Grav\Plugin\OpenCalendar\Api\EventsApi;
use Grav\Plugin\OpenCalendar\Api\RateLimiter;
use Grav\Plugin\OpenCalendar\Controllers\ApiController;
use Grav\Plugin\OpenCalendar\Controllers\ShortcodeProcessor;
use Grav\Plugin\OpenCalendar\Dto\EventQuery;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Http\HttpResponse;
use Grav\Plugin\OpenCalendar\Services\Container;
use Grav\Plugin\OpenCalendar\Twig\TwigExtension;
use PHPUnit\Framework\TestCase;

final class IntegrationApiTwigTest extends TestCase
{
    private string $dbPath;
    private string $pluginPath;
    private string $icsPath;

    protected function setUp(): void
    {
        $this->pluginPath = dirname(__DIR__, 3);
        $this->dbPath = sys_get_temp_dir() . '/opencalendar-api-' . uniqid('', true) . '.db';
        $this->icsPath = sys_get_temp_dir() . '/opencalendar-api-' . uniqid('', true) . '.ics';
        copy($this->pluginPath . '/tests/Fixtures/sample.ics', $this->icsPath);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm', $this->icsPath] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testApiPaginationSearchAndTwigShortcode(): void
    {
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
                throw new \RuntimeException('unused');
            }
        };

        $container = new Container(
            config: [
                'timezone' => 'UTC',
                'sync_interval' => 15,
                'cleanup' => 'never',
                'cache' => ['enabled' => true, 'ttl' => 60],
                'storage' => ['path' => $this->dbPath, 'wal_mode' => true],
                'display' => [
                    'default_view' => 'list',
                    'list' => ['limit' => 50, 'sort' => 'asc', 'show_past' => true],
                    'calendar' => ['initial_view' => 'dayGridMonth', 'first_day' => 1],
                ],
                'filters' => ['enabled' => true, 'show_source_filter' => true, 'show_category_filter' => true],
                'search' => ['enabled' => true],
                'api' => [
                    'enabled' => true,
                    'pagination' => ['default_limit' => 2, 'max_limit' => 100],
                    'rate_limit' => ['enabled' => true, 'max_requests' => 100, 'per_minutes' => 1],
                ],
                'advanced' => [
                    'http' => ['timeout' => 10, 'verify_ssl' => true, 'max_redirects' => 2, 'user_agent' => 'test'],
                    'import' => ['expand_recurring' => true, 'recurring_horizon_days' => 365],
                ],
                'sources' => [
                    [
                        'name' => 'Fixture',
                        'enabled' => true,
                        'type' => 'ics',
                        'url' => $this->icsPath,
                        'refresh' => '15',
                        'color' => '#123456',
                    ],
                ],
            ],
            pluginPath: $this->pluginPath,
            httpClient: $http,
        );

        $container->boot();
        $sources = $container->sourceConfigs();
        $results = $container->calendarService()->synchronize($sources, true);
        self::assertSame('success', $results[0]->status->value);

        $api = new EventsApi($container->calendarService(), $container->calendarService()->config()['api']);
        $page1 = $api->listEvents(['limit' => 2, 'offset' => 0]);
        self::assertSame(2, $page1['meta']['limit']);
        self::assertGreaterThan(2, $page1['meta']['total']);
        self::assertCount(2, $page1['data']);

        $search = $api->listEvents(['q' => 'Team Meeting']);
        self::assertGreaterThanOrEqual(1, $search['meta']['total']);

        $controller = new ApiController(
            $container,
            $container->calendarService()->config()['api'],
            new RateLimiter(true, 100, 1)
        );
        $response = $controller->handle('/events', ['limit' => 1], '127.0.0.1');
        self::assertSame(200, $response['status']);
        self::assertArrayHasKey('data', $response['body']);

        $twig = new TwigExtension($container->calendarService(), $container->calendarService()->config());
        $html = $twig->render(['view' => 'list', 'limit' => 10]);
        self::assertStringContainsString('opencalendar', $html);
        self::assertStringContainsString('oc-list', $html);

        $hiddenUi = $twig->render([
            'view' => 'list',
            'limit' => 10,
            'show_filters' => 'false',
            'show_search' => 'false',
            'from' => 'now',
            'to' => '+30 days',
            'show_past' => 'false',
        ]);
        self::assertStringNotContainsString('data-oc-filters', $hiddenUi);
        self::assertStringNotContainsString('data-oc-search', $hiddenUi);
        self::assertStringNotContainsString('data-oc-filter-source', $hiddenUi);

        $processor = new ShortcodeProcessor($twig);
        $processed = $processor->process('Before [opencalendar view="month"] After');
        self::assertStringContainsString('oc-calendar', $processed);
        self::assertStringContainsString('Before ', $processed);
        self::assertStringContainsString(' After', $processed);

        $processedHidden = $processor->process(
            '[opencalendar view="list" limit="10" from="now" to="+30 days" show_past="false" show_filters="false" show_search="false" /]'
        );
        self::assertStringContainsString('oc-list', $processedHidden);
        self::assertStringNotContainsString('data-oc-filters', $processedHidden);

        $query = EventQuery::fromRequest([
            'from' => '2026-07-01',
            'to' => '2026-08-31',
            'sort' => 'asc',
            'limit' => 50,
        ]);
        $result = $container->calendarService()->queryEvents($query);
        self::assertGreaterThan(0, $result->total);
    }

    public function testSourceConfigSlugify(): void
    {
        $config = SourceConfig::fromArray([
            'name' => 'Fire Brigade!',
            'type' => 'ics',
            'url' => 'https://example.com/a.ics',
            'enabled' => true,
        ]);
        self::assertSame('fire-brigade', $config->key);
        self::assertSame(SourceType::Ics, $config->type);
    }
}
