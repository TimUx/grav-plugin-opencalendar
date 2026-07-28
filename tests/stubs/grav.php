<?php

declare(strict_types=1);

/**
 * Minimal Grav stubs for static analysis.
 */

namespace Grav\Common;

class Plugin
{
    /** @var mixed */
    public $config;

    /** @var array<string, mixed> */
    public array $grav = [];

    public function isAdmin(): bool
    {
        return false;
    }

    /**
     * @param array<string, mixed> $events
     */
    public function enable(array $events): void
    {
    }
}

namespace RocketTheme\Toolbox\Event;

class Event implements \ArrayAccess
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}
