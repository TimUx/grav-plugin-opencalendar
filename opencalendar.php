<?php

declare(strict_types=1);

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\OpenCalendar\Api\RateLimiter;
use Grav\Plugin\OpenCalendar\Controllers\AdminController;
use Grav\Plugin\OpenCalendar\Controllers\ApiController;
use Grav\Plugin\OpenCalendar\Controllers\ShortcodeProcessor;
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
                'onAdminMenu' => ['onAdminMenu', 0],
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
    }

    public function onTwigExtensions(): void
    {
        try {
            $container = $this->container();
            $extension = new TwigExtension(
                $container->calendarService(),
                $this->pluginConfig()
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

        $assets->addCss($base . '/assets/css/opencalendar.css');
        $assets->addCss('https://cdn.jsdelivr.net/npm/@event-calendar/build@5.10.1/dist/event-calendar.min.css');
        $assets->addJs('https://cdn.jsdelivr.net/npm/@event-calendar/build@5.10.1/dist/event-calendar.min.js', ['group' => 'bottom']);
        $assets->addJs($base . '/assets/js/opencalendar.js', ['group' => 'bottom']);
    }

    public function onAdminTwigSiteVariables(): void
    {
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
                $this->pluginConfig()
            );
            $processor = new ShortcodeProcessor($extension);
            $page->setRawContent($processor->process($content));
        } catch (\Throwable $e) {
            $this->grav['log']->warning('OpenCalendar shortcode processing failed: ' . $e->getMessage());
        }
    }

    public function onPagesInitialized(): void
    {
        if (!$this->pluginConfig('api.enabled', false)) {
            return;
        }

        $route = rtrim((string) $this->pluginConfig('api.route', '/opencalendar/api'), '/');
        $path = $this->grav['uri']->path();

        if ($path !== $route && !str_starts_with($path, $route . '/')) {
            return;
        }

        $subPath = substr($path, strlen($route)) ?: '/';
        $query = $this->grav['uri']->query(null, true);
        if (!is_array($query)) {
            $query = [];
        }

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
        $response = $controller->handle($subPath, $query, is_string($ip) ? $ip : '0.0.0.0');

        http_response_code($response['status']);
        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($response['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function onAdminPagesInitialized(): void
    {
        $uri = $this->grav['uri'];
        $paths = $uri->paths();

        // /admin/plugins/opencalendar/sync|rebuild|clear-cache
        if (($paths[0] ?? '') !== 'admin' || ($paths[1] ?? '') !== 'plugins' || ($paths[2] ?? '') !== 'opencalendar') {
            return;
        }

        $action = $paths[3] ?? null;
        if ($action === null) {
            return;
        }

        $admin = new AdminController($this->container());
        $result = match ($action) {
            'sync' => $admin->syncNow(isset($_GET['source']) ? (string) $_GET['source'] : null),
            'rebuild' => $admin->rebuildDatabase(),
            'clear-cache' => $admin->clearCache(),
            'status' => ['ok' => true, 'message' => 'OK'] + $admin->status(),
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

    private function pluginConfig(?string $key = null, mixed $default = null): mixed
    {
        $config = $this->config->get('plugins.opencalendar');
        if (!is_array($config)) {
            $config = [];
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

    private function container(): Container
    {
        if ($this->container instanceof Container) {
            return $this->container;
        }

        $config = $this->pluginConfig();
        if (!is_array($config)) {
            $config = [];
        }

        $cache = $this->grav['cache'] ?? null;
        $logger = $this->grav['log'] ?? new \Psr\Log\NullLogger();

        $this->container = new Container(
            config: $config,
            pluginPath: __DIR__,
            gravCache: $cache,
            logger: $logger instanceof \Psr\Log\LoggerInterface ? $logger : new \Psr\Log\NullLogger(),
        );

        return $this->container;
    }
}
