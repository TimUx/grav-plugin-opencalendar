<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Enum;

enum SyncInterval: string
{
    case Minutes5 = '5';
    case Minutes10 = '10';
    case Minutes15 = '15';
    case Minutes30 = '30';
    case Minutes60 = '60';
    case Daily = 'daily';

    public function toSeconds(): int
    {
        return match ($this) {
            self::Minutes5 => 300,
            self::Minutes10 => 600,
            self::Minutes15 => 900,
            self::Minutes30 => 1800,
            self::Minutes60 => 3600,
            self::Daily => 86400,
        };
    }

    public static function fromConfig(int|string $value): self
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return self::tryFrom($value) ?? self::Minutes15;
    }
}
