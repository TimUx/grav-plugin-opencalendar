<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Sync;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Dto\SyncResult;
use Grav\Plugin\OpenCalendar\Enum\SyncInterval;
use Grav\Plugin\OpenCalendar\Enum\SyncStatus;
use Grav\Plugin\OpenCalendar\Events\EventDispatcherInterface;
use Grav\Plugin\OpenCalendar\Events\NullEventDispatcher;
use Grav\Plugin\OpenCalendar\Events\PipelineEvents;
use Grav\Plugin\OpenCalendar\Models\Event;
use Grav\Plugin\OpenCalendar\Source\SourceFactory;
use Grav\Plugin\OpenCalendar\Storage\CalendarRepository;
use Grav\Plugin\OpenCalendar\Storage\EventRepository;
use Grav\Plugin\OpenCalendar\Logging\LoggerInterface;
use Grav\Plugin\OpenCalendar\Logging\NullLogger;

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
        private readonly EventDispatcherInterface $dispatcher = new NullEventDispatcher(),
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
            $parsed = $this->filterEventsThroughPipeline($parsed, $config, $calendar, $fetch);

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

            $result = new SyncResult(
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
            $this->dispatchSourceCompleted($result, $config, $calendar);

            return $result;
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

            $result = new SyncResult(
                sourceKey: $config->key,
                status: SyncStatus::Error,
                durationMs: $duration,
                httpStatus: $httpStatus,
                error: $e->getMessage(),
            );
            $this->dispatchSourceCompleted($result, $config, $calendar);

            return $result;
        }
    }

    /**
     * @param list<Event> $parsed
     * @return list<Event>
     */
    private function filterEventsThroughPipeline(
        array $parsed,
        SourceConfig $config,
        mixed $calendar,
        mixed $fetch,
    ): array {
        $parsedEvent = $this->dispatcher->dispatch(PipelineEvents::EVENTS_PARSED, [
            'events' => $parsed,
            'source' => $config,
            'calendar' => $calendar,
            'fetch' => $fetch,
        ]);
        $parsed = $this->extractEvents($this->eventArgument($parsedEvent, 'events', $parsed));

        $beforePersist = $this->dispatcher->dispatch(PipelineEvents::EVENTS_BEFORE_PERSIST, [
            'events' => $parsed,
            'source' => $config,
            'calendar' => $calendar,
        ]);

        return $this->extractEvents($this->eventArgument($beforePersist, 'events', $parsed));
    }

    /**
     * @param list<Event> $fallback
     * @return list<Event>|mixed
     */
    private function eventArgument(object $event, string $key, array $fallback): mixed
    {
        if ($event instanceof \ArrayAccess) {
            return $event->offsetExists($key) ? $event->offsetGet($key) : $fallback;
        }

        return $fallback;
    }

    /**
     * @return list<Event>
     */
    private function extractEvents(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $events = [];
        foreach ($value as $item) {
            if ($item instanceof Event) {
                $events[] = $item;
            }
        }

        return $events;
    }

    private function dispatchSourceCompleted(SyncResult $result, SourceConfig $config, mixed $calendar): void
    {
        $this->dispatcher->dispatch(PipelineEvents::SYNC_SOURCE_COMPLETED, [
            'result' => $result,
            'source' => $config,
            'calendar' => $calendar,
        ]);
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
