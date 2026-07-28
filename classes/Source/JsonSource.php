<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\FetchResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Models\Event;

/**
 * JSON API source stub — architecture placeholder for a future implementation.
 */
final class JsonSource extends AbstractSource
{
    public function __construct(HttpClientInterface $http, array $httpOptions = [])
    {
        parent::__construct($http, $httpOptions);
    }

    public function getType(): string
    {
        return SourceType::Json->value;
    }

    public function fetch(
        SourceConfig $config,
        ?string $etag = null,
        ?string $lastModified = null,
        ?string $contentHash = null,
    ): FetchResult {
        throw new \RuntimeException(
            'JSON source type is not implemented yet. Use type "ics" or contribute JsonSource.'
        );
    }

    /**
     * @return list<Event>
     */
    public function parse(string $payload, SourceConfig $config, int $calendarId = 0): array
    {
        throw new \RuntimeException('JSON source type is not implemented yet.');
    }
}
