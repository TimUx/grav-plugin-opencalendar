<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\FetchResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Models\Event;

/**
 * JSON HTTP API calendar source.
 */
final class JsonSource extends AbstractSource
{
    public function __construct(
        HttpClientInterface $http,
        private readonly JsonParser $parser,
        array $httpOptions = [],
    ) {
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
        if ($config->url === '') {
            throw new \InvalidArgumentException('JSON source URL must not be empty.');
        }

        if ($this->isLocalPath($config->url)) {
            return $this->fetchLocalFile($config->url, $contentHash);
        }

        $result = $this->fetchHttp($config, $etag, $lastModified, [
            'Accept' => 'application/json',
        ]);

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

    /**
     * @return list<Event>
     */
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

    private function fetchLocalFile(string $path, ?string $contentHash): FetchResult
    {
        $file = str_starts_with($path, 'file://') ? substr($path, 7) : $path;
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException('Local JSON file not readable: ' . $file);
        }

        $body = file_get_contents($file);
        if ($body === false) {
            throw new \RuntimeException('Unable to read local JSON file: ' . $file);
        }

        $hash = hash('sha256', $body);
        $mtime = gmdate('D, d M Y H:i:s', (int) filemtime($file)) . ' GMT';
        if ($contentHash !== null && $contentHash !== '' && $hash === $contentHash) {
            return FetchResult::notModified(200, null, $mtime, $hash);
        }

        return new FetchResult($body, 200, null, $mtime, false, $hash);
    }
}
