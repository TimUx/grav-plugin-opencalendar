<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Events;

/**
 * No-op dispatcher for unit tests and CLI contexts without Grav.
 */
final class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(string $eventName, array $arguments = []): object
    {
        return new class ($arguments) implements \ArrayAccess {
            /** @param array<string, mixed> $data */
            public function __construct(private array $data)
            {
            }

            public function offsetExists(mixed $offset): bool
            {
                return is_string($offset) && array_key_exists($offset, $this->data);
            }

            public function offsetGet(mixed $offset): mixed
            {
                return is_string($offset) ? ($this->data[$offset] ?? null) : null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                if (!is_string($offset)) {
                    return;
                }
                $this->data[$offset] = $value;
            }

            public function offsetUnset(mixed $offset): void
            {
                if (is_string($offset)) {
                    unset($this->data[$offset]);
                }
            }
        };
    }
}
