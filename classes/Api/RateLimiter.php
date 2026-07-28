<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Api;

/**
 * Simple in-process rate limiter for API requests.
 */
final class RateLimiter
{
    /** @var array<string, list<int>> */
    private static array $hits = [];

    public function __construct(
        private readonly bool $enabled = true,
        private readonly int $maxRequests = 60,
        private readonly int $perMinutes = 1,
    ) {
    }

    public function allow(string $key): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $now = time();
        $window = max(1, $this->perMinutes) * 60;
        $bucket = self::$hits[$key] ?? [];
        $bucket = array_values(array_filter($bucket, static fn (int $ts): bool => ($now - $ts) < $window));

        if (count($bucket) >= $this->maxRequests) {
            self::$hits[$key] = $bucket;

            return false;
        }

        $bucket[] = $now;
        self::$hits[$key] = $bucket;

        return true;
    }
}
