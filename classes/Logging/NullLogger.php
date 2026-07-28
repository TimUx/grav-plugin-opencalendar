<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Logging;

final class NullLogger implements LoggerInterface
{
    public function warning(string $message, array $context = []): void
    {
    }

    public function error(string $message, array $context = []): void
    {
    }
}
