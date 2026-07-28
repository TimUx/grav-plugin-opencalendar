<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Enum;

enum SourceType: string
{
    case Ics = 'ics';
    case CalDav = 'caldav';
    case Json = 'json';
    case Local = 'local';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Ics => 'ICS',
            self::CalDav => 'CalDAV',
            self::Json => 'JSON',
            self::Local => 'Local',
        };
    }

    public function isImplemented(): bool
    {
        return $this === self::Ics;
    }
}
