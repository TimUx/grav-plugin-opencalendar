<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Controllers;

use Grav\Plugin\OpenCalendar\Services\Container;

/**
 * Admin dashboard actions: sync now, rebuild database, clear cache.
 */
final class AdminController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return array{ok: bool, message: string, results?: list<array<string, mixed>>}
     */
    public function syncNow(?string $sourceKey = null): array
    {
        $sources = $this->container->sourceConfigs();

        try {
            if ($sourceKey !== null && $sourceKey !== '') {
                $result = $this->container->calendarService()->synchronizeOne($sources, $sourceKey);
                if ($result === null) {
                    return ['ok' => false, 'message' => 'Source not found: ' . $sourceKey];
                }

                return [
                    'ok' => true,
                    'message' => 'Synchronization completed',
                    'results' => [$result->toArray()],
                ];
            }

            $results = $this->container->calendarService()->synchronize($sources, true);

            return [
                'ok' => true,
                'message' => 'Synchronization completed',
                'results' => array_map(static fn ($r) => $r->toArray(), $results),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string, results?: list<array<string, mixed>>}
     */
    public function rebuildDatabase(): array
    {
        try {
            $results = $this->container->calendarService()->rebuild($this->container->sourceConfigs());

            return [
                'ok' => true,
                'message' => 'Database rebuilt',
                'results' => array_map(static fn ($r) => $r->toArray(), $results),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function clearCache(): array
    {
        try {
            $this->container->calendarService()->clearCache();

            return ['ok' => true, 'message' => 'Cache cleared'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{calendars: list<array<string, mixed>>, event_count: int}
     */
    public function status(): array
    {
        $this->container->boot();

        return [
            'calendars' => array_map(
                static fn ($c) => $c->toArray(),
                $this->container->calendarService()->listCalendars()
            ),
            'event_count' => $this->container->calendarService()->eventCount(),
        ];
    }
}
