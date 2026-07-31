<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Controllers;

use Grav\Plugin\OpenCalendar\Enum\SyncStatus;
use Grav\Plugin\OpenCalendar\Services\Container;
use Grav\Plugin\OpenCalendar\Services\PluginSourcesWriter;

/**
 * Admin dashboard actions: sync now, rebuild database, clear cache, upload calendar.
 */
final class AdminController
{
    public function __construct(
        private Container $container,
        private readonly ?string $pluginConfigPath = null,
        private readonly ?\Closure $refreshContainer = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function syncNow(?string $sourceKey = null): array
    {
        $sources = $this->container->sourceConfigs();

        if ($sources === []) {
            $removed = 0;
            if ($this->container->hasExplicitSourcesConfig()) {
                $removed = $this->container->calendarService()->reconcileCalendars([]);
            }

            return array_merge($this->status(false), [
                'ok' => true,
                'message' => $removed > 0
                    ? sprintf('No sources configured. Removed %d orphaned calendar(s) from the database.', $removed)
                    : 'No sources found in plugin config. Open the Sources tab, configure at least one source, click Save, then sync again.',
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

            return array_merge($this->status(false), [
                'ok' => true,
                'message' => $this->summarizeResults($results),
                'results' => array_map(static fn ($r) => $r->toArray(), $results),
            ]);
        } catch (\Throwable $e) {
            return array_merge($this->status(false), [
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

            return array_merge($this->status(false), [
                'ok' => true,
                'message' => 'Database rebuilt. ' . $this->summarizeResults($results),
                'results' => array_map(static fn ($r) => $r->toArray(), $results),
            ]);
        } catch (\Throwable $e) {
            return array_merge($this->status(false), [
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
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
     * Store an uploaded ICS/JSON calendar, register it as a local source, and import.
     *
     * @param array<string, mixed> $file $_FILES['calendar'] style entry
     * @param bool $allowLocalTemp Allow PSR-7 / CLI temp files (Admin Next API uploads)
     * @return array<string, mixed>
     */
    public function uploadCalendar(array $file, string $sourceName = '', bool $allowLocalTemp = false): array
    {
        if ($this->pluginConfigPath === null || $this->pluginConfigPath === '') {
            return array_merge($this->status(false), [
                'ok' => false,
                'message' => 'Plugin config path is not available; cannot register uploaded source.',
            ]);
        }

        try {
            $upload = $this->container->calendarUploadService();
            $stored = $upload->storeUploadedFile($file, $allowLocalTemp);

            $name = trim($sourceName);
            if ($name === '') {
                $name = pathinfo($stored['original_name'], PATHINFO_FILENAME) ?: 'Uploaded calendar';
            }

            $row = $upload->buildSourceRow(
                $name,
                $stored['relative_url'],
                'Uploaded via Admin (' . $stored['original_name'] . ')'
            );

            $writer = new PluginSourcesWriter($this->pluginConfigPath);
            $upsert = $writer->upsertByName($row);

            if ($this->refreshContainer instanceof \Closure) {
                $refreshed = ($this->refreshContainer)();
                if ($refreshed instanceof Container) {
                    $this->container = $refreshed;
                }
            }

            $result = $this->container->calendarService()->synchronizeOne(
                $this->container->sourceConfigs(),
                $upsert['key']
            );

            if ($result === null) {
                // Fallback: sync all after config reload race.
                $results = $this->container->calendarService()->synchronize(
                    $this->container->sourceConfigs(),
                    true
                );
                $message = ($upsert['created'] ? 'Source created and imported. ' : 'Source updated and imported. ')
                    . $this->summarizeResults($results);

                return array_merge($this->status(false), [
                    'ok' => true,
                    'message' => $message,
                    'source_key' => $upsert['key'],
                    'file' => $stored['relative_url'],
                    'results' => array_map(static fn ($r) => $r->toArray(), $results),
                ]);
            }

            $message = ($upsert['created'] ? 'Source created and imported. ' : 'Source updated and imported. ')
                . $this->summarizeResults([$result]);

            return array_merge($this->status(false), [
                'ok' => $result->status !== SyncStatus::Error,
                'message' => $message,
                'source_key' => $upsert['key'],
                'file' => $stored['relative_url'],
                'results' => [$result->toArray()],
            ]);
        } catch (\Throwable $e) {
            return array_merge($this->status(false), [
                'ok' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{ok: bool, message: string, calendars: list<array<string, mixed>>, event_count: int, source_count: int, pruned?: int}
     */
    public function status(bool $reconcile = true): array
    {
        $this->container->boot();

        $pruned = 0;
        if ($reconcile && $this->container->hasExplicitSourcesConfig()) {
            try {
                $pruned = $this->container->calendarService()->reconcileCalendars(
                    $this->container->sourceConfigs()
                );
            } catch (\Throwable) {
                $pruned = 0;
            }
        }

        $message = 'OK';
        if ($pruned > 0) {
            $message = sprintf('OK — removed %d orphaned calendar(s) no longer in Sources.', $pruned);
        }

        $calendars = $this->container->calendarService()->listCalendars();
        $errorCount = 0;
        foreach ($calendars as $calendar) {
            if ($calendar->status === SyncStatus::Error) {
                ++$errorCount;
            }
        }

        return [
            'ok' => true,
            'message' => $message,
            'calendars' => array_map(
                static fn ($c) => $c->toArray(),
                $calendars
            ),
            'event_count' => $this->container->calendarService()->eventCount(),
            'source_count' => count($this->container->sourceConfigs()),
            'error_count' => $errorCount,
            'pruned' => $pruned,
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
