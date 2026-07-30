<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Controllers;

use Grav\Plugin\OpenCalendar\Dto\EventQuery;
use Grav\Plugin\OpenCalendar\Export\IcsExporter;
use Grav\Plugin\OpenCalendar\Services\Container;

/**
 * Serves aggregated calendar data as a subscribable text/calendar (ICS) feed.
 */
final class ExportController
{
    /**
     * @param array<string, mixed> $exportConfig
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $exportConfig,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function handle(array $query, ?string $ifNoneMatch = null): array
    {
        if (!($this->exportConfig['enabled'] ?? true)) {
            return [
                'status' => 404,
                'body' => 'ICS export is disabled.',
                'headers' => ['Content-Type' => 'text/plain; charset=utf-8'],
            ];
        }

        try {
            $this->container->boot();
            $max = max(1, (int) ($this->exportConfig['max_events'] ?? 5000));
            $defaultLimit = min($max, 5000);

            $params = $query;
            if (!isset($params['limit'])) {
                $params['limit'] = $defaultLimit;
            }

            // Subscription-friendly default window unless explicitly overridden.
            if (!array_key_exists('from', $params) && ($this->exportConfig['default_from'] ?? '') !== '') {
                $params['from'] = (string) $this->exportConfig['default_from'];
            }
            if (!array_key_exists('to', $params) && ($this->exportConfig['default_to'] ?? '') !== '') {
                $params['to'] = (string) $this->exportConfig['default_to'];
            }

            $eventQuery = EventQuery::fromRequest($params, $defaultLimit, $max);
            $result = $this->container->calendarService()->queryEvents($eventQuery);

            $calendarName = (string) ($this->exportConfig['calendar_name'] ?? 'OpenCalendar');
            if (isset($query['name']) && is_string($query['name']) && $query['name'] !== '') {
                $calendarName = $query['name'];
            } elseif (
                isset($query['source'])
                && is_string($query['source'])
                && $query['source'] !== ''
                && !str_contains($query['source'], ',')
            ) {
                foreach ($this->container->calendarService()->listCalendars() as $calendar) {
                    if ($calendar->sourceKey === $query['source'] || $calendar->name === $query['source']) {
                        $calendarName = $calendar->name;
                        break;
                    }
                }
            }

            $refreshMinutes = max(5, (int) ($this->exportConfig['refresh_minutes'] ?? 60));
            $exporter = new IcsExporter(
                calendarName: $calendarName,
                refreshMinutes: $refreshMinutes,
                calendarDescription: (string) ($this->exportConfig['calendar_description'] ?? 'OpenCalendar subscription feed'),
            );
            $ics = $exporter->export($result->items, $calendarName);
            $etag = '"' . hash('sha256', $ics) . '"';

            if (is_string($ifNoneMatch) && $ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
                return [
                    'status' => 304,
                    'body' => '',
                    'headers' => [
                        'ETag' => $etag,
                        'Cache-Control' => 'public, max-age=' . ($refreshMinutes * 60),
                    ],
                ];
            }

            $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $calendarName) ?: 'opencalendar';
            $filename = trim($filename, '-') . '.ics';

            $download = filter_var($query['download'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $disposition = $download ? 'attachment' : 'inline';

            return [
                'status' => 200,
                'body' => $ics,
                'headers' => [
                    'Content-Type' => 'text/calendar; charset=utf-8',
                    'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
                    'Cache-Control' => 'public, max-age=' . ($refreshMinutes * 60),
                    'ETag' => $etag,
                    'X-Robots-Tag' => 'noindex',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => 'Export failed: ' . $e->getMessage(),
                'headers' => ['Content-Type' => 'text/plain; charset=utf-8'],
            ];
        }
    }
}
