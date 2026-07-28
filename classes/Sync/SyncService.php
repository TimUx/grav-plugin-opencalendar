<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Sync;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Dto\SyncResult;
use Grav\Plugin\OpenCalendar\Enum\CleanupPolicy;
use Grav\Plugin\OpenCalendar\Enum\SyncInterval;
use Grav\Plugin\OpenCalendar\Enum\SyncStatus;
use Grav\Plugin\OpenCalendar\Storage\CalendarRepository;
use Grav\Plugin\OpenCalendar\Storage\Database;
use Grav\Plugin\OpenCalendar\Storage\EventRepository;
use Grav\Plugin\OpenCalendar\Logging\LoggerInterface;
use Grav\Plugin\OpenCalendar\Logging\NullLogger;

/**
 * Orchestrates multi-source synchronization, cleanup, and maintenance.
 */
final class SyncService
{
    public function __construct(
        private readonly SyncJob $job,
        private readonly CalendarRepository $calendars,
        private readonly EventRepository $events,
        private readonly Database $db,
        private readonly SyncInterval $interval = SyncInterval::Minutes15,
        private readonly CleanupPolicy $cleanup = CleanupPolicy::Days30,
        private readonly bool $vacuumOnCleanup = false,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->job->setGlobalIntervalSeconds($this->interval->toSeconds());
    }

    /**
     * @param list<SourceConfig> $sources
     * @return list<SyncResult>
     */
    public function syncAll(array $sources, bool $force = false): array
    {
        $results = [];
        $keepKeys = [];

        foreach ($sources as $source) {
            $keepKeys[] = $source->key;
            try {
                $results[] = $this->job->run($source, $force);
            } catch (\Throwable $e) {
                $this->logger->warning('OpenCalendar unexpected sync error for {source}: {message}', [
                    'source' => $source->key,
                    'message' => $e->getMessage(),
                ]);
                $results[] = new SyncResult(
                    sourceKey: $source->key,
                    status: SyncStatus::Error,
                    error: $e->getMessage(),
                );
            }
        }

        try {
            $this->calendars->pruneToKeys($keepKeys);
        } catch (\Throwable $e) {
            $this->logger->warning('OpenCalendar failed pruning calendars: {message}', [
                'message' => $e->getMessage(),
            ]);
        }

        $this->runCleanup();

        return $results;
    }

    /**
     * @param list<SourceConfig> $sources
     */
    public function syncOne(array $sources, string $sourceKey, bool $force = true): ?SyncResult
    {
        foreach ($sources as $source) {
            if ($source->key === $sourceKey) {
                $result = $this->job->run($source, $force);
                try {
                    $this->calendars->pruneToKeys(array_map(
                        static fn (SourceConfig $item): string => $item->key,
                        $sources
                    ));
                } catch (\Throwable $e) {
                    $this->logger->warning('OpenCalendar failed pruning calendars: {message}', [
                        'message' => $e->getMessage(),
                    ]);
                }
                $this->runCleanup();

                return $result;
            }
        }

        return null;
    }

    /**
     * Remove DB calendars that are no longer present in the configured source list.
     *
     * @param list<SourceConfig> $sources
     */
    public function reconcileCalendars(array $sources): int
    {
        $keepKeys = array_map(
            static fn (SourceConfig $source): string => $source->key,
            $sources
        );

        return $this->calendars->pruneToKeys($keepKeys);
    }

    public function runCleanup(): int
    {
        $days = $this->cleanup->retentionDays();
        if ($days === null) {
            return 0;
        }

        try {
            $removed = $this->events->purgeExpired($days);
            if ($removed > 0 && $this->vacuumOnCleanup) {
                $this->db->vacuum();
            }

            return $removed;
        } catch (\Throwable $e) {
            $this->logger->warning('OpenCalendar cleanup failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @param list<SourceConfig> $sources
     * @return list<SyncResult>
     */
    public function rebuild(array $sources): array
    {
        $this->events->purgeAll();

        return $this->syncAll($sources, true);
    }
}
