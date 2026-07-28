<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Models\Event;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

/**
 * RFC5545 ICS/iCalendar parser built on Sabre VObject.
 *
 * Expands recurring events within a configurable horizon and maps VEVENT
 * properties onto the normalized Event model.
 */
final class IcsParser
{
    public function __construct(
        private readonly string $defaultTimezone = 'UTC',
        private readonly bool $expandRecurring = true,
        private readonly int $recurringHorizonDays = 365,
        private readonly bool $stripHtml = false,
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
            $document = Reader::read($payload, Reader::OPTION_FORGIVING);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Invalid ICS payload: ' . $e->getMessage(), 0, $e);
        }

        if (!$document instanceof VCalendar) {
            throw new \RuntimeException('ICS payload does not contain a VCALENDAR component.');
        }

        $timezone = $this->resolveTimezone($document);
        $this->localizeFloatingTimes($document, $timezone);
        $events = [];

        if ($this->expandRecurring) {
            $from = new \DateTimeImmutable('now', $timezone);
            $from = $from->modify('-30 days');
            $to = $from->modify('+' . max(1, $this->recurringHorizonDays) . ' days');

            try {
                $expanded = $document->expand(
                    \DateTime::createFromImmutable($from),
                    \DateTime::createFromImmutable($to)
                );
            } catch (\Throwable) {
                $expanded = $document;
            }

            if ($expanded instanceof VCalendar) {
                $document = $expanded;
            }
        }

        foreach ($document->select('VEVENT') as $component) {
            if (!$component instanceof VEvent) {
                continue;
            }

            $mapped = $this->mapEvent($component, $config, $calendarId, $timezone);
            if ($mapped !== null) {
                $events[] = $mapped;
            }
        }

        return $events;
    }

    private function mapEvent(
        VEvent $vevent,
        SourceConfig $config,
        int $calendarId,
        \DateTimeZone $fallbackTz,
    ): ?Event {
        $uid = trim((string) ($vevent->UID ?? ''));
        if ($uid === '') {
            $uid = 'generated-' . hash('sha256', (string) $vevent->serialize());
        }

        $dtStart = $vevent->DTSTART ?? null;
        if ($dtStart === null) {
            return null;
        }

        $allDay = !$dtStart->hasTime();
        $startAt = $this->fromIcalDate($dtStart, $fallbackTz);
        $endAt = null;

        if (isset($vevent->DTEND)) {
            $endAt = $this->fromIcalDate($vevent->DTEND, $fallbackTz);
        } elseif (isset($vevent->DURATION) && $startAt !== null) {
            try {
                $duration = $vevent->DURATION->getDateInterval();
                $endAt = $startAt->add($duration);
            } catch (\Throwable) {
                $endAt = null;
            }
        }

        $title = trim((string) ($vevent->SUMMARY ?? 'Untitled'));
        $description = isset($vevent->DESCRIPTION)
            ? $this->normalizeText((string) $vevent->DESCRIPTION)
            : null;
        $location = isset($vevent->LOCATION) ? trim((string) $vevent->LOCATION) : null;
        $organizer = $this->extractOrganizer($vevent);
        $url = isset($vevent->URL) ? trim((string) $vevent->URL) : null;
        $status = isset($vevent->STATUS) ? strtoupper(trim((string) $vevent->STATUS)) : null;
        $categories = $this->extractCategories($vevent);
        $color = $this->extractColor($vevent) ?? $config->color;
        $attachments = $this->extractAttachments($vevent);
        $recurrenceId = isset($vevent->{'RECURRENCE-ID'})
            ? $this->formatRecurrenceId($vevent->{'RECURRENCE-ID'})
            : null;

        $isRecurring = isset($vevent->RRULE);
        $rrule = $isRecurring ? trim((string) $vevent->RRULE) : null;

        $timezoneName = null;
        if (!$allDay) {
            $timezoneName = $startAt->getTimezone()->getName();
        }

        $contentHash = hash('sha256', implode('|', [
            $uid,
            $recurrenceId ?? '',
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
            recurrenceId: $recurrenceId,
            title: $title,
            description: $description,
            location: $location !== '' ? $location : null,
            organizer: $organizer,
            url: $url !== '' ? $url : null,
            status: $status,
            categories: $categories,
            color: $color,
            attachments: $attachments,
            startAt: $startAt,
            endAt: $endAt,
            allDay: $allDay,
            timezone: $timezoneName,
            isRecurring: $isRecurring || $recurrenceId !== null,
            rrule: $rrule,
            contentHash: $contentHash,
            calendarName: $config->name,
            calendarColor: $config->color,
            calendarKey: $config->key,
        );
    }

    private function localizeFloatingTimes(VCalendar $calendar, \DateTimeZone $timezone): void
    {
        $tzName = $timezone->getName();

        foreach ($calendar->select('VEVENT') as $component) {
            if (!$component instanceof VEvent) {
                continue;
            }

            foreach (['DTSTART', 'DTEND', 'RECURRENCE-ID'] as $name) {
                if (!isset($component->{$name})) {
                    continue;
                }

                $prop = $component->{$name};
                if (!is_object($prop) || !method_exists($prop, 'isFloating') || !method_exists($prop, 'hasTime')) {
                    continue;
                }

                if (!$prop->hasTime() || !$prop->isFloating()) {
                    continue;
                }

                $prop['TZID'] = $tzName;
            }
        }
    }

    /**
     * @param \Sabre\VObject\Property $prop
     */
    private function fromIcalDate(object $prop, \DateTimeZone $fallbackTz): \DateTimeImmutable
    {
        if (!method_exists($prop, 'getDateTime')) {
            throw new \RuntimeException('Invalid iCalendar date property.');
        }

        $allDay = method_exists($prop, 'hasTime') && !$prop->hasTime();
        $dt = $prop->getDateTime();
        $immutable = $dt instanceof \DateTimeImmutable
            ? $dt
            : \DateTimeImmutable::createFromInterface($dt);

        if ($allDay) {
            return new \DateTimeImmutable($immutable->format('Y-m-d'), new \DateTimeZone('UTC'));
        }

        // Floating local times (no TZID / Z) keep wall-clock in site/calendar timezone.
        if (method_exists($prop, 'isFloating') && $prop->isFloating()) {
            return new \DateTimeImmutable($immutable->format('Y-m-d H:i:s'), $fallbackTz);
        }

        // Absolute times (Z or TZID): preserve the instant, express in display timezone.
        return $immutable->setTimezone($fallbackTz);
    }

    private function resolveTimezone(VCalendar $calendar): \DateTimeZone
    {
        $candidate = $this->defaultTimezone;
        if (isset($calendar->{'X-WR-TIMEZONE'})) {
            $fromCalendar = trim((string) $calendar->{'X-WR-TIMEZONE'});
            if ($fromCalendar !== '') {
                $candidate = $fromCalendar;
            }
        }

        try {
            return new \DateTimeZone($candidate);
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(['\\n', '\\N', '\\,', '\\;'], ["\n", "\n", ',', ';'], $text);
        $text = trim($text);

        if ($this->stripHtml) {
            $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $text;
    }

    private function extractOrganizer(VEvent $vevent): ?string
    {
        if (!isset($vevent->ORGANIZER)) {
            return null;
        }

        $organizer = $vevent->ORGANIZER;
        $cn = $this->propertyParam($organizer, 'CN');
        $value = trim((string) $organizer);
        $value = preg_replace('/^mailto:/i', '', $value) ?? $value;

        if ($cn !== null && $cn !== '') {
            return $cn . ($value !== '' ? ' <' . $value . '>' : '');
        }

        return $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function extractCategories(VEvent $vevent): array
    {
        if (!isset($vevent->CATEGORIES)) {
            return [];
        }

        $categories = [];
        foreach ($vevent->select('CATEGORIES') as $prop) {
            $raw = (string) $prop;
            foreach (explode(',', $raw) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $categories[] = $part;
                }
            }
        }

        return array_values(array_unique($categories));
    }

    private function extractColor(VEvent $vevent): ?string
    {
        foreach (['COLOR', 'X-APPLE-CALENDAR-COLOR', 'X-COLOR'] as $name) {
            if (isset($vevent->{$name})) {
                $color = trim((string) $vevent->{$name});
                if ($color !== '') {
                    return $color;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{uri: string, filename?: string|null, mime?: string|null}>
     */
    private function extractAttachments(VEvent $vevent): array
    {
        if (!isset($vevent->ATTACH)) {
            return [];
        }

        $attachments = [];
        foreach ($vevent->select('ATTACH') as $attach) {
            $uri = trim((string) $attach);
            if ($uri === '') {
                continue;
            }

            $filename = $this->propertyParam($attach, 'FILENAME')
                ?? $this->propertyParam($attach, 'X-FILENAME');
            $mime = $this->propertyParam($attach, 'FMTTYPE');

            $attachments[] = [
                'uri' => $uri,
                'filename' => $filename,
                'mime' => $mime,
            ];
        }

        return $attachments;
    }

    private function propertyParam(mixed $property, string $name): ?string
    {
        if (!is_object($property) || !isset($property->parameters)) {
            return null;
        }

        $parameters = $property->parameters;
        if (!is_iterable($parameters)) {
            return null;
        }

        foreach ($parameters as $paramName => $param) {
            if (strcasecmp((string) $paramName, $name) === 0) {
                $value = trim((string) $param);

                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    private function formatRecurrenceId(mixed $prop): string
    {
        try {
            if (is_object($prop) && method_exists($prop, 'getDateTime')) {
                return $prop->getDateTime()->format('Y-m-d\TH:i:s');
            }
        } catch (\Throwable) {
            // fall through
        }

        return trim((string) $prop);
    }
}
