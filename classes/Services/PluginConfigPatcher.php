<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Services;

/**
 * Reads/writes the site overlay at user/config/plugins/opencalendar.yaml.
 *
 * Patches only that file (never the plugin defaults) so GPM/user overlays stay lean.
 */
final class PluginConfigPatcher
{
    public function __construct(private readonly string $configFilePath)
    {
    }

    public function configFilePath(): string
    {
        return $this->configFilePath;
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mutator
     */
    public function patch(callable $mutator): bool
    {
        $config = $this->read();
        $updated = $mutator($config);
        if ($updated === $config) {
            return false;
        }

        $this->write($updated);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
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

        return $this->parseSimpleYaml($raw);
    }

    /**
     * Minimal YAML subset reader for tests / environments without Grav or ext-yaml.
     * Supports nested maps, simple lists, and scalars that dumpSimpleYaml emits.
     *
     * @return array<string, mixed>
     */
    private function parseSimpleYaml(string $raw): array
    {
        $lines = preg_split('/\R/', $raw) ?: [];
        $root = [];
        /** @var list<array{indent: int, ref: array<mixed>}> $stack */
        $stack = [['indent' => -1, 'ref' => &$root]];

        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (!preg_match('/^( *)(.+)$/', $line, $m)) {
                continue;
            }

            $indent = strlen($m[1]);
            $content = $m[2];

            while (count($stack) > 1 && $indent <= $stack[count($stack) - 1]['indent']) {
                array_pop($stack);
            }

            $parent = &$stack[count($stack) - 1]['ref'];

            if (str_starts_with($content, '- ')) {
                $value = $this->parseYamlScalar(trim(substr($content, 2)));
                $parent[] = $value;
                continue;
            }

            if ($content === '-') {
                $child = [];
                $parent[] = &$child;
                $stack[] = ['indent' => $indent, 'ref' => &$child];
                unset($child);
                continue;
            }

            if (!preg_match('/^([^:]+):\s*(.*)$/', $content, $km)) {
                continue;
            }

            $key = rtrim($km[1]);
            $rest = $km[2];
            if ($rest === '') {
                $child = [];
                $parent[$key] = &$child;
                $stack[] = ['indent' => $indent, 'ref' => &$child];
                unset($child);
            } elseif ($rest === '[]') {
                $parent[$key] = [];
            } else {
                $parent[$key] = $this->parseYamlScalar($rest);
            }
        }

        /** @var array<string, mixed> $root */
        return $root;
    }

    private function parseYamlScalar(string $value): mixed
    {
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if ($value === 'null' || $value === '~') {
            return null;
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        if (
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
            || (str_starts_with($value, '"') && str_ends_with($value, '"'))
        ) {
            $inner = substr($value, 1, -1);

            return str_replace("''", "'", $inner);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function write(array $config): void
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
