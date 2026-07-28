<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Enum;

enum CleanupPolicy: string
{
    case Never = 'never';
    case Immediate = 'immediate';
    case Days1 = '1';
    case Days7 = '7';
    case Days30 = '30';
    case Days90 = '90';

    public function retentionDays(): ?int
    {
        return match ($this) {
            self::Never => null,
            self::Immediate => 0,
            self::Days1 => 1,
            self::Days7 => 7,
            self::Days30 => 30,
            self::Days90 => 90,
        };
    }

    public static function fromConfig(int|string $value): self
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return self::tryFrom($value) ?? self::Days30;
    }
}
