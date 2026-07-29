<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Controllers;

use Grav\Plugin\OpenCalendar\Dto\EventQuery;
use Grav\Plugin\OpenCalendar\Export\IcsExporter;
use Grav\Plugin\OpenCalendar\Services\Container;

/**
 * Serves aggregated calendar data as text/calendar (ICS).
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
    public function handle(array $query): array
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

            $eventQuery = EventQuery::fromRequest($params, $defaultLimit, $max);
            $result = $this->container->calendarService()->queryEvents($eventQuery);

            $calendarName = (string) ($this->exportConfig['calendar_name'] ?? 'OpenCalendar');
            if (isset($query['name']) && is_string($query['name']) && $query['name'] !== '') {
                $calendarName = $query['name'];
            }

            $exporter = new IcsExporter(
                calendarName: $calendarName,
            );
            $ics = $exporter->export($result->items, $calendarName);

            $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $calendarName) ?: 'opencalendar';
            $filename = trim($filename, '-') . '.ics';

            return [
                'status' => 200,
                'body' => $ics,
                'headers' => [
                    'Content-Type' => 'text/calendar; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Cache-Control' => 'public, max-age=300',
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
