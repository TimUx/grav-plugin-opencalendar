<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\FetchResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Models\Event;

/**
 * Contract for calendar source adapters.
 *
 * New source types implement this interface and register in SourceFactory.
 */
interface SourceInterface
{
    public function getType(): string;

    /**
     * Fetch raw payload from the remote or local source.
     *
     * Implementations MUST honour conditional headers (If-None-Match / If-Modified-Since)
     * when etag / lastModified are provided and return FetchResult::notModified() when unchanged.
     */
    public function fetch(
        SourceConfig $config,
        ?string $etag = null,
        ?string $lastModified = null,
        ?string $contentHash = null,
    ): FetchResult;

    /**
     * Parse a previously fetched payload into normalized Event models.
     *
     * calendarId may be 0 during dry-run parsing; SyncService assigns the real id on persist.
     *
     * @return list<Event>
     */
    public function parse(string $payload, SourceConfig $config, int $calendarId = 0): array;

    public function supports(SourceConfig $config): bool;
}
