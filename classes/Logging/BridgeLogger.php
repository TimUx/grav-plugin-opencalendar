<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Logging;

/**
 * Adapts Grav's Monolog/psr logger (or any object with warning/error methods).
 */
final class BridgeLogger implements LoggerInterface
{
    public function __construct(private readonly object $logger)
    {
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        $interpolated = $this->interpolate($message, $context);

        if (is_callable([$this->logger, $level])) {
            $this->logger->{$level}($interpolated);

            return;
        }

        if (is_callable([$this->logger, 'log'])) {
            $this->logger->log($level, $interpolated);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }
}
