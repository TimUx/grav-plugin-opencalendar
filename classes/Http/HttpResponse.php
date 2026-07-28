<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Http;

final class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    public function header(string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $lower) {
                return $value;
            }
        }

        return null;
    }
}
