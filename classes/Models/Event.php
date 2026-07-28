<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Models;

final class Event
{
    /**
     * @param list<string> $categories
     * @param list<array{uri: string, filename?: string|null, mime?: string|null}> $attachments
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $calendarId,
        public readonly string $uid,
        public readonly ?string $recurrenceId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $location,
        public readonly ?string $organizer,
        public readonly ?string $url,
        public readonly ?string $status,
        public readonly array $categories,
        public readonly ?string $color,
        public readonly array $attachments,
        public readonly \DateTimeImmutable $startAt,
        public readonly ?\DateTimeImmutable $endAt,
        public readonly bool $allDay,
        public readonly ?string $timezone,
        public readonly bool $isRecurring,
        public readonly ?string $rrule,
        public readonly ?string $contentHash,
        public readonly ?\DateTimeImmutable $deletedAt = null,
        public readonly ?string $calendarName = null,
        public readonly ?string $calendarColor = null,
        public readonly ?string $calendarKey = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'calendar_id' => $this->calendarId,
            'calendar_key' => $this->calendarKey,
            'calendar_name' => $this->calendarName,
            'calendar_color' => $this->calendarColor,
            'uid' => $this->uid,
            'recurrence_id' => $this->recurrenceId,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'organizer' => $this->organizer,
            'url' => $this->url,
            'status' => $this->status,
            'categories' => $this->categories,
            'color' => $this->color ?? $this->calendarColor,
            'attachments' => $this->attachments,
            'start' => $this->startAt->format(\DateTimeInterface::ATOM),
            'end' => $this->endAt?->format(\DateTimeInterface::ATOM),
            'all_day' => $this->allDay,
            'timezone' => $this->timezone,
            'is_recurring' => $this->isRecurring,
            'rrule' => $this->rrule,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCalendarEvent(): array
    {
        $payload = [
            'id' => (string) ($this->id ?? $this->uid),
            'title' => $this->title,
            'start' => $this->allDay
                ? $this->startAt->format('Y-m-d')
                : $this->startAt->format(\DateTimeInterface::ATOM),
            'allDay' => $this->allDay,
            'backgroundColor' => $this->color ?? $this->calendarColor,
            'borderColor' => $this->color ?? $this->calendarColor,
            'extendedProps' => [
                'uid' => $this->uid,
                'description' => $this->description,
                'location' => $this->location,
                'organizer' => $this->organizer,
                'url' => $this->url,
                'status' => $this->status,
                'categories' => $this->categories,
                'attachments' => $this->attachments,
                'calendar' => $this->calendarName,
                'calendar_key' => $this->calendarKey,
                'calendar_color' => $this->calendarColor,
            ],
        ];

        if ($this->endAt !== null) {
            $payload['end'] = $this->allDay
                ? $this->endAt->format('Y-m-d')
                : $this->endAt->format(\DateTimeInterface::ATOM);
        }

        return $payload;
    }
}
