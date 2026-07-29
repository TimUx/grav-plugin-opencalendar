<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Controllers;

use Grav\Plugin\OpenCalendar\Enum\SyncStatus;
use Grav\Plugin\OpenCalendar\Services\Container;

/**
 * Authenticated HTTP webhook that triggers a forced calendar sync.
 */
final class WebhookController
{
    /**
     * @param array<string, mixed> $webhookConfig
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $webhookConfig,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, body: array<string, mixed>, headers: array<string, string>}
     */
    public function handle(string $method, array $query, ?string $rawBody, string $providedToken): array
    {
        $headers = ['Content-Type' => 'application/json; charset=utf-8'];

        if (!($this->webhookConfig['enabled'] ?? false)) {
            return [
                'status' => 404,
                'body' => ['error' => 'Webhook disabled'],
                'headers' => $headers,
            ];
        }

        $method = strtoupper($method);
        if (!in_array($method, ['POST', 'GET'], true)) {
            return [
                'status' => 405,
                'body' => ['error' => 'Method not allowed'],
                'headers' => array_merge($headers, ['Allow' => 'GET, POST']),
            ];
        }

        $secret = (string) ($this->webhookConfig['secret'] ?? '');
        if ($secret === '') {
            return [
                'status' => 503,
                'body' => ['error' => 'Webhook secret is not configured'],
                'headers' => $headers,
            ];
        }

        if (!$this->tokenMatches($secret, $providedToken)) {
            return [
                'status' => 401,
                'body' => ['error' => 'Unauthorized'],
                'headers' => $headers,
            ];
        }

        $sourceKey = $this->resolveSourceKey($query, $rawBody);

        try {
            $this->container->boot();
            $sources = $this->container->sourceConfigs();
            $service = $this->container->calendarService();

            if ($sourceKey !== null) {
                $result = $service->synchronizeOne($sources, $sourceKey);
                if ($result === null) {
                    return [
                        'status' => 404,
                        'body' => ['error' => 'Source not found', 'source' => $sourceKey],
                        'headers' => $headers,
                    ];
                }

                return [
                    'status' => $result->status === SyncStatus::Error ? 500 : 200,
                    'body' => [
                        'ok' => $result->status !== SyncStatus::Error,
                        'results' => [$result->toArray()],
                    ],
                    'headers' => $headers,
                ];
            }

            $results = $service->synchronize($sources, true);

            return [
                'status' => 200,
                'body' => [
                    'ok' => true,
                    'results' => array_map(static fn ($r) => $r->toArray(), $results),
                ],
                'headers' => $headers,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => ['error' => 'Sync failed', 'message' => $e->getMessage()],
                'headers' => $headers,
            ];
        }
    }

    private function tokenMatches(string $secret, string $provided): bool
    {
        if ($provided === '') {
            return false;
        }

        return hash_equals($secret, $provided);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function resolveSourceKey(array $query, ?string $rawBody): ?string
    {
        if (!($this->webhookConfig['allow_source_param'] ?? true)) {
            return null;
        }

        $source = $query['source'] ?? $query['calendar'] ?? null;
        if (is_string($source) && $source !== '') {
            return $source;
        }

        if ($rawBody === null || $rawBody === '') {
            return null;
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            return null;
        }

        $source = $decoded['source'] ?? $decoded['calendar'] ?? null;

        return is_string($source) && $source !== '' ? $source : null;
    }
}
