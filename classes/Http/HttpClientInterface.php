<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Http;

/**
 * Minimal HTTP client contract for calendar source downloads.
 */
interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @param array{username?: string, password?: string, token?: string, type?: string} $auth
     */
    public function get(
        string $url,
        array $headers = [],
        array $auth = [],
        int $timeout = 30,
        bool $verifySsl = true,
        int $maxRedirects = 3,
        string $userAgent = 'OpenCalendar/1.0',
    ): HttpResponse;

    /**
     * Generic HTTP request used by CalDAV REPORT/PROPFIND and authenticated APIs.
     *
     * @param array<string, string> $headers
     * @param array{username?: string, password?: string, token?: string, type?: string} $auth
     */
    public function request(
        string $method,
        string $url,
        ?string $body = null,
        array $headers = [],
        array $auth = [],
        int $timeout = 30,
        bool $verifySsl = true,
        int $maxRedirects = 3,
        string $userAgent = 'OpenCalendar/1.0',
    ): HttpResponse;
}
