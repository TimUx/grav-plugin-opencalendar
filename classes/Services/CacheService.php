<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Services;

/**
 * Cache facade that prefers Grav's cache when available, otherwise an in-memory store.
 */
final class CacheService
{
    /** @var array<string, array{value: mixed, expires: int}> */
    private array $memory = [];

    private readonly string $prefix;

    public function __construct(
        private readonly bool $enabled = true,
        private readonly int $defaultTtl = 3600,
        private readonly ?object $gravCache = null,
        string $prefix = 'opencalendar:',
    ) {
        $this->prefix = $prefix;
    }

    public function get(string $key): mixed
    {
        if (!$this->enabled) {
            return null;
        }

        $fullKey = $this->prefix . $key;

        if ($this->gravCache !== null && is_callable([$this->gravCache, 'fetch'])) {
            $value = $this->gravCache->fetch($fullKey);

            return $value === false ? null : $value;
        }

        $item = $this->memory[$fullKey] ?? null;
        if ($item === null) {
            return null;
        }

        if ($item['expires'] > 0 && $item['expires'] < time()) {
            unset($this->memory[$fullKey]);

            return null;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $fullKey = $this->prefix . $key;
        $ttl = $ttl ?? $this->defaultTtl;

        if ($this->gravCache !== null && is_callable([$this->gravCache, 'save'])) {
            $this->gravCache->save($fullKey, $value, $ttl > 0 ? $ttl : 0);

            return;
        }

        $this->memory[$fullKey] = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];
    }

    public function delete(string $key): void
    {
        $fullKey = $this->prefix . $key;

        if ($this->gravCache !== null && is_callable([$this->gravCache, 'delete'])) {
            $this->gravCache->delete($fullKey);
        }

        unset($this->memory[$fullKey]);
    }

    public function clear(): void
    {
        foreach (array_keys($this->memory) as $key) {
            if (str_starts_with($key, $this->prefix)) {
                unset($this->memory[$key]);
            }
        }
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}
