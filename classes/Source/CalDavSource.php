<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\FetchResult;
use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;
use Grav\Plugin\OpenCalendar\Models\Event;

/**
 * CalDAV calendar source using calendar-query REPORT and IcsParser.
 */
final class CalDavSource extends AbstractSource
{
    public function __construct(
        HttpClientInterface $http,
        private readonly IcsParser $parser,
        array $httpOptions = [],
        private readonly int $horizonDays = 365,
    ) {
        parent::__construct($http, $httpOptions);
    }

    public function getType(): string
    {
        return SourceType::CalDav->value;
    }

    public function fetch(
        SourceConfig $config,
        ?string $etag = null,
        ?string $lastModified = null,
        ?string $contentHash = null,
    ): FetchResult {
        if ($config->url === '') {
            throw new \InvalidArgumentException('CalDAV source URL must not be empty.');
        }

        $url = rtrim($config->url, '/') . '/';
        $body = $this->buildCalendarQueryXml();
        $headers = [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Depth' => '1',
            'Prefer' => 'return-minimal',
        ];
        if ($etag !== null && $etag !== '') {
            $headers['If-None-Match'] = $etag;
        }

        $response = $this->http->request(
            'REPORT',
            $url,
            $body,
            $headers,
            $config->auth,
            (int) ($this->httpOptions['timeout'] ?? 30),
            (bool) ($this->httpOptions['verify_ssl'] ?? true),
            (int) ($this->httpOptions['max_redirects'] ?? 3),
            (string) ($this->httpOptions['user_agent'] ?? 'OpenCalendar/1.0 Grav Plugin'),
        );

        if ($response->statusCode === 304) {
            return FetchResult::notModified(
                304,
                $response->header('ETag') ?? $etag,
                $response->header('Last-Modified') ?? $lastModified,
                $contentHash ?? '',
            );
        }

        // Some servers expose a plain ICS download on GET; fall back when REPORT is unsupported.
        if (in_array($response->statusCode, [404, 405, 501], true)) {
            return $this->fetchHttp($config, $etag, $lastModified, [
                'Accept' => 'text/calendar, application/calendar+json, */*',
            ]);
        }

        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new \RuntimeException(
                sprintf('CalDAV source "%s" returned HTTP %d', $config->name, $response->statusCode),
                $response->statusCode
            );
        }

        $ics = $this->multistatusToIcs($response->body);
        $hash = hash('sha256', $ics);
        if ($contentHash !== null && $contentHash !== '' && $hash === $contentHash) {
            return FetchResult::notModified(
                $response->statusCode,
                $response->header('ETag') ?? $etag,
                $response->header('Last-Modified') ?? $lastModified,
                $contentHash,
            );
        }

        return new FetchResult(
            body: $ics,
            httpStatus: $response->statusCode,
            etag: $response->header('ETag'),
            lastModified: $response->header('Last-Modified'),
            notModified: false,
            contentHash: $hash,
        );
    }

    /**
     * @return list<Event>
     */
    public function parse(string $payload, SourceConfig $config, int $calendarId = 0): array
    {
        $trimmed = ltrim($payload);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            throw new \RuntimeException('CalDAV payload looks like JSON; expected iCalendar data.');
        }

        return $this->parser->parse($payload, $config, $calendarId);
    }

    private function buildCalendarQueryXml(): string
    {
        $days = max(1, $this->horizonDays);
        $start = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $start = $start->modify('-30 days');
        $end = $start->modify('+' . ($days + 30) . ' days');

        return '<?xml version="1.0" encoding="utf-8" ?>'
            . '<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">'
            . '<D:prop><D:getetag/><C:calendar-data/></D:prop>'
            . '<C:filter><C:comp-filter name="VCALENDAR"><C:comp-filter name="VEVENT">'
            . '<C:time-range start="' . $start->format('Ymd\THis\Z') . '" end="' . $end->format('Ymd\THis\Z') . '"/>'
            . '</C:comp-filter></C:comp-filter></C:filter>'
            . '</C:calendar-query>';
    }

    private function multistatusToIcs(string $xml): string
    {
        $xml = trim($xml);
        if ($xml === '') {
            return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//OpenCalendar//CalDAV//EN\r\nEND:VCALENDAR\r\n";
        }

        // Already a calendar document (GET fallback).
        if (preg_match('/^\s*BEGIN:VCALENDAR/i', $xml) === 1) {
            return $xml;
        }

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            throw new \RuntimeException('Invalid CalDAV multistatus XML response.');
        }

        $document->registerXPathNamespace('D', 'DAV:');
        $document->registerXPathNamespace('C', 'urn:ietf:params:xml:ns:caldav');
        $nodes = $document->xpath('//C:calendar-data') ?: [];

        $chunks = [];
        foreach ($nodes as $node) {
            $data = trim(html_entity_decode((string) $node, ENT_QUOTES | ENT_XML1));
            if ($data === '') {
                continue;
            }
            $chunks[] = $data;
        }

        if ($chunks === []) {
            return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//OpenCalendar//CalDAV//EN\r\nEND:VCALENDAR\r\n";
        }

        $vevents = [];
        $timezoneBlocks = [];
        foreach ($chunks as $chunk) {
            if (stripos($chunk, 'BEGIN:VCALENDAR') !== false) {
                if (preg_match_all('/BEGIN:VTIMEZONE.*?END:VTIMEZONE/si', $chunk, $tzMatches) > 0) {
                    foreach ($tzMatches[0] as $block) {
                        $timezoneBlocks[$block] = $block;
                    }
                }
                if (preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/si', $chunk, $eventMatches) > 0) {
                    foreach ($eventMatches[0] as $event) {
                        $vevents[] = $event;
                    }
                }
                continue;
            }

            if (stripos($chunk, 'BEGIN:VEVENT') !== false) {
                $vevents[] = $chunk;
            }
        }

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//OpenCalendar//CalDAV//EN\r\n";
        foreach ($timezoneBlocks as $block) {
            $ics .= rtrim($block) . "\r\n";
        }
        foreach ($vevents as $event) {
            $ics .= rtrim($event) . "\r\n";
        }
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }
}
