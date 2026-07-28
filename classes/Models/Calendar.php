<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Models;

use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Enum\SyncStatus;

final class Calendar
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $sourceKey,
        public readonly string $name,
        public readonly SourceType $type,
        public readonly ?string $url,
        public readonly bool $enabled,
        public readonly ?string $color,
        public readonly ?string $description,
        public readonly string $refresh,
        public readonly ?string $etag = null,
        public readonly ?string $lastModified = null,
        public readonly ?string $contentHash = null,
        public readonly ?\DateTimeImmutable $lastSyncAt = null,
        public readonly ?\DateTimeImmutable $lastSuccessAt = null,
        public readonly ?int $lastSyncDurationMs = null,
        public readonly ?int $lastHttpStatus = null,
        public readonly ?string $lastError = null,
        public readonly SyncStatus $status = SyncStatus::Idle,
        public readonly int $importedCount = 0,
        public readonly int $updatedCount = 0,
        public readonly int $deletedCount = 0,
        public readonly ?string $configJson = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_key' => $this->sourceKey,
            'name' => $this->name,
            'type' => $this->type->value,
            'url' => $this->url,
            'enabled' => $this->enabled,
            'color' => $this->color,
            'description' => $this->description,
            'refresh' => $this->refresh,
            'etag' => $this->etag,
            'last_modified' => $this->lastModified,
            'content_hash' => $this->contentHash,
            'last_sync_at' => $this->lastSyncAt?->format(\DateTimeInterface::ATOM),
            'last_success_at' => $this->lastSuccessAt?->format(\DateTimeInterface::ATOM),
            'last_sync_duration_ms' => $this->lastSyncDurationMs,
            'last_http_status' => $this->lastHttpStatus,
            'last_error' => $this->lastError,
            'status' => $this->status->value,
            'imported_count' => $this->importedCount,
            'updated_count' => $this->updatedCount,
            'deleted_count' => $this->deletedCount,
        ];
    }
}
