<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Source;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;
use Grav\Plugin\OpenCalendar\Enum\SourceType;
use Grav\Plugin\OpenCalendar\Http\HttpClientInterface;

/**
 * Resolves SourceInterface implementations by source type.
 *
 * Registering a new source requires only adding a case here (or extending via DI).
 */
final class SourceFactory
{
    /** @var array<string, SourceInterface> */
    private array $sources = [];

    /**
     * @param iterable<SourceInterface> $sources
     */
    public function __construct(iterable $sources = [])
    {
        foreach ($sources as $source) {
            $this->register($source);
        }
    }

    public function register(SourceInterface $source): void
    {
        $this->sources[$source->getType()] = $source;
    }

    public function get(SourceType|string $type): SourceInterface
    {
        $key = $type instanceof SourceType ? $type->value : $type;

        if (!isset($this->sources[$key])) {
            throw new \InvalidArgumentException('Unsupported calendar source type: ' . $key);
        }

        return $this->sources[$key];
    }

    public function forConfig(SourceConfig $config): SourceInterface
    {
        return $this->get($config->type);
    }

    /**
     * @return list<string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->sources);
    }

    /**
     * @param array<string, mixed> $httpOptions
     * @param array<string, mixed> $importOptions
     */
    public static function createDefault(
        HttpClientInterface $http,
        array $httpOptions = [],
        array $importOptions = [],
        string $defaultTimezone = 'UTC',
        string $localBasePath = '',
    ): self {
        $parser = new IcsParser(
            defaultTimezone: $defaultTimezone,
            expandRecurring: (bool) ($importOptions['expand_recurring'] ?? true),
            recurringHorizonDays: (int) ($importOptions['recurring_horizon_days'] ?? 365),
            stripHtml: (bool) ($importOptions['strip_html'] ?? false),
        );

        return new self([
            new IcsSource($http, $parser, $httpOptions),
            new CalDavSource($http, $httpOptions),
            new JsonSource($http, $httpOptions),
            new LocalSource($http, $localBasePath, $httpOptions),
        ]);
    }
}
