<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\FetchResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Models\Event;

/**
 * Local filesystem calendar source for ICS and JSON files under a configured base path.
 */
final class LocalSource extends AbstractSource
{
    public function __construct(
        HttpClientInterface $http,
        private readonly string $basePath = '',
        private readonly ?IcsParser $icsParser = null,
        private readonly ?JsonParser $jsonParser = null,
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
        $file = $this->resolvePath($config->url);
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException('Local calendar file not readable: ' . $file);
        }

        $body = file_get_contents($file);
        if ($body === false) {
            throw new \RuntimeException('Unable to read local calendar file: ' . $file);
        }

        $hash = hash('sha256', $body);
        $mtime = gmdate('D, d M Y H:i:s', (int) filemtime($file)) . ' GMT';
        if ($contentHash !== null && $contentHash !== '' && $hash === $contentHash) {
            return FetchResult::notModified(200, null, $mtime, $hash);
        }

        return new FetchResult($body, 200, null, $mtime, false, $hash);
    }

    /**
     * @return list<Event>
     */
    public function parse(string $payload, SourceConfig $config, int $calendarId = 0): array
    {
        $format = $this->detectFormat($config->url, $payload);
        if ($format === 'json') {
            if (!$this->jsonParser instanceof JsonParser) {
                throw new \RuntimeException('JSON parser is not available for local source.');
            }

            return $this->jsonParser->parse($payload, $config, $calendarId);
        }

        if (!$this->icsParser instanceof IcsParser) {
            throw new \RuntimeException('ICS parser is not available for local source.');
        }

        return $this->icsParser->parse($payload, $config, $calendarId);
    }

    private function resolvePath(string $url): string
    {
        $path = trim($url);
        if ($path === '') {
            throw new \InvalidArgumentException('Local source path must not be empty.');
        }

        if (str_starts_with($path, 'file://')) {
            $path = substr($path, 7);
        }

        if ($this->isAbsolutePath($path)) {
            $resolved = realpath($path) ?: $path;
        } else {
            $base = rtrim($this->basePath, "/\\");
            if ($base === '') {
                throw new \RuntimeException('Local source base path is not configured.');
            }
            $candidate = $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), "/\\");
            $realBase = realpath($base) ?: $base;
            $parent = dirname($candidate);
            $realParent = realpath($parent);
            if ($realParent === false) {
                throw new \RuntimeException('Local calendar path is not accessible: ' . $candidate);
            }
            $normalizedBase = rtrim(str_replace('\\', '/', $realBase), '/') . '/';
            $normalizedParent = rtrim(str_replace('\\', '/', $realParent), '/') . '/';
            if (!str_starts_with($normalizedParent, $normalizedBase) && rtrim($normalizedParent, '/') !== rtrim($normalizedBase, '/')) {
                throw new \RuntimeException('Local source path escapes the configured base directory.');
            }
            $resolved = $realParent . DIRECTORY_SEPARATOR . basename($candidate);
        }

        $this->assertWithinBase($resolved);

        return $resolved;
    }

    private function assertWithinBase(string $resolved): void
    {
        $base = $this->basePath !== '' ? (realpath($this->basePath) ?: $this->basePath) : '';
        if ($base === '') {
            return;
        }

        $normalizedBase = rtrim(str_replace('\\', '/', $base), '/') . '/';
        $normalizedFile = str_replace('\\', '/', $resolved);
        if (!str_starts_with($normalizedFile, $normalizedBase) && $normalizedFile !== rtrim($normalizedBase, '/')) {
            throw new \RuntimeException('Local source path escapes the configured base directory.');
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':' && ($path[2] === '\\' || $path[2] === '/'));
    }

    private function detectFormat(string $path, string $payload): string
    {
        $lower = strtolower($path);
        if (str_ends_with($lower, '.json')) {
            return 'json';
        }
        if (str_ends_with($lower, '.ics') || str_ends_with($lower, '.ical')) {
            return 'ics';
        }

        $trimmed = ltrim($payload);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            return 'json';
        }

        return 'ics';
    }
}
