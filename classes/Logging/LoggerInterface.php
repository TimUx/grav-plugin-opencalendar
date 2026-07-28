<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Logging;

/**
 * Minimal logger contract — avoids vendoring psr/log (conflicts with Grav's older psr/log).
 */
interface LoggerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void;
}
