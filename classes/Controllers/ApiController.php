<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Controllers;

use Grav\Plugin\OpenCalendar\Api\EventsApi;
use Grav\Plugin\OpenCalendar\Api\RateLimiter;
use Grav\Plugin\OpenCalendar\Services\Container;

/**
 * Handles frontend REST API routes under /opencalendar/api.
 */
final class ApiController
{
    public function __construct(
        private readonly Container $container,
        private readonly array $apiConfig,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $exportConfig
     * @return array{status: int, body: array<string, mixed>|string, headers: array<string, string>}
     */
    public function handle(
        string $path,
        array $query,
        string $clientIp = '0.0.0.0',
        string $method = 'GET',
        array $exportConfig = [],
    ): array {
        $headers = ['Content-Type' => 'application/json; charset=utf-8'];

        if (!empty($this->apiConfig['cors']['enabled'])) {
            $origins = $this->apiConfig['cors']['allowed_origins'] ?? [];
            if (is_array($origins) && $origins !== []) {
                $headers['Access-Control-Allow-Origin'] = (string) $origins[0];
            } else {
                $headers['Access-Control-Allow-Origin'] = '*';
            }
            $headers['Access-Control-Allow-Methods'] = 'GET, OPTIONS';
        }

        $method = strtoupper($method);
        if ($method === 'OPTIONS') {
            return ['status' => 204, 'body' => '', 'headers' => $headers];
        }

        if (!$this->rateLimiter->allow($clientIp)) {
            return [
                'status' => 429,
                'body' => ['error' => 'Rate limit exceeded'],
                'headers' => $headers,
            ];
        }

        $api = new EventsApi($this->container->calendarService(), $this->apiConfig);
        $path = '/' . trim($path, '/');

        try {
            if ($path === '/export.ics' || $path === '/calendar.ics') {
                $exporter = new ExportController($this->container, $exportConfig !== [] ? $exportConfig : [
                    'enabled' => true,
                    'calendar_name' => 'OpenCalendar',
                    'max_events' => 5000,
                ]);

                return $exporter->handle($query);
            }

            if ($path === '/events' || $path === '/') {
                return ['status' => 200, 'body' => $api->listEvents($query), 'headers' => $headers];
            }

            if (preg_match('#^/events/([^/]+)$#', $path, $m) === 1) {
                $event = $api->getEvent(rawurldecode($m[1]), isset($query['source']) ? (string) $query['source'] : null);
                if ($event === null) {
                    return ['status' => 404, 'body' => ['error' => 'Event not found'], 'headers' => $headers];
                }

                return ['status' => 200, 'body' => ['data' => $event], 'headers' => $headers];
            }

            if ($path === '/calendars' || $path === '/sources') {
                return ['status' => 200, 'body' => ['data' => $api->listCalendars()], 'headers' => $headers];
            }

            if ($path === '/categories') {
                return ['status' => 200, 'body' => $api->listCategories(), 'headers' => $headers];
            }

            return ['status' => 404, 'body' => ['error' => 'Not found'], 'headers' => $headers];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => ['error' => 'Internal server error', 'message' => $e->getMessage()],
                'headers' => $headers,
            ];
        }
    }
}
