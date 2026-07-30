<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Services;

use Grav\Plugin\OpenCalendar\Dto\SourceConfig;

/**
 * Upserts a source row into the site's user plugin config file.
 *
 * Uses Grav YAML helpers when available; falls back to a minimal YAML emitter for tests.
 */
final class PluginSourcesWriter
{
    public function __construct(private readonly string $configFilePath)
    {
    }

    /**
     * @param array<string, mixed> $sourceRow
     * @return array{key: string, created: bool, sources: list<array<string, mixed>>}
     */
    public function upsertByName(array $sourceRow): array
    {
        $name = trim((string) ($sourceRow['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Source name must not be empty.');
        }

        $key = SourceConfig::slugify($name);
        $config = $this->readConfig();
        $sources = ConfigNormalizer::toArray($config['sources'] ?? []);
        $normalized = [];
        foreach ($sources as $row) {
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        $created = true;
        $replaced = false;
        foreach ($normalized as $index => $row) {
            $existingName = trim((string) ($row['name'] ?? ''));
            $existingKey = SourceConfig::slugify($existingName !== '' ? $existingName : 'source');
            if ($existingKey === $key || strcasecmp($existingName, $name) === 0) {
                $normalized[$index] = array_merge($row, $sourceRow, ['name' => $name]);
                $created = false;
                $replaced = true;
                break;
            }
        }

        if (!$replaced) {
            $normalized[] = $sourceRow;
        }

        $config['sources'] = array_values($normalized);
        if (!array_key_exists('enabled', $config)) {
            $config['enabled'] = true;
        }

        $this->writeConfig($config);

        return [
            'key' => $key,
            'created' => $created,
            'sources' => array_values($normalized),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfig(): array
    {
        if (!is_file($this->configFilePath)) {
            return [];
        }

        $raw = file_get_contents($this->configFilePath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        if (class_exists(\Grav\Common\Yaml::class)) {
            $parsed = \Grav\Common\Yaml::parse($raw);

            return is_array($parsed) ? $parsed : [];
        }

        if (function_exists('yaml_parse')) {
            $parsed = yaml_parse($raw);

            return is_array($parsed) ? $parsed : [];
        }

        throw new \RuntimeException(
            'Cannot update OpenCalendar sources: no YAML parser available to read existing config safely.'
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function writeConfig(array $config): void
    {
        $directory = dirname($this->configFilePath);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create config directory: ' . $directory);
        }

        if (class_exists(\Grav\Common\Yaml::class)) {
            $yaml = \Grav\Common\Yaml::dump($config);
        } elseif (function_exists('yaml_emit')) {
            $yaml = yaml_emit($config);
        } else {
            $yaml = $this->dumpSimpleYaml($config);
        }

        if (@file_put_contents($this->configFilePath, $yaml) === false) {
            throw new \RuntimeException('Unable to write plugin config: ' . $this->configFilePath);
        }
    }

    /**
     * Enough YAML for plugin source overlays when Grav helpers are unavailable.
     *
     * @param array<mixed, mixed> $data
     */
    private function dumpSimpleYaml(array $data, int $indent = 0): string
    {
        $pad = str_repeat('  ', $indent);
        $out = '';

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                if (is_array($value)) {
                    $out .= $pad . "-\n" . $this->dumpSimpleYaml($value, $indent + 1);
                } else {
                    $out .= $pad . '- ' . $this->yamlScalar($value) . "\n";
                }
                continue;
            }

            $name = (string) $key;
            if (is_array($value)) {
                if ($value === []) {
                    $out .= $pad . $name . ": []\n";
                } else {
                    $out .= $pad . $name . ":\n" . $this->dumpSimpleYaml($value, $indent + 1);
                }
            } else {
                $out .= $pad . $name . ': ' . $this->yamlScalar($value) . "\n";
            }
        }

        return $out;
    }

    private function yamlScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;
        if ($string === '') {
            return "''";
        }
        if (preg_match('/[\r\n#:{}[\],*&!?|>%@`\'"]/', $string) === 1 || preg_match('/^\s|\s$/', $string) === 1) {
            return "'" . str_replace("'", "''", $string) . "'";
        }

        return $string;
    }
}
