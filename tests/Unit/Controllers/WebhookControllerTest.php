<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Controllers;

use Grav\Plugin\OpenCalendar\Controllers\WebhookController;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Http\HttpResponse;
use Grav\Plugin\OpenCalendar\Services\Container;
use PHPUnit\Framework\TestCase;

final class WebhookControllerTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/opencalendar-webhook-' . uniqid('', true) . '.db';
    }

    protected function tearDown(): void
    {
        foreach ([$this->dbPath, $this->dbPath . '-wal', $this->dbPath . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testRejectsMissingSecretAndBadToken(): void
    {
        $container = $this->container();

        $disabled = new WebhookController($container, [
            'enabled' => false,
            'secret' => 's3cret',
        ]);
        self::assertSame(404, $disabled->handle('POST', [], null, 's3cret')['status']);

        $noSecret = new WebhookController($container, [
            'enabled' => true,
            'secret' => '',
        ]);
        self::assertSame(503, $noSecret->handle('POST', [], null, 'x')['status']);

        $auth = new WebhookController($container, [
            'enabled' => true,
            'secret' => 's3cret',
            'allow_source_param' => true,
        ]);
        self::assertSame(401, $auth->handle('POST', [], null, 'wrong')['status']);
    }

    public function testAuthorizedWebhookRunsSync(): void
    {
        $controller = new WebhookController($this->container(), [
            'enabled' => true,
            'secret' => 's3cret',
            'allow_source_param' => true,
        ]);

        $response = $controller->handle('POST', [], null, 's3cret');
        self::assertSame(200, $response['status']);
        self::assertTrue($response['body']['ok'] ?? false);
        self::assertNotEmpty($response['body']['results'] ?? []);
    }

    private function container(): Container
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
                throw new \RuntimeException('HTTP should not be called');
            }
        };

        $pluginPath = dirname(__DIR__, 3);

        return new Container(
            config: [
                'timezone' => 'UTC',
                'sync_interval' => 15,
                'cleanup' => 'never',
                'storage' => ['path' => $this->dbPath, 'wal_mode' => true],
                'cache' => ['enabled' => false],
                'sources' => [
                    [
                        'name' => 'Webhook Fixture',
                        'enabled' => true,
                        'type' => 'local',
                        'url' => 'tests/Fixtures/sample.ics',
                        'refresh' => '15',
                        'color' => '#123456',
                    ],
                ],
                'advanced' => [
                    'import' => [
                        'expand_recurring' => false,
                        'recurring_horizon_days' => 30,
                    ],
                ],
            ],
            pluginPath: $pluginPath,
            httpClient: $http,
        );
    }
}
