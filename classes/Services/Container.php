<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Services;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\CleanupPolicy;
use Grav\Plugin\OpenCalendar\Enum\SyncInterval;
use Grav\Plugin\OpenCalendar\Http\CurlHttpClient;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Logging\LoggerInterface;
use Grav\Plugin\OpenCalendar\Logging\NullLogger;
use Grav\Plugin\OpenCalendar\Source\SourceFactory;
use Grav\Plugin\OpenCalendar\Storage\CalendarRepository;
use Grav\Plugin\OpenCalendar\Storage\Database;
use Grav\Plugin\OpenCalendar\Storage\EventRepository;
use Grav\Plugin\OpenCalendar\Storage\Migrator;
use Grav\Plugin\OpenCalendar\Sync\SyncJob;
use Grav\Plugin\OpenCalendar\Sync\SyncService;

/**
 * Lightweight service container / factory for OpenCalendar.
 */
final class Container
{
    private ?Database $database = null;
    private ?CalendarService $calendarService = null;
    private ?CacheService $cacheService = null;
    private ?SyncService $syncService = null;
    private bool $migrated = false;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
        private readonly string $pluginPath,
        private readonly mixed $gravCache = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly ?string $userDataPath = null,
    ) {
    }

    public function boot(): void
    {
        $this->database();
        $this->migrate();
    }

    public function database(): Database
    {
        if ($this->database instanceof Database) {
            return $this->database;
        }

        $configured = (string) ($this->config['storage']['path'] ?? 'user-data://opencalendar/opencalendar.db');
        $path = $this->resolvePath($configured);
        $wal = (bool) ($this->config['storage']['wal_mode'] ?? true);

        $this->database = new Database($path, $wal);

        return $this->database;
    }

    public function migrate(): void
    {
        if ($this->migrated) {
            return;
        }

        $migrator = new Migrator(
            $this->database(),
            $this->pluginPath . '/classes/Storage/Migrations'
        );
        $migrator->migrate();
        $this->migrated = true;
    }

    public function cache(): CacheService
    {
        if ($this->cacheService instanceof CacheService) {
            return $this->cacheService;
        }

        $this->cacheService = new CacheService(
            enabled: (bool) ($this->config['cache']['enabled'] ?? true),
            defaultTtl: (int) ($this->config['cache']['ttl'] ?? 3600),
            gravCache: $this->gravCache,
        );

        return $this->cacheService;
    }

    public function calendarRepository(): CalendarRepository
    {
        return new CalendarRepository($this->database());
    }

    public function eventRepository(): EventRepository
    {
        return new EventRepository($this->database());
    }

    public function sourceFactory(): SourceFactory
    {
        $http = $this->httpClient ?? new CurlHttpClient();
        $httpOptions = [
            'timeout' => (int) ($this->config['advanced']['http']['timeout'] ?? 30),
            'verify_ssl' => (bool) ($this->config['advanced']['http']['verify_ssl'] ?? true),
            'max_redirects' => (int) ($this->config['advanced']['http']['max_redirects'] ?? 3),
            'user_agent' => (string) ($this->config['advanced']['http']['user_agent'] ?? 'OpenCalendar/1.0 Grav Plugin'),
        ];

        return SourceFactory::createDefault(
            http: $http,
            httpOptions: $httpOptions,
            importOptions: is_array($this->config['advanced']['import'] ?? null)
                ? $this->config['advanced']['import']
                : [],
            defaultTimezone: (string) ($this->config['timezone'] ?? 'Europe/Berlin'),
            localBasePath: $this->pluginPath,
        );
    }

    public function syncService(): SyncService
    {
        if ($this->syncService instanceof SyncService) {
            return $this->syncService;
        }

        $job = new SyncJob(
            $this->sourceFactory(),
            $this->calendarRepository(),
            $this->eventRepository(),
            $this->logger,
        );

        $this->syncService = new SyncService(
            job: $job,
            calendars: $this->calendarRepository(),
            events: $this->eventRepository(),
            db: $this->database(),
            interval: SyncInterval::fromConfig($this->config['sync_interval'] ?? 15),
            cleanup: CleanupPolicy::fromConfig($this->config['cleanup'] ?? 30),
            vacuumOnCleanup: (bool) ($this->config['storage']['vacuum_on_cleanup'] ?? false),
            logger: $this->logger,
        );

        return $this->syncService;
    }

    public function calendarService(): CalendarService
    {
        if ($this->calendarService instanceof CalendarService) {
            return $this->calendarService;
        }

        $this->migrate();

        $this->calendarService = new CalendarService(
            $this->eventRepository(),
            $this->calendarRepository(),
            $this->syncService(),
            $this->cache(),
            $this->config,
        );

        return $this->calendarService;
    }

    /**
     * @return list<SourceConfig>
     */
    public function sourceConfigs(): array
    {
        $rows = $this->config['sources'] ?? [];
        $rows = ConfigNormalizer::toArray($rows);
        if ($rows === []) {
            return [];
        }

        $configs = [];
        $usedKeys = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            // Skip completely empty list placeholders from Admin.
            if (trim((string) ($row['name'] ?? '')) === '' && trim((string) ($row['url'] ?? '')) === '') {
                continue;
            }

            $base = SourceConfig::fromArray($row);
            $key = $base->key;
            if (isset($usedKeys[$key])) {
                $key .= '-' . $index;
            }
            $usedKeys[$key] = true;

            $configs[] = new SourceConfig(
                key: $key,
                name: $base->name,
                enabled: $base->enabled,
                type: $base->type,
                url: $base->url,
                refresh: $base->refresh === 'inherit'
                    ? (string) ($this->config['sync_interval'] ?? '15')
                    : $base->refresh,
                color: $base->color,
                description: $base->description,
                auth: $base->auth,
            );
        }

        return $configs;
    }

    public function hasExplicitSourcesConfig(): bool
    {
        return array_key_exists('sources', $this->config);
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            $path = 'user-data://opencalendar/opencalendar.db';
        }

        if (str_starts_with($path, 'user-data://')) {
            $relative = ltrim(substr($path, strlen('user-data://')), '/');
            $base = $this->userDataPath
                ?? (dirname($this->pluginPath, 2) . '/data');

            return rtrim($base, '/') . '/' . $relative;
        }

        if ($path[0] === '/' || preg_match('#^[A-Za-z]:[/\\\\]#', $path) === 1) {
            return $path;
        }

        // Legacy relative paths remain under the plugin directory.
        return rtrim($this->pluginPath, '/') . '/' . ltrim($path, '/');
    }
}
