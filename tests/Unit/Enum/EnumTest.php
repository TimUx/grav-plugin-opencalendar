<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Enum;

use Grav\Plugin\OpenCalendar\Enum\CleanupPolicy;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Enum\SyncInterval;
use PHPUnit\Framework\TestCase;

final class EnumTest extends TestCase
{
    public function testSyncIntervalSeconds(): void
    {
        self::assertSame(300, SyncInterval::Minutes5->toSeconds());
        self::assertSame(86400, SyncInterval::Daily->toSeconds());
        self::assertSame(SyncInterval::Minutes15, SyncInterval::fromConfig(15));
        self::assertSame(SyncInterval::Daily, SyncInterval::fromConfig('daily'));
    }

    public function testCleanupPolicy(): void
    {
        self::assertNull(CleanupPolicy::Never->retentionDays());
        self::assertSame(0, CleanupPolicy::Immediate->retentionDays());
        self::assertSame(30, CleanupPolicy::Days30->retentionDays());
        self::assertSame(CleanupPolicy::Days7, CleanupPolicy::fromConfig(7));
    }

    public function testSourceType(): void
    {
        self::assertTrue(SourceType::Ics->isImplemented());
        self::assertTrue(SourceType::CalDav->isImplemented());
        self::assertTrue(SourceType::Json->isImplemented());
        self::assertTrue(SourceType::Local->isImplemented());
        self::assertContains('ics', SourceType::values());
    }
}
