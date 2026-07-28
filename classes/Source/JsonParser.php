<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Models\Event;

/**
 * Parses OpenCalendar JSON event payloads into Event models.
 */
final class JsonParser
{
    public function __construct(
        private readonly string $defaultTimezone = 'Europe/Berlin',
    ) {
    }

    /**
     * @return list<Event>
     */
    public function parse(string $payload, SourceConfig $config, int $calendarId = 0): array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Invalid JSON calendar payload: ' . $e->getMessage(), 0, $e);
        }

        $rows = $this->extractEventRows($decoded);
        $events = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $event = $this->mapEvent($row, $config, $calendarId, $index);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @return list<mixed>
     */
    private function extractEventRows(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            throw new \RuntimeException('JSON calendar payload must be an object or array.');
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        foreach (['events', 'data', 'items', 'results'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return array_values($decoded[$key]);
            }
        }

        throw new \RuntimeException(
            'JSON calendar payload must be a list of events or an object with an events/data/items array.'
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapEvent(array $row, SourceConfig $config, int $calendarId, int $index): ?Event
    {
        $title = trim((string) ($row['title'] ?? $row['summary'] ?? $row['name'] ?? ''));
        $startRaw = $row['start'] ?? $row['start_at'] ?? $row['dtstart'] ?? $row['begin'] ?? null;
        if ($title === '' || $startRaw === null || $startRaw === '') {
            return null;
        }

        $allDay = (bool) ($row['all_day'] ?? $row['allDay'] ?? false);
        $startAt = $this->parseDateTime((string) $startRaw, $allDay);
        if ($startAt === null) {
            return null;
        }

        $endRaw = $row['end'] ?? $row['end_at'] ?? $row['dtend'] ?? null;
        $endAt = is_string($endRaw) && $endRaw !== ''
            ? $this->parseDateTime($endRaw, $allDay)
            : null;

        $uid = trim((string) ($row['uid'] ?? $row['id'] ?? ''));
        if ($uid === '') {
            $uid = 'json-' . hash('sha256', $config->key . '|' . $title . '|' . $startAt->format('c') . '|' . $index);
        }

        $categories = $this->normalizeCategories($row['categories'] ?? $row['category'] ?? []);
        $description = isset($row['description']) ? trim((string) $row['description']) : null;
        $location = isset($row['location']) ? trim((string) $row['location']) : null;
        $organizer = isset($row['organizer']) ? trim((string) $row['organizer']) : null;
        $url = isset($row['url']) ? trim((string) $row['url']) : null;
        $status = isset($row['status']) ? strtoupper(trim((string) $row['status'])) : null;
        $color = isset($row['color']) ? trim((string) $row['color']) : $config->color;
        $rrule = isset($row['rrule']) ? trim((string) $row['rrule']) : null;
        $attachments = $this->normalizeAttachments($row['attachments'] ?? []);

        $contentHash = hash('sha256', implode('|', [
            $uid,
            $title,
            $startAt->format(\DateTimeInterface::ATOM),
            $endAt?->format(\DateTimeInterface::ATOM) ?? '',
            $description ?? '',
            $location ?? '',
            implode(',', $categories),
        ]));

        return new Event(
            id: null,
            calendarId: $calendarId,
            uid: $uid,
            recurrenceId: isset($row['recurrence_id']) ? trim((string) $row['recurrence_id']) : null,
            title: $title,
            description: $description !== '' ? $description : null,
            location: $location !== '' ? $location : null,
            organizer: $organizer !== '' ? $organizer : null,
            url: $url !== '' ? $url : null,
            status: $status !== '' ? $status : null,
            categories: $categories,
            color: $color !== '' ? $color : $config->color,
            attachments: $attachments,
            startAt: $startAt,
            endAt: $endAt,
            allDay: $allDay,
            timezone: $allDay ? null : $startAt->getTimezone()->getName(),
            isRecurring: $rrule !== null && $rrule !== '',
            rrule: $rrule !== '' ? $rrule : null,
            contentHash: $contentHash,
            calendarName: $config->name,
            calendarColor: $config->color,
            calendarKey: $config->key,
        );
    }

    private function parseDateTime(string $value, bool $allDay): ?\DateTimeImmutable
    {
        try {
            $tzName = $this->defaultTimezone !== '' ? $this->defaultTimezone : 'Europe/Berlin';
            $tz = new \DateTimeZone($tzName);
            $dt = new \DateTimeImmutable($value);
            if ($allDay) {
                return new \DateTimeImmutable($dt->format('Y-m-d'), new \DateTimeZone('UTC'));
            }

            return $dt->setTimezone($tz);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeCategories(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
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

    /**
     * @return list<array{uri: string, filename?: string|null, mime?: string|null}>
     */
    private function normalizeAttachments(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = ['uri' => $item];
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            $uri = trim((string) ($item['uri'] ?? $item['url'] ?? ''));
            if ($uri === '') {
                continue;
            }
            $out[] = [
                'uri' => $uri,
                'filename' => isset($item['filename']) ? (string) $item['filename'] : null,
                'mime' => isset($item['mime']) ? (string) $item['mime'] : null,
            ];
        }

        return $out;
    }
}
