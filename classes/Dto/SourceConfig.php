<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Dto;

use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Enum\SyncInterval;

/**
 * Immutable configuration for a single calendar source.
 */
final class SourceConfig
{
    /**
     * @param array{type?: string, username?: string, password?: string, token?: string} $auth
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly bool $enabled,
        public readonly SourceType $type,
        public readonly string $url,
        public readonly string $refresh,
        public readonly ?string $color,
        public readonly ?string $description,
        public readonly array $auth = ['type' => 'none'],
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row, ?string $fallbackKey = null): self
    {
        $name = trim((string) ($row['name'] ?? 'Unnamed'));
        $key = $fallbackKey ?? self::slugify($name);

        $type = SourceType::tryFrom((string) ($row['type'] ?? 'ics')) ?? SourceType::Ics;
        $auth = is_array($row['auth'] ?? null) ? $row['auth'] : ['type' => 'none'];

        $refresh = $row['refresh'] ?? 'inherit';
        if (is_int($refresh)) {
            $refresh = (string) $refresh;
        }
        $refresh = (string) $refresh;

        return new self(
            key: $key,
            name: $name !== '' ? $name : 'Unnamed',
            enabled: (bool) ($row['enabled'] ?? true),
            type: $type,
            url: trim((string) ($row['url'] ?? '')),
            refresh: $refresh !== '' ? $refresh : 'inherit',
            color: isset($row['color']) && $row['color'] !== '' ? (string) $row['color'] : null,
            description: isset($row['description']) && $row['description'] !== ''
                ? (string) $row['description']
                : null,
            auth: [
                'type' => (string) ($auth['type'] ?? 'none'),
                'username' => (string) ($auth['username'] ?? ''),
                'password' => (string) ($auth['password'] ?? ''),
                'token' => (string) ($auth['token'] ?? ''),
            ],
        );
    }

    public function resolveRefreshSeconds(SyncInterval $global): int
    {
        if ($this->refresh === 'inherit' || $this->refresh === '') {
            return $global->toSeconds();
        }

        return SyncInterval::fromConfig($this->refresh)->toSeconds();
    }

    public static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? 'source';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'source';
    }
}
