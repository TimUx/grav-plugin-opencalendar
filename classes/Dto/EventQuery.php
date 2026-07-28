<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Dto;

/**
 * Criteria for querying events from storage.
 */
final class EventQuery
{
    /**
     * @param list<string> $calendarKeys
     * @param list<string> $categories
     */
    public function __construct(
        public readonly ?\DateTimeImmutable $from = null,
        public readonly ?\DateTimeImmutable $to = null,
        public readonly array $calendarKeys = [],
        public readonly array $categories = [],
        public readonly ?string $search = null,
        public readonly string $sort = 'asc',
        public readonly int $limit = 50,
        public readonly int $offset = 0,
        public readonly bool $includeDeleted = false,
        public readonly bool $futureOnly = false,
        public readonly bool $includeExpired = true,
        public readonly ?int $month = null,
        public readonly ?int $year = null,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function fromRequest(array $params, int $defaultLimit = 50, int $maxLimit = 200): self
    {
        $limit = isset($params['limit']) ? (int) $params['limit'] : $defaultLimit;
        $limit = max(1, min($limit, $maxLimit));
        $offset = max(0, (int) ($params['offset'] ?? 0));

        $sort = strtolower((string) ($params['sort'] ?? 'asc'));
        if (!in_array($sort, ['asc', 'desc'], true)) {
            $sort = 'asc';
        }

        $calendarKeys = self::toStringList($params['source'] ?? $params['calendar'] ?? []);
        $categories = self::toStringList($params['category'] ?? $params['categories'] ?? []);

        $search = isset($params['q']) ? trim((string) $params['q']) : null;
        if ($search === '') {
            $search = null;
        }

        return new self(
            from: self::parseDate($params['from'] ?? null),
            to: self::parseDate($params['to'] ?? null),
            calendarKeys: $calendarKeys,
            categories: $categories,
            search: $search,
            sort: $sort,
            limit: $limit,
            offset: $offset,
            includeDeleted: false,
            futureOnly: filter_var($params['future_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
            includeExpired: filter_var($params['include_expired'] ?? true, FILTER_VALIDATE_BOOLEAN),
            month: isset($params['month']) ? (int) $params['month'] : null,
            year: isset($params['year']) ? (int) $params['year'] : null,
        );
    }

    private static function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (new \DateTimeImmutable('@' . (string) (int) $value))
                ->setTimezone(new \DateTimeZone('UTC'));
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private static function toStringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_array($value)) {
            $value = explode(',', (string) $value);
        }

        $out = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }
}
