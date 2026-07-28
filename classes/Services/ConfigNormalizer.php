<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Services;

/**
 * Normalizes Grav Config/Data objects into plain PHP arrays.
 */
final class ConfigNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::normalizeValue($item);
            }

            return $out;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            /** @var mixed $exported */
            $exported = $value->toArray();

            return self::toArray($exported);
        }

        if ($value instanceof \JsonSerializable) {
            $exported = $value->jsonSerialize();

            return is_array($exported) ? self::toArray($exported) : [];
        }

        if ($value instanceof \Traversable) {
            return self::toArray(iterator_to_array($value));
        }

        return [];
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            $asArray = self::toArray($value);
            // Distinguish list/object-like structures: if conversion yielded data, prefer array form
            // for nested config sections; scalars/objects without toArray stay as-is when empty.
            if ($asArray !== [] || is_array($value) || (is_object($value) && method_exists($value, 'toArray'))) {
                return $asArray;
            }
        }

        return $value;
    }
}
