<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Controllers;

use Grav\Plugin\OpenCalendar\Enum\SyncStatus;
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
     * @return array<string, mixed>
     */
    public function syncNow(?string $sourceKey = null): array
    {
        $sources = $this->container->sourceConfigs();

        if ($sources === []) {
            return array_merge($this->status(), [
                'ok' => false,
                'message' => 'No sources found in plugin config. Open the Sources tab, configure at least one source, click Save, then sync again.',
            ]);
        }

        try {
            if ($sourceKey !== null && $sourceKey !== '') {
                $result = $this->container->calendarService()->synchronizeOne($sources, $sourceKey);
                if ($result === null) {
                    return array_merge($this->status(), [
                        'ok' => false,
                        'message' => 'Source not found: ' . $sourceKey,
                    ]);
                }

                return array_merge($this->status(), [
                    'ok' => $result->status !== SyncStatus::Error,
                    'message' => $this->summarizeResults([$result]),
                    'results' => [$result->toArray()],
                ]);
            }

            $results = $this->container->calendarService()->synchronize($sources, true);

            return array_merge($this->status(), [
                'ok' => true,
                'message' => $this->summarizeResults($results),
                'results' => array_map(static fn ($r) => $r->toArray(), $results),
            ]);
        } catch (\Throwable $e) {
            return array_merge($this->status(), [
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rebuildDatabase(): array
    {
        $sources = $this->container->sourceConfigs();
        if ($sources === []) {
            return array_merge($this->status(), [
                'ok' => false,
                'message' => 'No sources found in plugin config. Save Sources first.',
            ]);
        }

        try {
            $results = $this->container->calendarService()->rebuild($sources);

            return array_merge($this->status(), [
                'ok' => true,
                'message' => 'Database rebuilt. ' . $this->summarizeResults($results),
                'results' => array_map(static fn ($r) => $r->toArray(), $results),
            ]);
        } catch (\Throwable $e) {
            return array_merge($this->status(), [
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function clearCache(): array
    {
        try {
            $this->container->calendarService()->clearCache();

            return array_merge($this->status(), [
                'ok' => true,
                'message' => 'Cache cleared',
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message: string, calendars: list<array<string, mixed>>, event_count: int, source_count: int}
     */
    public function status(): array
    {
        $this->container->boot();

        return [
            'ok' => true,
            'message' => 'OK',
            'calendars' => array_map(
                static fn ($c) => $c->toArray(),
                $this->container->calendarService()->listCalendars()
            ),
            'event_count' => $this->container->calendarService()->eventCount(),
            'source_count' => count($this->container->sourceConfigs()),
        ];
    }

    /**
     * @param list<\Grav\Plugin\OpenCalendar\Dto\SyncResult> $results
     */
    private function summarizeResults(array $results): string
    {
        if ($results === []) {
            return 'Synchronization completed, but no sources were processed.';
        }

        $imported = 0;
        $updated = 0;
        $deleted = 0;
        $errors = [];
        $skipped = 0;
        $success = 0;

        foreach ($results as $result) {
            $imported += $result->imported;
            $updated += $result->updated;
            $deleted += $result->deleted;

            if ($result->status === SyncStatus::Error) {
                $errors[] = $result->sourceKey . ': ' . ($result->error ?? 'error');
            } elseif ($result->status === SyncStatus::Skipped) {
                ++$skipped;
            } else {
                ++$success;
            }
        }

        $message = sprintf(
            'Synchronization completed (%d source(s): %d ok, %d skipped, %d error). Imported %d, updated %d, deleted %d.',
            count($results),
            $success,
            $skipped,
            count($errors),
            $imported,
            $updated,
            $deleted
        );

        if ($errors !== []) {
            $message .= ' Errors: ' . implode('; ', $errors);
        }

        return $message;
    }
}
