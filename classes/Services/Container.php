<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Services;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\CleanupPolicy;
use Grav\Plugin\OpenCalendar\Enum\SyncInterval;
use Grav\Plugin\OpenCalendar\Events\EventDispatcherInterface;
use Grav\Plugin\OpenCalendar\Events\NullEventDispatcher;
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
    private const CANONICAL_STORAGE = 'user-data://opencalendar/opencalendar.db';

    private ?Database $database = null;
    private ?CalendarService $calendarService = null;
    private ?CacheService $cacheService = null;
    private ?SyncService $syncService = null;
    private bool $schemaMigrated = false;
    private bool $runtimeMigrated = false;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config,
        private readonly string $pluginPath,
        private readonly mixed $gravCache = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly ?string $userDataPath = null,
        private readonly EventDispatcherInterface $dispatcher = new NullEventDispatcher(),
        private readonly ?string $configFilePath = null,
    ) {
        $this->config = $config;
    }

    public function boot(): void
    {
        $this->ensureRuntimeDataOutsidePlugin();
        $this->database();
        $this->migrate();
    }

    public function database(): Database
    {
        if ($this->database instanceof Database) {
            return $this->database;
        }

        $this->ensureRuntimeDataOutsidePlugin();

        $configured = (string) ($this->config['storage']['path'] ?? self::CANONICAL_STORAGE);
        $path = $this->resolvePath($configured);
        $wal = (bool) ($this->config['storage']['wal_mode'] ?? true);

        $this->database = new Database($path, $wal);

        return $this->database;
    }

    public function migrate(): void
    {
        if ($this->schemaMigrated) {
            return;
        }

        $migrator = new Migrator(
            $this->database(),
            $this->pluginPath . '/classes/Storage/Migrations'
        );
        $migrator->migrate();
        $this->schemaMigrated = true;
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
            'user_agent' => (string) (
                $this->config['advanced']['http']['user_agent'] ?? 'OpenCalendar/1.0 Grav Plugin'
            ),
        ];

        return SourceFactory::createDefault(
            http: $http,
            httpOptions: $httpOptions,
            importOptions: is_array($this->config['advanced']['import'] ?? null)
                ? $this->config['advanced']['import']
                : [],
            defaultTimezone: (string) ($this->config['timezone'] ?? 'Europe/Berlin'),
            localBasePath: $this->localSourceBases(),
        );
    }

    /**
     * Writable directory for Admin-uploaded calendar files.
     */
    public function uploadsDirectory(): string
    {
        return rtrim($this->userDataRoot(), '/') . '/opencalendar/uploads';
    }

    public function calendarUploadService(): CalendarUploadService
    {
        return new CalendarUploadService($this->uploadsDirectory());
    }

    /**
     * Allowed filesystem roots for type=local sources (user data only — never the plugin tree).
     *
     * @return list<string>
     */
    public function localSourceBases(): array
    {
        $base = rtrim($this->userDataRoot(), '/') . '/opencalendar';

        return $base !== '/opencalendar' ? [$base] : [];
    }

    private function userDataRoot(): string
    {
        return $this->userDataPath
            ?? (dirname($this->pluginPath, 2) . '/data');
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
            $this->dispatcher,
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
            dispatcher: $this->dispatcher,
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

    /**
     * Move legacy runtime files out of the plugin directory and rewrite config paths.
     */
    private function ensureRuntimeDataOutsidePlugin(): void
    {
        if ($this->runtimeMigrated) {
            return;
        }
        $this->runtimeMigrated = true;

        $migrator = new RuntimeDataMigrator(
            $this->pluginPath,
            $this->userDataRoot(),
            $this->logger,
        );

        $result = $migrator->migrate($this->config);
        $this->config = $result['config'];

        // Always canonicalize plugin-relative storage to user-data after migration attempt.
        $storagePath = trim((string) ($this->config['storage']['path'] ?? ''));
        if ($storagePath === '' || $migrator->isPluginRelativeStorage($storagePath)) {
            if (!isset($this->config['storage']) || !is_array($this->config['storage'])) {
                $this->config['storage'] = [];
            }
            $this->config['storage']['path'] = self::CANONICAL_STORAGE;
            $result['storage_rewritten'] = true;
            $result['migrated'] = true;
        }

        if (!$result['migrated'] || $this->configFilePath === null || $this->configFilePath === '') {
            return;
        }

        try {
            $patcher = new PluginConfigPatcher($this->configFilePath);
            $patcher->patch(function (array $fileConfig) use ($result): array {
                if ($result['storage_rewritten']) {
                    if (!isset($fileConfig['storage']) || !is_array($fileConfig['storage'])) {
                        $fileConfig['storage'] = [];
                    }
                    $fileConfig['storage']['path'] = self::CANONICAL_STORAGE;
                }

                if ($result['sources_rewritten'] > 0 && isset($this->config['sources']) && is_array($this->config['sources'])) {
                    // Prefer rewritten source URLs from the migrated in-memory config when keys match.
                    $rewrittenByKey = [];
                    foreach ($this->config['sources'] as $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $name = trim((string) ($row['name'] ?? ''));
                        if ($name === '') {
                            continue;
                        }
                        $rewrittenByKey[SourceConfig::slugify($name)] = $row;
                    }

                    $sources = ConfigNormalizer::toArray($fileConfig['sources'] ?? []);
                    foreach ($sources as $index => $row) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $name = trim((string) ($row['name'] ?? ''));
                        if ($name === '') {
                            continue;
                        }
                        $key = SourceConfig::slugify($name);
                        if (!isset($rewrittenByKey[$key])) {
                            continue;
                        }
                        $newUrl = trim((string) ($rewrittenByKey[$key]['url'] ?? ''));
                        if ($newUrl !== '' && $newUrl !== trim((string) ($row['url'] ?? ''))) {
                            $sources[$index]['url'] = $newUrl;
                        }
                    }
                    $fileConfig['sources'] = array_values($sources);
                }

                return $fileConfig;
            });
        } catch (\Throwable $e) {
            $this->logger->warning('OpenCalendar could not persist migrated paths to config: ' . $e->getMessage());
        }
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            $path = self::CANONICAL_STORAGE;
        }

        if (str_starts_with($path, 'user-data://')) {
            $relative = ltrim(substr($path, strlen('user-data://')), '/');

            return rtrim($this->userDataRoot(), '/') . '/' . $relative;
        }

        if ($path[0] === '/' || preg_match('#^[A-Za-z]:[/\\\\]#', $path) === 1) {
            // Absolute paths inside the plugin tree are not writable storage anymore.
            $migrator = new RuntimeDataMigrator($this->pluginPath, $this->userDataRoot(), $this->logger);
            if ($migrator->isPathInsidePlugin($path)) {
                return rtrim($this->userDataRoot(), '/') . '/opencalendar/opencalendar.db';
            }

            return $path;
        }

        // Relative paths historically lived under the plugin; keep them under user data.
        $relative = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($relative, 'data/')) {
            $relative = substr($relative, strlen('data/'));
        }

        return rtrim($this->userDataRoot(), '/') . '/opencalendar/' . ltrim($relative, '/');
    }
}
