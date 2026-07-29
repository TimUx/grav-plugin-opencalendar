<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Events;

/**
 * Minimal event bus used by the sync pipeline (Grav or null implementation).
 */
interface EventDispatcherInterface
{
    /**
     * @param array<string, mixed> $arguments
     * @return object ArrayAccess-like event payload (mutated by listeners)
     */
    public function dispatch(string $eventName, array $arguments = []): object;
}
