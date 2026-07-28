<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Http;

/**
 * cURL-based HTTP client used for ICS, CalDAV, and JSON sources.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function get(
        string $url,
        array $headers = [],
        array $auth = [],
        int $timeout = 30,
        bool $verifySsl = true,
        int $maxRedirects = 3,
        string $userAgent = 'OpenCalendar/1.0',
    ): HttpResponse {
        return $this->request('GET', $url, null, $headers, $auth, $timeout, $verifySsl, $maxRedirects, $userAgent);
    }

    public function request(
        string $method,
        string $url,
        ?string $body = null,
        array $headers = [],
        array $auth = [],
        int $timeout = 30,
        bool $verifySsl = true,
        int $maxRedirects = 3,
        string $userAgent = 'OpenCalendar/1.0',
    ): HttpResponse {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('The curl extension is required to fetch remote calendar sources.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize cURL.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];
        $method = strtoupper(trim($method));
        if ($method === '') {
            $method = 'GET';
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => max(0, $maxRedirects),
            CURLOPT_TIMEOUT => max(1, $timeout),
            CURLOPT_CONNECTTIMEOUT => max(1, min(15, $timeout)),
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $len = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }

                return $len;
            },
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        $authType = strtolower((string) ($auth['type'] ?? 'none'));
        if ($authType === 'basic') {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt(
                $ch,
                CURLOPT_USERPWD,
                (string) ($auth['username'] ?? '') . ':' . (string) ($auth['password'] ?? '')
            );
        } elseif ($authType === 'bearer' && ($auth['token'] ?? $auth['password'] ?? '') !== '') {
            $token = (string) (($auth['token'] ?? '') !== '' ? $auth['token'] : ($auth['password'] ?? ''));
            $headerLines[] = 'Authorization: Bearer ' . $token;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException('HTTP request failed: ' . $error, $errno);
        }

        if (!is_string($responseBody)) {
            $responseBody = '';
        }

        return new HttpResponse($status, $responseBody, $responseHeaders);
    }
}
