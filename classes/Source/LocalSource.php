<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\FetchResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Models\Event;

/**
 * Local file source stub — architecture placeholder for dedicated local adapters.
 *
 * Note: IcsSource already supports local file paths for ICS. This class is reserved
 * for non-ICS local formats (CSV, JSON files, etc.).
 */
final class LocalSource extends AbstractSource
{
    public function __construct(
        HttpClientInterface $http,
        private readonly string $basePath = '',
        array $httpOptions = [],
    ) {
        parent::__construct($http, $httpOptions);
    }

    public function getType(): string
    {
        return SourceType::Local->value;
    }

    public function fetch(
        SourceConfig $config,
        ?string $etag = null,
        ?string $lastModified = null,
        ?string $contentHash = null,
    ): FetchResult {
        throw new \RuntimeException(
            'Local source type is not implemented yet. Base path reserved: ' . $this->basePath
            . '. Point an ICS source at a local .ics file path, or contribute LocalSource.'
        );
    }

    /**
     * @return list<Event>
     */
    public function parse(string $payload, SourceConfig $config, int $calendarId = 0): array
    {
        throw new \RuntimeException('Local source type is not implemented yet.');
    }
}
