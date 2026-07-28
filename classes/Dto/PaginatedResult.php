<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Dto;

/**
 * @template T
 */
final class PaginatedResult
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }

    public function hasMore(): bool
    {
        return ($this->offset + $this->limit) < $this->total;
    }

    public function page(): int
    {
        if ($this->limit <= 0) {
            return 1;
        }

        return (int) floor($this->offset / $this->limit) + 1;
    }

    public function totalPages(): int
    {
        if ($this->limit <= 0) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->limit));
    }

    /**
     * @template TMapped
     * @param callable(T): TMapped $mapper
     * @return array{meta: array{total: int, limit: int, offset: int, page: int, pages: int}, data: list<TMapped>}
     */
    public function toArray(callable $mapper): array
    {
        /** @var list<TMapped> $data */
        $data = array_values(array_map($mapper, $this->items));

        return [
            'meta' => [
                'total' => $this->total,
                'limit' => $this->limit,
                'offset' => $this->offset,
                'page' => $this->page(),
                'pages' => $this->totalPages(),
            ],
            'data' => $data,
        ];
    }
}
