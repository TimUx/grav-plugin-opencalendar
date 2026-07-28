<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Storage;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Enum\SyncStatus;
use Grav\Plugin\OpenCalendar\Models\Calendar;

final class CalendarRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function upsertFromConfig(SourceConfig $config): Calendar
    {
        $existing = $this->findBySourceKey($config->key);
        $now = $this->now();

        if ($existing === null) {
            $this->db->execute(
                'INSERT INTO calendars (
                    source_key, name, type, url, enabled, color, description, refresh,
                    status, config_json, created_at, updated_at
                ) VALUES (
                    :source_key, :name, :type, :url, :enabled, :color, :description, :refresh,
                    :status, :config_json, :created_at, :updated_at
                )',
                [
                    'source_key' => $config->key,
                    'name' => $config->name,
                    'type' => $config->type->value,
                    'url' => $config->url,
                    'enabled' => $config->enabled ? 1 : 0,
                    'color' => $config->color,
                    'description' => $config->description,
                    'refresh' => $config->refresh,
                    'status' => SyncStatus::Idle->value,
                    'config_json' => json_encode($config->auth, JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            return $this->findById($this->db->lastInsertId())
                ?? throw new \RuntimeException('Failed to load calendar after insert.');
        }

        $this->db->execute(
            'UPDATE calendars SET
                name = :name,
                type = :type,
                url = :url,
                enabled = :enabled,
                color = :color,
                description = :description,
                refresh = :refresh,
                config_json = :config_json,
                updated_at = :updated_at
             WHERE id = :id',
            [
                'id' => $existing->id,
                'name' => $config->name,
                'type' => $config->type->value,
                'url' => $config->url,
                'enabled' => $config->enabled ? 1 : 0,
                'color' => $config->color,
                'description' => $config->description,
                'refresh' => $config->refresh,
                'config_json' => json_encode($config->auth, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]
        );

        return $this->findById((int) $existing->id)
            ?? throw new \RuntimeException('Failed to load calendar after update.');
    }

    public function findById(int $id): ?Calendar
    {
        $row = $this->db->fetchOne('SELECT * FROM calendars WHERE id = :id', ['id' => $id]);

        return $row === null ? null : $this->hydrate($row);
    }

    public function findBySourceKey(string $key): ?Calendar
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM calendars WHERE source_key = :source_key',
            ['source_key' => $key]
        );

        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * @return list<Calendar>
     */
    public function all(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM calendars ORDER BY name COLLATE NOCASE ASC');

        return array_map(fn (array $row): Calendar => $this->hydrate($row), $rows);
    }

    /**
     * @return list<Calendar>
     */
    public function enabled(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM calendars WHERE enabled = 1 ORDER BY name COLLATE NOCASE ASC'
        );

        return array_map(fn (array $row): Calendar => $this->hydrate($row), $rows);
    }

    public function updateSyncState(
        int $calendarId,
        SyncStatus $status,
        int $durationMs,
        int $imported,
        int $updated,
        int $deleted,
        ?int $httpStatus,
        ?string $etag,
        ?string $lastModified,
        ?string $contentHash,
        ?string $error,
        bool $success,
    ): void {
        $now = $this->now();

        $this->db->execute(
            'UPDATE calendars SET
                status = :status,
                last_sync_at = :last_sync_at,
                last_success_at = CASE WHEN :success = 1 THEN :last_success_at ELSE last_success_at END,
                last_sync_duration_ms = :duration_ms,
                last_http_status = :http_status,
                etag = COALESCE(:etag, etag),
                last_modified = COALESCE(:last_modified, last_modified),
                content_hash = COALESCE(:content_hash, content_hash),
                last_error = :last_error,
                imported_count = :imported_count,
                updated_count = :updated_count,
                deleted_count = :deleted_count,
                updated_at = :updated_at
             WHERE id = :id',
            [
                'id' => $calendarId,
                'status' => $status->value,
                'last_sync_at' => $now,
                'success' => $success ? 1 : 0,
                'last_success_at' => $now,
                'duration_ms' => $durationMs,
                'http_status' => $httpStatus,
                'etag' => $etag,
                'last_modified' => $lastModified,
                'content_hash' => $contentHash,
                'last_error' => $error,
                'imported_count' => $imported,
                'updated_count' => $updated,
                'deleted_count' => $deleted,
                'updated_at' => $now,
            ]
        );
    }

    public function logSync(
        ?int $calendarId,
        SyncStatus $status,
        string $startedAt,
        string $finishedAt,
        int $imported,
        int $updated,
        int $deleted,
        ?int $httpStatus,
        int $durationMs,
        ?string $message,
    ): void {
        $this->db->execute(
            'INSERT INTO sync_log (
                calendar_id, started_at, finished_at, status,
                imported_count, updated_count, deleted_count,
                http_status, duration_ms, message
            ) VALUES (
                :calendar_id, :started_at, :finished_at, :status,
                :imported_count, :updated_count, :deleted_count,
                :http_status, :duration_ms, :message
            )',
            [
                'calendar_id' => $calendarId,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'status' => $status->value,
                'imported_count' => $imported,
                'updated_count' => $updated,
                'deleted_count' => $deleted,
                'http_status' => $httpStatus,
                'duration_ms' => $durationMs,
                'message' => $message,
            ]
        );
    }

    /**
     * Make the calendars table match the configured source keys exactly.
     *
     * An empty $keepKeys list deletes all calendars (caller must ensure config was read correctly).
     *
     * @param list<string> $keepKeys
     */
    public function pruneToKeys(array $keepKeys): int
    {
        if ($keepKeys === []) {
            $stmt = $this->db->execute('DELETE FROM calendars');

            return $stmt->rowCount();
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($keepKeys) as $i => $key) {
            $name = 'k' . $i;
            $placeholders[] = ':' . $name;
            $params[$name] = $key;
        }

        $sql = 'DELETE FROM calendars WHERE source_key NOT IN (' . implode(',', $placeholders) . ')';
        $stmt = $this->db->execute($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * @deprecated Use pruneToKeys(); kept for clarity at call sites that never wipe-all.
     * @param list<string> $keepKeys
     */
    public function deleteMissingKeys(array $keepKeys): int
    {
        if ($keepKeys === []) {
            return 0;
        }

        return $this->pruneToKeys($keepKeys);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Calendar
    {
        return new Calendar(
            id: (int) $row['id'],
            sourceKey: (string) $row['source_key'],
            name: (string) $row['name'],
            type: SourceType::tryFrom((string) $row['type']) ?? SourceType::Ics,
            url: $row['url'] !== null ? (string) $row['url'] : null,
            enabled: (bool) $row['enabled'],
            color: $row['color'] !== null ? (string) $row['color'] : null,
            description: $row['description'] !== null ? (string) $row['description'] : null,
            refresh: (string) $row['refresh'],
            etag: $row['etag'] !== null ? (string) $row['etag'] : null,
            lastModified: $row['last_modified'] !== null ? (string) $row['last_modified'] : null,
            contentHash: $row['content_hash'] !== null ? (string) $row['content_hash'] : null,
            lastSyncAt: $this->parseDate($row['last_sync_at'] ?? null),
            lastSuccessAt: $this->parseDate($row['last_success_at'] ?? null),
            lastSyncDurationMs: $row['last_sync_duration_ms'] !== null ? (int) $row['last_sync_duration_ms'] : null,
            lastHttpStatus: $row['last_http_status'] !== null ? (int) $row['last_http_status'] : null,
            lastError: $row['last_error'] !== null ? (string) $row['last_error'] : null,
            status: SyncStatus::tryFrom((string) $row['status']) ?? SyncStatus::Idle,
            importedCount: (int) $row['imported_count'],
            updatedCount: (int) $row['updated_count'],
            deletedCount: (int) $row['deleted_count'],
            configJson: $row['config_json'] !== null ? (string) $row['config_json'] : null,
        );
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format(\DateTimeInterface::ATOM);
    }
}
