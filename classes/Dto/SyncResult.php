<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Dto;

use Grav\Plugin\OpenCalendar\Enum\SyncStatus;

final class SyncResult
{
    public function __construct(
        public readonly string $sourceKey,
        public readonly SyncStatus $status,
        public readonly int $imported = 0,
        public readonly int $updated = 0,
        public readonly int $deleted = 0,
        public readonly int $durationMs = 0,
        public readonly ?int $httpStatus = null,
        public readonly ?string $etag = null,
        public readonly ?string $lastModified = null,
        public readonly ?string $contentHash = null,
        public readonly ?string $error = null,
        public readonly bool $skippedParse = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'status' => $this->status->value,
            'imported' => $this->imported,
            'updated' => $this->updated,
            'deleted' => $this->deleted,
            'duration_ms' => $this->durationMs,
            'http_status' => $this->httpStatus,
            'etag' => $this->etag,
            'last_modified' => $this->lastModified,
            'content_hash' => $this->contentHash,
            'error' => $this->error,
            'skipped_parse' => $this->skippedParse,
        ];
    }
}
