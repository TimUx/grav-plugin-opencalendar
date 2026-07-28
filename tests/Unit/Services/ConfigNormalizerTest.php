<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Services;

use Grav\Plugin\OpenCalendar\Services\ConfigNormalizer;
use PHPUnit\Framework\TestCase;

final class ConfigNormalizerTest extends TestCase
{
    public function testConvertsNestedObjectsWithToArray(): void
    {
        $object = new class {
            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return [
                    'sources' => [
                        [
                            'name' => 'Feuerwehr Termine',
                            'enabled' => 1,
                            'type' => 'ics',
                            'url' => 'https://example.com/cal.ics',
                        ],
                    ],
                ];
            }
        };

        $normalized = ConfigNormalizer::toArray($object);

        self::assertSame('Feuerwehr Termine', $normalized['sources'][0]['name']);
        self::assertSame(1, $normalized['sources'][0]['enabled']);
    }

    public function testEmptyNonArrayBecomesEmptyArray(): void
    {
        self::assertSame([], ConfigNormalizer::toArray(null));
        self::assertSame([], ConfigNormalizer::toArray('nope'));
    }
}
