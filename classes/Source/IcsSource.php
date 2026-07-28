<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\FetchResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Models\Event;

final class IcsSource extends AbstractSource
{
    public function __construct(
        HttpClientInterface $http,
        private readonly IcsParser $parser,
        array $httpOptions = [],
    ) {
        parent::__construct($http, $httpOptions);
    }

    public function getType(): string
    {
        return SourceType::Ics->value;
    }

    public function fetch(
        SourceConfig $config,
        ?string $etag = null,
        ?string $lastModified = null,
        ?string $contentHash = null,
    ): FetchResult {
        if ($config->url === '') {
            throw new \InvalidArgumentException('ICS source URL must not be empty.');
        }

        // Support local file paths for testing and offline feeds.
        if ($this->isLocalPath($config->url)) {
            return $this->fetchLocal($config->url, $contentHash);
        }

        $result = $this->fetchHttp($config, $etag, $lastModified);

        if (
            !$result->notModified && $contentHash !== null && $contentHash !== ''
            && $result->contentHash === $contentHash
        ) {
            return FetchResult::notModified(
                $result->httpStatus,
                $result->etag ?? $etag,
                $result->lastModified ?? $lastModified,
                $contentHash,
            );
        }

        return $result;
    }

    public function parse(string $payload, SourceConfig $config, int $calendarId = 0): array
    {
        return $this->parser->parse($payload, $config, $calendarId);
    }

    private function isLocalPath(string $url): bool
    {
        if (str_starts_with($url, 'file://')) {
            return true;
        }

        return !str_contains($url, '://') && is_file($url);
    }

    private function fetchLocal(string $path, ?string $contentHash): FetchResult
    {
        $file = str_starts_with($path, 'file://') ? substr($path, 7) : $path;
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException('Local ICS file not readable: ' . $file);
        }

        $body = file_get_contents($file);
        if ($body === false) {
            throw new \RuntimeException('Unable to read local ICS file: ' . $file);
        }

        $hash = hash('sha256', $body);
        $mtime = gmdate('D, d M Y H:i:s', (int) filemtime($file)) . ' GMT';

        if ($contentHash !== null && $contentHash !== '' && $hash === $contentHash) {
            return FetchResult::notModified(200, null, $mtime, $hash);
        }

        return new FetchResult($body, 200, null, $mtime, false, $hash);
    }
}
