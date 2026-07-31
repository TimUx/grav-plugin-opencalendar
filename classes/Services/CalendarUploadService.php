<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Services;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;

/**
 * Validates and stores uploaded calendar files for local source import.
 */
final class CalendarUploadService
{
    private const MAX_BYTES = 10_485_760; // 10 MiB

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['ics', 'ical', 'json'];

    public function __construct(private readonly string $uploadsDirectory)
    {
    }

    public function uploadsDirectory(): string
    {
        return $this->uploadsDirectory;
    }

    /**
     * @param array{
     *   name?: string,
     *   type?: string,
     *   tmp_name?: string,
     *   error?: int,
     *   size?: int
     * } $file $_FILES entry
     * @param bool $allowLocalTemp Allow readable temp files (CLI tests / Grav API PSR-7 moveTo)
     * @return array{path: string, relative_url: string, original_name: string, format: string}
     */
    public function storeUploadedFile(array $file, bool $allowLocalTemp = false): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage($error));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            throw new \RuntimeException('Uploaded file is missing or not readable.');
        }

        // is_uploaded_file() is false for CLI fixtures and PSR-7 moveTo() temps used by Admin Next.
        $fromHttp = !in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
        if ($fromHttp && !is_uploaded_file($tmp) && !$allowLocalTemp) {
            throw new \RuntimeException('Uploaded file is missing or not readable.');
        }

        $original = (string) ($file['name'] ?? 'calendar.ics');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Unsupported file type. Upload an .ics, .ical, or .json calendar file.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            $size = (int) filesize($tmp);
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \RuntimeException('Calendar file must be between 1 byte and 10 MiB.');
        }

        $body = file_get_contents($tmp);
        if ($body === false || $body === '') {
            throw new \RuntimeException('Unable to read the uploaded calendar file.');
        }

        $format = $this->detectAndValidateFormat($body, $extension);

        $this->ensureDirectory($this->uploadsDirectory);

        $safeBase = SourceConfig::slugify(pathinfo($original, PATHINFO_FILENAME));
        $filename = $safeBase . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
        $destination = rtrim($this->uploadsDirectory, "/\\") . DIRECTORY_SEPARATOR . $filename;

        if (!@rename($tmp, $destination) && !@copy($tmp, $destination)) {
            throw new \RuntimeException('Failed to store uploaded calendar file.');
        }
        @chmod($destination, 0644);

        return [
            'path' => $destination,
            'relative_url' => 'uploads/' . $filename,
            'original_name' => $original,
            'format' => $format,
        ];
    }

    /**
     * Build a local source row for plugin configuration.
     *
     * @return array<string, mixed>
     */
    public function buildSourceRow(string $name, string $relativeUrl, ?string $description = null): array
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'Uploaded calendar';
        }

        return [
            'name' => $name,
            'enabled' => true,
            'type' => SourceType::Local->value,
            'url' => $relativeUrl,
            'refresh' => 'inherit',
            'color' => '',
            'description' => $description ?? 'Uploaded via Admin',
            'auth' => [
                'type' => 'none',
                'username' => '',
                'password' => '',
                'token' => '',
            ],
        ];
    }

    private function detectAndValidateFormat(string $body, string $extension): string
    {
        $trimmed = ltrim($body);

        if ($extension === 'json' || str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Uploaded JSON calendar is invalid: ' . json_last_error_msg());
            }

            return 'json';
        }

        if (!str_contains(strtoupper($trimmed), 'BEGIN:VCALENDAR')) {
            throw new \RuntimeException('Uploaded file does not look like a valid iCalendar (.ics) document.');
        }

        return 'ics';
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create uploads directory: ' . $directory);
        }
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds the allowed size.',
            UPLOAD_ERR_PARTIAL => 'Upload was incomplete. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No calendar file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing a temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by a PHP extension.',
            default => 'Upload failed with error code ' . $error . '.',
        };
    }
}
