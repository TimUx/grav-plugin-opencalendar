<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Api;

use Grav\Plugin\OpenCalendar\Dto\EventQuery;
use Grav\Plugin\OpenCalendar\Models\Event;
use Grav\Plugin\OpenCalendar\Services\CalendarService;

/**
 * Read-only JSON API handlers.
 */
final class EventsApi
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly array $apiConfig = [],
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @return array{meta: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public function listEvents(array $params): array
    {
        $defaultLimit = (int) ($this->apiConfig['pagination']['default_limit'] ?? 50);
        $maxLimit = (int) ($this->apiConfig['pagination']['max_limit'] ?? 200);
        $query = EventQuery::fromRequest($params, $defaultLimit, $maxLimit);
        $result = $this->calendarService->queryEvents($query);

        return $result->toArray(fn (Event $event): array => $this->serializeEvent($event));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEvent(string $uid, ?string $calendarKey = null): ?array
    {
        $event = $this->calendarService->getEventByUid($uid, $calendarKey);

        return $event === null ? null : $this->serializeEvent($event);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCalendars(): array
    {
        return array_map(
            static fn ($calendar): array => $calendar->toArray(),
            $this->calendarService->listCalendars()
        );
    }

    /**
     * @return array{data: list<string>}
     */
    public function listCategories(): array
    {
        return ['data' => $this->calendarService->listCategories()];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'uid' => $event->uid,
            'title' => $event->title,
            'start' => $event->startAt->format(\DateTimeInterface::ATOM),
            'end' => $event->endAt?->format(\DateTimeInterface::ATOM),
            'allDay' => $event->allDay,
            'location' => $event->location,
            'description' => $event->description,
            'organizer' => $event->organizer,
            'categories' => $event->categories,
            'status' => $event->status,
            'url' => $event->url,
            'attachments' => $event->attachments,
            'color' => $event->color ?? $event->calendarColor,
            'source' => [
                'key' => $event->calendarKey,
                'name' => $event->calendarName,
                'color' => $event->calendarColor,
            ],
        ];
    }
}
