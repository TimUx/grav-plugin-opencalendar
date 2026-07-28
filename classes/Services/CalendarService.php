<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Services;

use Grav\Plugin\OpenCalendar\Dto\EventQuery;
use Grav\Plugin\OpenCalendar\Dto\PaginatedResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Models\Calendar;
use Grav\Plugin\OpenCalendar\Models\Event;
use Grav\Plugin\OpenCalendar\Storage\CalendarRepository;
use Grav\Plugin\OpenCalendar\Storage\EventRepository;
use Grav\Plugin\OpenCalendar\Sync\SyncService;

/**
 * Primary application service for reading calendars/events and triggering sync.
 */
final class CalendarService
{
    public function __construct(
        private readonly EventRepository $events,
        private readonly CalendarRepository $calendars,
        private readonly SyncService $sync,
        private readonly CacheService $cache,
        private readonly array $config = [],
    ) {
    }

    /**
     * @return PaginatedResult<Event>
     */
    public function queryEvents(EventQuery $query): PaginatedResult
    {
        $cacheKey = 'events:' . hash('sha256', serialize([
            $query->from?->format(\DateTimeInterface::ATOM),
            $query->to?->format(\DateTimeInterface::ATOM),
            $query->calendarKeys,
            $query->categories,
            $query->search,
            $query->sort,
            $query->limit,
            $query->offset,
            $query->futureOnly,
            $query->includeExpired,
            $query->month,
            $query->year,
        ]));

        /** @var PaginatedResult<Event>|null $cached */
        $cached = $this->cache->get($cacheKey);
        if ($cached instanceof PaginatedResult) {
            return $cached;
        }

        $result = $this->events->search($query);
        $this->cache->set($cacheKey, $result);

        return $result;
    }

    public function getEvent(int $id): ?Event
    {
        return $this->events->findById($id);
    }

    public function getEventByUid(string $uid, ?string $calendarKey = null): ?Event
    {
        return $this->events->findByUid($uid, $calendarKey);
    }

    /**
     * @return list<Calendar>
     */
    public function listCalendars(): array
    {
        return $this->calendars->all();
    }

    /**
     * @return list<string>
     */
    public function listCategories(?array $calendarKeys = null): array
    {
        return $this->events->distinctCategories($calendarKeys);
    }

    /**
     * @param list<SourceConfig> $sources
     * @return list<\Grav\Plugin\OpenCalendar\Dto\SyncResult>
     */
    public function synchronize(array $sources, bool $force = false): array
    {
        $results = $this->sync->syncAll($sources, $force);
        $this->cache->clear();

        return $results;
    }

    /**
     * @param list<SourceConfig> $sources
     */
    public function synchronizeOne(array $sources, string $sourceKey): ?\Grav\Plugin\OpenCalendar\Dto\SyncResult
    {
        $result = $this->sync->syncOne($sources, $sourceKey, true);
        $this->cache->clear();

        return $result;
    }

    /**
     * @param list<SourceConfig> $sources
     * @return list<\Grav\Plugin\OpenCalendar\Dto\SyncResult>
     */
    public function rebuild(array $sources): array
    {
        $results = $this->sync->rebuild($sources);
        $this->cache->clear();

        return $results;
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }

    /**
     * Drop calendar rows that no longer exist in the given source configuration.
     *
     * @param list<SourceConfig> $sources
     */
    public function reconcileCalendars(array $sources): int
    {
        $removed = $this->sync->reconcileCalendars($sources);
        if ($removed > 0) {
            $this->cache->clear();
        }

        return $removed;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    public function eventCount(): int
    {
        return $this->events->countActive();
    }
}
