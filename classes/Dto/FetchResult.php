<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Dto;

/**
 * Result of fetching a remote or local calendar source.
 */
final class FetchResult
{
    public function __construct(
        public readonly string $body,
        public readonly int $httpStatus,
        public readonly ?string $etag = null,
        public readonly ?string $lastModified = null,
        public readonly bool $notModified = false,
        public readonly string $contentHash = '',
    ) {
    }

    public static function notModified(
        int $httpStatus = 304,
        ?string $etag = null,
        ?string $lastModified = null,
        string $contentHash = '',
    ): self {
        return new self(
            body: '',
            httpStatus: $httpStatus,
            etag: $etag,
            lastModified: $lastModified,
            notModified: true,
            contentHash: $contentHash,
        );
    }

    public static function fromBody(
        string $body,
        int $httpStatus = 200,
        ?string $etag = null,
        ?string $lastModified = null,
    ): self {
        return new self(
            body: $body,
            httpStatus: $httpStatus,
            etag: $etag,
            lastModified: $lastModified,
            notModified: false,
            contentHash: hash('sha256', $body),
        );
    }
}
