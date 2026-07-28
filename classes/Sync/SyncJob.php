<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Sync;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Dto\SyncResult;
use Grav\Plugin\OpenCalendar\Enum\SyncInterval;
use Grav\Plugin\OpenCalendar\Enum\SyncStatus;
use Grav\Plugin\OpenCalendar\Source\SourceFactory;
use Grav\Plugin\OpenCalendar\Storage\CalendarRepository;
use Grav\Plugin\OpenCalendar\Storage\EventRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Synchronizes a single calendar source into SQLite.
 */
final class SyncJob
{
    public function __construct(
        private readonly SourceFactory $sources,
        private readonly CalendarRepository $calendars,
        private readonly EventRepository $events,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function run(SourceConfig $config, bool $force = false): SyncResult
    {
        $started = microtime(true);
        $startedAt = $this->now();
        $calendar = $this->calendars->upsertFromConfig($config);

        if (!$config->enabled) {
            return new SyncResult(
                sourceKey: $config->key,
                status: SyncStatus::Skipped,
                durationMs: $this->elapsedMs($started),
                error: 'Source disabled',
                skippedParse: true,
            );
        }

        if (!$force && !$this->isDue($config, $calendar->lastSyncAt)) {
            return new SyncResult(
                sourceKey: $config->key,
                status: SyncStatus::Skipped,
                durationMs: $this->elapsedMs($started),
                etag: $calendar->etag,
                lastModified: $calendar->lastModified,
                contentHash: $calendar->contentHash,
                skippedParse: true,
            );
        }

        try {
            if (!$config->type->isImplemented()) {
                throw new \RuntimeException(
                    sprintf('Source type "%s" is not implemented yet.', $config->type->value)
                );
            }

            $adapter = $this->sources->forConfig($config);
            $fetch = $adapter->fetch(
                $config,
                $calendar->etag,
                $calendar->lastModified,
                $calendar->contentHash,
            );

            if ($fetch->notModified) {
                $duration = $this->elapsedMs($started);
                $this->calendars->updateSyncState(
                    calendarId: (int) $calendar->id,
                    status: SyncStatus::Skipped,
                    durationMs: $duration,
                    imported: 0,
                    updated: 0,
                    deleted: 0,
                    httpStatus: $fetch->httpStatus,
                    etag: $fetch->etag,
                    lastModified: $fetch->lastModified,
                    contentHash: $fetch->contentHash !== '' ? $fetch->contentHash : $calendar->contentHash,
                    error: null,
                    success: true,
                );
                $this->calendars->logSync(
                    (int) $calendar->id,
                    SyncStatus::Skipped,
                    $startedAt,
                    $this->now(),
                    0,
                    0,
                    0,
                    $fetch->httpStatus,
                    $duration,
                    'Not modified'
                );

                return new SyncResult(
                    sourceKey: $config->key,
                    status: SyncStatus::Skipped,
                    durationMs: $duration,
                    httpStatus: $fetch->httpStatus,
                    etag: $fetch->etag,
                    lastModified: $fetch->lastModified,
                    contentHash: $fetch->contentHash !== '' ? $fetch->contentHash : $calendar->contentHash,
                    skippedParse: true,
                );
            }

            $parsed = $adapter->parse($fetch->body, $config, (int) $calendar->id);
            $stats = $this->events->syncCalendarEvents((int) $calendar->id, $config->name, $parsed);
            $duration = $this->elapsedMs($started);

            $this->calendars->updateSyncState(
                calendarId: (int) $calendar->id,
                status: SyncStatus::Success,
                durationMs: $duration,
                imported: $stats['imported'],
                updated: $stats['updated'],
                deleted: $stats['deleted'],
                httpStatus: $fetch->httpStatus,
                etag: $fetch->etag,
                lastModified: $fetch->lastModified,
                contentHash: $fetch->contentHash,
                error: null,
                success: true,
            );
            $this->calendars->logSync(
                (int) $calendar->id,
                SyncStatus::Success,
                $startedAt,
                $this->now(),
                $stats['imported'],
                $stats['updated'],
                $stats['deleted'],
                $fetch->httpStatus,
                $duration,
                null
            );

            return new SyncResult(
                sourceKey: $config->key,
                status: SyncStatus::Success,
                imported: $stats['imported'],
                updated: $stats['updated'],
                deleted: $stats['deleted'],
                durationMs: $duration,
                httpStatus: $fetch->httpStatus,
                etag: $fetch->etag,
                lastModified: $fetch->lastModified,
                contentHash: $fetch->contentHash,
            );
        } catch (\Throwable $e) {
            $duration = $this->elapsedMs($started);
            $this->logger->warning('OpenCalendar sync failed for {source}: {message}', [
                'source' => $config->key,
                'message' => $e->getMessage(),
            ]);

            $httpStatus = $e->getCode() > 0 ? (int) $e->getCode() : null;
            $this->calendars->updateSyncState(
                calendarId: (int) $calendar->id,
                status: SyncStatus::Error,
                durationMs: $duration,
                imported: 0,
                updated: 0,
                deleted: 0,
                httpStatus: $httpStatus,
                etag: null,
                lastModified: null,
                contentHash: null,
                error: $e->getMessage(),
                success: false,
            );
            $this->calendars->logSync(
                (int) $calendar->id,
                SyncStatus::Error,
                $startedAt,
                $this->now(),
                0,
                0,
                0,
                $httpStatus,
                $duration,
                $e->getMessage()
            );

            return new SyncResult(
                sourceKey: $config->key,
                status: SyncStatus::Error,
                durationMs: $duration,
                httpStatus: $httpStatus,
                error: $e->getMessage(),
            );
        }
    }

    private function isDue(SourceConfig $config, ?\DateTimeImmutable $lastSyncAt): bool
    {
        if ($lastSyncAt === null) {
            return true;
        }

        $interval = $config->refresh === 'inherit' || $config->refresh === ''
            ? SyncInterval::Minutes15
            : SyncInterval::fromConfig($config->refresh);

        // Global interval is applied by SyncService; per-source refresh still respected here.
        $seconds = $config->refresh === 'inherit'
            ? ($this->globalIntervalSeconds ?? $interval->toSeconds())
            : $interval->toSeconds();

        $dueAt = $lastSyncAt->getTimestamp() + $seconds;

        return time() >= $dueAt;
    }

    private ?int $globalIntervalSeconds = null;

    public function setGlobalIntervalSeconds(int $seconds): void
    {
        $this->globalIntervalSeconds = $seconds;
    }

    private function elapsedMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format(\DateTimeInterface::ATOM);
    }
}
