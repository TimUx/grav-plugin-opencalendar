<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\FetchResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;

abstract class AbstractSource implements SourceInterface
{
    public function __construct(
        protected readonly HttpClientInterface $http,
        protected readonly array $httpOptions = [],
    ) {
    }

    public function supports(SourceConfig $config): bool
    {
        return $config->type->value === $this->getType();
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    protected function fetchHttp(
        SourceConfig $config,
        ?string $etag,
        ?string $lastModified,
        array $extraHeaders = [],
    ): FetchResult {
        $headers = $extraHeaders;
        if ($etag !== null && $etag !== '') {
            $headers['If-None-Match'] = $etag;
        }
        if ($lastModified !== null && $lastModified !== '') {
            $headers['If-Modified-Since'] = $lastModified;
        }

        $response = $this->http->get(
            $config->url,
            $headers,
            $config->auth,
            (int) ($this->httpOptions['timeout'] ?? 30),
            (bool) ($this->httpOptions['verify_ssl'] ?? true),
            (int) ($this->httpOptions['max_redirects'] ?? 3),
            (string) ($this->httpOptions['user_agent'] ?? 'OpenCalendar/1.0 Grav Plugin'),
        );

        if ($response->statusCode === 304) {
            return FetchResult::notModified(
                304,
                $response->header('ETag') ?? $etag,
                $response->header('Last-Modified') ?? $lastModified,
            );
        }

        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new \RuntimeException(
                sprintf('Source "%s" returned HTTP %d', $config->name, $response->statusCode),
                $response->statusCode
            );
        }

        return FetchResult::fromBody(
            $response->body,
            $response->statusCode,
            $response->header('ETag'),
            $response->header('Last-Modified'),
        );
    }
}
