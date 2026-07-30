<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\OpenCalendar\Api\RateLimiter;
use Grav\Plugin\OpenCalendar\Controllers\AdminController;
use Grav\Plugin\OpenCalendar\Controllers\ApiController;
use Grav\Plugin\OpenCalendar\Controllers\ExportController;
use Grav\Plugin\OpenCalendar\Controllers\ShortcodeProcessor;
use Grav\Plugin\OpenCalendar\Controllers\WebhookController;
use Grav\Plugin\OpenCalendar\Events\GravEventDispatcher;
use Grav\Plugin\OpenCalendar\Logging\BridgeLogger;
use Grav\Plugin\OpenCalendar\Logging\NullLogger;
use Grav\Plugin\OpenCalendar\Services\ConfigNormalizer;
use Grav\Plugin\OpenCalendar\Services\Container;
use Grav\Plugin\OpenCalendar\Twig\TwigExtension;
use RocketTheme\Toolbox\Event\Event;

/**
 * OpenCalendar Grav plugin.
 *
 * Aggregates external calendar sources into SQLite and exposes Twig, shortcodes, and optional REST API.
 */
class OpenCalendarPlugin extends Plugin
{
    private ?Container $container = null;

    /**
     * @return array<string, array{0: string, 1?: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0],
            ],
        ];
    }

    public function autoload(): ClassLoader
    {
        $autoload = __DIR__ . '/vendor/autoload.php';

        // Never fatal Grav when Composer deps are missing — disable in onPluginsInitialized instead.
        if (!is_file($autoload)) {
            return new ClassLoader();
        }

        /** @var ClassLoader $loader */
        $loader = require $autoload;

        return $loader;
    }

    public function onPluginsInitialized(): void
    {
        if (!$this->dependenciesInstalled()) {
            $message = 'OpenCalendar: missing vendor/autoload.php. '
                . 'Run: cd user/plugins/opencalendar && composer install --no-dev --optimize-autoloader';

            if (isset($this->grav['log'])) {
                $this->grav['log']->error($message);
            }

            if ($this->isAdmin() && isset($this->grav['messages'])) {
                $this->grav['messages']->add(
                    'OpenCalendar is not ready: run <code>composer install --no-dev</code> in <code>user/plugins/opencalendar</code>.',
                    'error'
                );
            }

            return;
        }

        if ($this->isAdmin()) {
            $this->enable([
                'onTwigSiteVariables' => ['onAdminTwigSiteVariables', 0],
                'onAdminTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 0],
                'onAdminMenu' => ['onAdminMenu', 0],
                'onAdminDashboard' => ['onAdminDashboard', 0],
                'onPagesInitialized' => ['onAdminPagesInitialized', 0],
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            ]);

            return;
        }

        $this->enable([
            'onTwigExtensions' => ['onTwigExtensions', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onPageContentProcessed' => ['onPageContentProcessed', 0],
            'onPagesInitialized' => ['onPagesInitialized', 0],
            'onSchedulerInitialized' => ['onSchedulerInitialized', 0],
        ]);

        if ($this->pluginConfig('advanced.scheduler.on_cache_clear', true)) {
            $this->enable([
                'onCacheClear' => ['onCacheClear', 0],
            ]);
        }

        try {
            $this->container()->boot();
        } catch (\Throwable $e) {
            $this->grav['log']->warning('OpenCalendar boot failed: ' . $e->getMessage());
        }
    }

    private function dependenciesInstalled(): bool
    {
        return is_file(__DIR__ . '/vendor/autoload.php');
    }

    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';

        if ($this->isAdmin()) {
            $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/themes/grav/templates';
        }
    }

    /**
     * @param Event $event
     */
    public function onAdminTwigTemplatePaths($event): void
    {
        $paths = $event['paths'] ?? [];
        if (!is_array($paths)) {
            $paths = [];
        }

        $paths[] = __DIR__ . '/admin/themes/grav/templates';
        $event['paths'] = $paths;
    }

    public function onTwigExtensions(): void
    {
        try {
            $container = $this->container();
            $extension = new TwigExtension(
                $container->calendarService(),
                $this->pluginConfig(),
                $this->translator()
            );
            $this->grav['twig']->twig->addExtension($extension);
        } catch (\Throwable $e) {
            $this->grav['log']->warning('OpenCalendar Twig extension failed: ' . $e->getMessage());
        }
    }

    public function onTwigSiteVariables(): void
    {
        $assets = $this->grav['assets'];
        $base = 'plugin://opencalendar';
        $jsVersion = @filemtime(__DIR__ . '/assets/js/opencalendar.js') ?: time();

        $assets->addCss($base . '/assets/css/opencalendar.css', [
            'priority' => 80,
            'pipeline' => false,
            'loading' => null,
        ]);
        $assets->addCss('https://cdn.jsdelivr.net/npm/@event-calendar/build@5.10.1/dist/event-calendar.min.css');
        $assets->addJs('https://cdn.jsdelivr.net/npm/@event-calendar/build@5.10.1/dist/event-calendar.min.js', ['group' => 'bottom']);
        $assets->addJs($base . '/assets/js/opencalendar.js', [
            'group' => 'bottom',
            'priority' => 80,
            'pipeline' => false,
        ]);

        // Bust browser/CDN caches when plugin assets change.
        try {
            $assets->addInlineJs(
                'window.OpenCalendarAssetVersion=' . json_encode((string) $jsVersion) . ';',
                ['group' => 'bottom', 'priority' => 79]
            );
        } catch (\Throwable) {
            // ignore older Grav asset APIs
        }
    }

    public function onAdminTwigSiteVariables(): void
    {
        $assets = $this->grav['assets'];
        $base = 'plugin://opencalendar';
        $assets->addCss($base . '/assets/admin/opencalendar-admin.css');
        $assets->addJs($base . '/assets/admin/opencalendar-admin.js', ['group' => 'bottom']);

        try {
            $status = (new AdminController($this->container()))->status();
            $this->grav['twig']->twig_vars['opencalendar_status'] = $status;
        } catch (\Throwable $e) {
            $this->grav['twig']->twig_vars['opencalendar_status'] = [
                'calendars' => [],
                'event_count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function onAdminMenu(): void
    {
        // Configuration lives under Plugins → OpenCalendar; sync dashboard is a plugin blueprint tab.
    }

    /**
     * Register a compact sync-status widget on the Grav Admin home dashboard.
     */
    public function onAdminDashboard(): void
    {
        $widgetId = 'dashboard-opencalendar';
        $path = 'plugins.admin.widgets_display.' . $widgetId;
        if ($this->config->get($path) === null) {
            $this->config->set($path, true);
        }

        $language = $this->grav['language'] ?? null;
        $name = 'OpenCalendar';
        if (is_object($language) && method_exists($language, 'translate')) {
            $translated = (string) $language->translate('PLUGIN_OPENCALENDAR.DASHBOARD_WIDGET_TITLE');
            if ($translated !== '' && $translated !== 'PLUGIN_OPENCALENDAR.DASHBOARD_WIDGET_TITLE') {
                $name = $translated;
            }
        }

        $this->grav['twig']->plugins_hooked_dashboard_widgets_bottom[] = [
            'name' => $name,
            'template' => $widgetId,
        ];
    }

    /**
     * Optional sync when Grav cache is cleared (advanced.scheduler.on_cache_clear).
     */
    public function onCacheClear(): void
    {
        if (!$this->pluginConfig('advanced.scheduler.enabled', true)) {
            return;
        }

        try {
            $container = $this->container();
            $container->calendarService()->synchronize($container->sourceConfigs(), false);
        } catch (\Throwable $e) {
            $this->grav['log']->warning('OpenCalendar cache-clear sync failed: ' . $e->getMessage());
        }
    }

    public function onPageContentProcessed(Event $event): void
    {
        $page = $event['page'] ?? null;
        if ($page === null) {
            return;
        }

        $content = $page->getRawContent();
        if (!is_string($content) || !str_contains(strtolower($content), '[opencalendar')) {
            return;
        }

        try {
            $extension = new TwigExtension(
                $this->container()->calendarService(),
                $this->pluginConfig(),
                $this->translator()
            );
            $processor = new ShortcodeProcessor($extension);
            $page->setRawContent($processor->process($content));
        } catch (\Throwable $e) {
            $this->grav['log']->warning('OpenCalendar shortcode processing failed: ' . $e->getMessage());
        }
    }

    public function onPagesInitialized(): void
    {
        $this->disableCacheForOpenCalendarPages();

        $path = $this->grav['uri']->path();
        $query = $this->grav['uri']->query(null, true);
        if (!is_array($query)) {
            $query = [];
        }

        if ($this->maybeHandleWebhook($path, $query)) {
            return;
        }

        if ($this->maybeHandleExport($path, $query)) {
            return;
        }

        if (!$this->pluginConfig('api.enabled', false)) {
            return;
        }

        $route = rtrim((string) $this->pluginConfig('api.route', '/opencalendar/api'), '/');

        if ($path !== $route && !str_starts_with($path, $route . '/')) {
            return;
        }

        $subPath = substr($path, strlen($route)) ?: '/';

        $apiConfig = $this->pluginConfig('api', []);
        if (!is_array($apiConfig)) {
            $apiConfig = [];
        }

        $limiter = new RateLimiter(
            (bool) ($apiConfig['rate_limit']['enabled'] ?? true),
            (int) ($apiConfig['rate_limit']['max_requests'] ?? 60),
            (int) ($apiConfig['rate_limit']['per_minutes'] ?? 1),
        );

        $controller = new ApiController($this->container(), $apiConfig, $limiter);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $response = $controller->handle(
            $subPath,
            $query,
            is_string($ip) ? $ip : '0.0.0.0',
            $method,
            $this->exportConfig(),
        );

        http_response_code($response['status']);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }

        $body = $response['body'];
        if (is_string($body)) {
            echo $body;
        } else {
            echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        exit;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function maybeHandleWebhook(string $path, array $query): bool
    {
        if (!$this->pluginConfig('webhook.enabled', false)) {
            return false;
        }

        $route = rtrim((string) $this->pluginConfig('webhook.route', '/opencalendar/webhook'), '/');
        if ($path !== $route && !str_starts_with($path, $route . '/')) {
            return false;
        }

        $webhookConfig = $this->pluginConfig('webhook', []);
        if (!is_array($webhookConfig)) {
            $webhookConfig = [];
        }

        $token = $this->extractWebhookToken($query);
        $rawBody = file_get_contents('php://input');
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $controller = new WebhookController($this->container(true), $webhookConfig);
        $response = $controller->handle($method, $query, is_string($rawBody) ? $rawBody : null, $token);

        http_response_code($response['status']);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function maybeHandleExport(string $path, array $query): bool
    {
        if (!$this->pluginConfig('export.enabled', true)) {
            return false;
        }

        $route = rtrim((string) $this->pluginConfig('export.route', '/opencalendar/calendar.ics'), '/');
        if ($path !== $route) {
            return false;
        }

        $controller = new ExportController($this->container(), $this->exportConfig());
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        $response = $controller->handle(
            $query,
            is_string($ifNoneMatch) ? $ifNoneMatch : null,
        );

        http_response_code($response['status']);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $response['body'];
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportConfig(): array
    {
        $config = $this->pluginConfig('export', []);

        return is_array($config) ? $config : [];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function extractWebhookToken(array $query): string
    {
        $headerCandidates = [
            'HTTP_X_OPENCALENDAR_TOKEN',
            'HTTP_X_WEBHOOK_TOKEN',
            'HTTP_AUTHORIZATION',
        ];

        foreach ($headerCandidates as $header) {
            $value = $_SERVER[$header] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            if (str_starts_with(strtolower($value), 'bearer ')) {
                return trim(substr($value, 7));
            }

            return trim($value);
        }

        $token = $query['token'] ?? $query['secret'] ?? '';

        return is_string($token) ? $token : '';
    }

    public function onAdminPagesInitialized(): void
    {
        $uri = $this->grav['uri'];
        $paths = array_values($uri->paths());

        // Support both /admin/plugins/opencalendar/... and paths without the admin prefix.
        $pluginIndex = array_search('opencalendar', $paths, true);
        if ($pluginIndex === false || ($paths[$pluginIndex - 1] ?? '') !== 'plugins') {
            return;
        }

        $action = $paths[$pluginIndex + 1] ?? null;
        if ($action === null || $action === '') {
            return;
        }

        $admin = new AdminController($this->container(true));
        $result = match ($action) {
            'sync' => $admin->syncNow(isset($_GET['source']) ? (string) $_GET['source'] : null),
            'rebuild' => $admin->rebuildDatabase(),
            'clear-cache' => $admin->clearCache(),
            'status' => $admin->status(),
            default => null,
        };

        if ($result === null) {
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function onSchedulerInitialized(Event $event): void
    {
        if (!$this->pluginConfig('advanced.scheduler.enabled', true)) {
            return;
        }

        try {
            $scheduler = $event['scheduler'];
            $interval = (string) $this->pluginConfig('sync_interval', '15');
            $job = $scheduler->addFunction(function (): void {
                try {
                    $container = $this->container();
                    $container->calendarService()->synchronize($container->sourceConfigs(), false);
                } catch (\Throwable $e) {
                    $this->grav['log']->warning('OpenCalendar scheduled sync failed: ' . $e->getMessage());
                }
            }, [], 'opencalendar-sync');

            $at = match ($interval) {
                '5' => '*/5 * * * *',
                '10' => '*/10 * * * *',
                '15' => '*/15 * * * *',
                '30' => '*/30 * * * *',
                '60' => '0 * * * *',
                'daily' => '0 2 * * *',
                default => '*/15 * * * *',
            };

            if (method_exists($job, 'at')) {
                $job->at($at);
            }
        } catch (\Throwable $e) {
            $this->grav['log']->warning('OpenCalendar scheduler registration failed: ' . $e->getMessage());
        }
    }

    /**
     * @return callable(string, array<int|string, scalar|null>=): string
     */
    private function translator(): callable
    {
        return function (string $key, array $replace = []): string {
            $language = $this->grav['language'] ?? null;
            if (!is_object($language) || !method_exists($language, 'translate')) {
                return $key;
            }

            if ($replace === []) {
                return (string) $language->translate($key);
            }

            return (string) $language->translate(array_merge([$key], array_values($replace)));
        };
    }

    private function pluginConfig(?string $key = null, mixed $default = null): mixed
    {
        $raw = $this->config->get('plugins.opencalendar', []);
        $config = ConfigNormalizer::toArray($raw);

        // Fallback: some Grav versions expose plugin config via ArrayAccess as a Data object.
        if ($config === [] && isset($this->config['plugins.opencalendar'])) {
            $config = ConfigNormalizer::toArray($this->config['plugins.opencalendar']);
        }

        if ($key === null) {
            return $config;
        }

        $parts = explode('.', $key);
        $value = $config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    private function disableCacheForOpenCalendarPages(): void
    {
        try {
            $page = $this->grav['page'] ?? null;
            $uri = $this->grav['uri'] ?? null;
            $hasPageParam = is_object($uri)
                && method_exists($uri, 'param')
                && ($uri->param('page') || $uri->param('oc_page'));

            $raw = '';
            if (is_object($page) && method_exists($page, 'getRawContent')) {
                $raw = (string) $page->getRawContent();
            }

            $usesPlugin = $hasPageParam
                || str_contains(strtolower($raw), '[opencalendar')
                || str_contains($raw, 'opencalendar(');

            if (!$usesPlugin || !is_object($page) || !method_exists($page, 'modifyHeader')) {
                return;
            }

            $page->modifyHeader('cache_enable', false);
        } catch (\Throwable) {
            // ignore
        }
    }

    private function container(bool $fresh = false): Container
    {
        if ($fresh) {
            $this->container = null;
        }

        if ($this->container instanceof Container) {
            return $this->container;
        }

        $config = $this->pluginConfig();
        if (!is_array($config)) {
            $config = [];
        }

        $cache = $this->grav['cache'] ?? null;
        $gravLog = $this->grav['log'] ?? null;
        $logger = is_object($gravLog) ? new BridgeLogger($gravLog) : new NullLogger();

        $userDataPath = null;
        try {
            $locator = $this->grav['locator'] ?? null;
            if (is_object($locator) && method_exists($locator, 'findResource')) {
                $found = $locator->findResource('user-data://', true);
                if (is_string($found) && $found !== '') {
                    $userDataPath = $found;
                }
            }
        } catch (\Throwable) {
            $userDataPath = null;
        }

        // Fallback: user/plugins/opencalendar → user/data
        if ($userDataPath === null || $userDataPath === '') {
            $userDataPath = dirname(__DIR__, 2) . '/data';
        }

        $this->container = new Container(
            config: $config,
            pluginPath: __DIR__,
            gravCache: is_object($cache) ? $cache : null,
            logger: $logger,
            userDataPath: $userDataPath,
            dispatcher: new GravEventDispatcher($this->grav),
        );

        return $this->container;
    }
}
