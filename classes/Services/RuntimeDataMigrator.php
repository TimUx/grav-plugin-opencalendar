<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Services;

use Grav\Plugin\OpenCalendar\Logging\LoggerInterface;
use Grav\Plugin\OpenCalendar\Logging\NullLogger;

/**
 * Moves runtime files out of the plugin tree into Grav user/data.
 *
 * Grav GPM uninstall/update only removes packaged files, then rmdir()s the plugin
 * directory. Any leftover custom files (SQLite, local ICS/JSON) cause
 * "Directory not empty" and block reinstall. Keeping the plugin tree read-only
 * software-only prevents that.
 */
final class RuntimeDataMigrator
{
    private const CANONICAL_STORAGE = 'user-data://opencalendar/opencalendar.db';

    /** @var list<string> */
    private const DB_SUFFIXES = ['', '-wal', '-shm', '-journal'];

    public function __construct(
        private readonly string $pluginPath,
        private readonly string $userDataRoot,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function userCalendarRoot(): string
    {
        return rtrim($this->userDataRoot, '/\\') . '/opencalendar';
    }

    public function pluginDataRoot(): string
    {
        return rtrim($this->pluginPath, '/\\') . '/data';
    }

    /**
     * @param array<string, mixed> $config Merged or overlay plugin config
     * @return array{
     *   config: array<string, mixed>,
     *   migrated: bool,
     *   moved: list<string>,
     *   storage_rewritten: bool,
     *   sources_rewritten: int
     * }
     */
    public function migrate(array $config): array
    {
        $moved = [];
        $storageRewritten = false;
        $sourcesRewritten = 0;

        $targetRoot = $this->userCalendarRoot();
        $this->ensureDirectory($targetRoot);

        $configured = trim((string) ($config['storage']['path'] ?? ''));
        if ($configured === '') {
            $configured = self::CANONICAL_STORAGE;
        }

        $legacyDb = $this->resolveLegacyStoragePath($configured);
        if ($legacyDb !== null && is_file($legacyDb)) {
            $destDb = $targetRoot . '/opencalendar.db';
            foreach ($this->moveSqliteFamily($legacyDb, $destDb) as $path) {
                $moved[] = $path;
            }
            if ($configured !== self::CANONICAL_STORAGE) {
                $config = $this->withStoragePath($config, self::CANONICAL_STORAGE);
                $storageRewritten = true;
            }
        } elseif ($this->isPluginRelativeStorage($configured) || $this->isPathInsidePlugin($configured)) {
            $config = $this->withStoragePath($config, self::CANONICAL_STORAGE);
            $storageRewritten = true;
        }

        $urlMap = [];
        $pluginData = $this->pluginDataRoot();
        if (is_dir($pluginData)) {
            foreach ($this->listFilesRecursive($pluginData) as $file) {
                $base = basename($file);
                if ($base === '.gitkeep') {
                    continue;
                }

                // SQLite sidecars are moved with their primary .db via moveSqliteFamily().
                if ($this->isSqliteSidecarFile($file)) {
                    continue;
                }

                // Stray primary DB files under plugin/data (storage.path already handled above).
                if ($this->isSqlitePrimaryFile($file)) {
                    $destDb = $targetRoot . '/opencalendar.db';
                    if (!is_file($destDb)) {
                        foreach ($this->moveSqliteFamily($file, $destDb) as $path) {
                            $moved[] = $path;
                        }
                    } else {
                        $relative = $this->relativeFrom($pluginData, $file);
                        $dest = $targetRoot . '/files/' . $relative;
                        if ($this->moveFile($file, $dest)) {
                            $moved[] = $file . ' => ' . $dest;
                        }
                        foreach (self::DB_SUFFIXES as $suffix) {
                            if ($suffix === '') {
                                continue;
                            }
                            $sidecar = $file . $suffix;
                            if (is_file($sidecar)) {
                                @unlink($sidecar);
                            }
                        }
                    }
                    continue;
                }

                $relative = $this->relativeFrom($pluginData, $file);
                $dest = $targetRoot . '/' . $relative;
                if ($this->moveFile($file, $dest)) {
                    $moved[] = $file . ' => ' . $dest;
                    $oldUrl = 'data/' . str_replace('\\', '/', $relative);
                    $newUrl = str_replace('\\', '/', $relative);
                    $urlMap[$oldUrl] = $newUrl;
                    $urlMap[str_replace('\\', '/', $file)] = $newUrl;
                    $real = realpath($dest);
                    if (is_string($real) && $real !== '') {
                        $urlMap[$real] = $newUrl;
                    }
                }
            }

            $this->removeEmptyDirectories($pluginData);
        }

        // Drop orphan SQLite sidecars left behind under the plugin tree.
        $this->purgeOrphanSqliteSidecars($this->pluginDataRoot());

        if ($urlMap !== []) {
            $result = $this->rewriteLocalSourceUrls($config, $urlMap);
            $config = $result['config'];
            $sourcesRewritten = $result['count'];
        }

        // Rewrite remaining local sources that still point at the plugin tree.
        $result = $this->rewritePluginLocalSources($config);
        $config = $result['config'];
        $sourcesRewritten += $result['count'];

        $migrated = $moved !== [] || $storageRewritten || $sourcesRewritten > 0;
        if ($migrated) {
            $this->logger->info('OpenCalendar migrated runtime data out of the plugin directory.', [
                'moved' => count($moved),
                'storage_rewritten' => $storageRewritten,
                'sources_rewritten' => $sourcesRewritten,
            ]);
        }

        return [
            'config' => $config,
            'migrated' => $migrated,
            'moved' => array_values(array_unique($moved)),
            'storage_rewritten' => $storageRewritten,
            'sources_rewritten' => $sourcesRewritten,
        ];
    }

    /**
     * Resolve a configured storage path that still lives under the plugin tree.
     */
    public function resolveLegacyStoragePath(string $configured): ?string
    {
        $configured = trim($configured);
        if ($configured === '' || str_starts_with($configured, 'user-data://')) {
            return null;
        }

        if ($this->isAbsolutePath($configured)) {
            return $this->isPathInsidePlugin($configured) ? $configured : null;
        }

        // Legacy relative paths were resolved against the plugin root.
        $candidate = rtrim($this->pluginPath, '/\\') . '/' . ltrim(str_replace(['/', '\\'], '/', $configured), '/');
        if (is_file($candidate) || is_file($candidate . '-wal')) {
            return $candidate;
        }

        // Common historical default.
        $fallback = $this->pluginDataRoot() . '/opencalendar.db';
        $looksLikeLegacyDb = $configured === 'data/opencalendar.db'
            || str_ends_with($configured, 'opencalendar.db');
        if ($looksLikeLegacyDb && is_file($fallback)) {
            return $fallback;
        }

        return null;
    }

    public function isPathInsidePlugin(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }

        if (str_starts_with($path, 'file://')) {
            $path = substr($path, 7);
        }

        $plugin = realpath($this->pluginPath) ?: rtrim(str_replace('\\', '/', $this->pluginPath), '/');
        $normalizedPlugin = rtrim(str_replace('\\', '/', $plugin), '/') . '/';

        if ($this->isAbsolutePath($path)) {
            $resolved = realpath($path) ?: $path;
            $normalized = str_replace('\\', '/', $resolved);

            return str_starts_with($normalized, $normalizedPlugin)
                || $normalized === rtrim($normalizedPlugin, '/');
        }

        // Relative paths historically meant "under the plugin".
        return true;
    }

    public function isPluginRelativeStorage(string $configured): bool
    {
        $configured = trim($configured);
        if ($configured === '' || str_starts_with($configured, 'user-data://')) {
            return false;
        }

        if ($this->isAbsolutePath($configured)) {
            return $this->isPathInsidePlugin($configured);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function withStoragePath(array $config, string $path): array
    {
        if (!isset($config['storage']) || !is_array($config['storage'])) {
            $config['storage'] = [];
        }
        $config['storage']['path'] = $path;

        return $config;
    }

    /**
     * @param array<string, string> $urlMap
     * @param array<string, mixed> $config
     * @return array{config: array<string, mixed>, count: int}
     */
    private function rewriteLocalSourceUrls(array $config, array $urlMap): array
    {
        $sources = $config['sources'] ?? null;
        if (!is_array($sources)) {
            return ['config' => $config, 'count' => 0];
        }

        $count = 0;
        foreach ($sources as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (strtolower((string) ($row['type'] ?? '')) !== 'local') {
                continue;
            }

            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $lookup = $url;
            if (str_starts_with($lookup, 'file://')) {
                $lookup = substr($lookup, 7);
            }

            if (isset($urlMap[$lookup])) {
                $sources[$index]['url'] = $urlMap[$lookup];
                $count++;
                continue;
            }

            $normalized = str_replace('\\', '/', $lookup);
            if (isset($urlMap[$normalized])) {
                $sources[$index]['url'] = $urlMap[$normalized];
                $count++;
            }
        }

        $config['sources'] = $sources;

        return ['config' => $config, 'count' => $count];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{config: array<string, mixed>, count: int}
     */
    private function rewritePluginLocalSources(array $config): array
    {
        $sources = $config['sources'] ?? null;
        if (!is_array($sources)) {
            return ['config' => $config, 'count' => 0];
        }

        $pluginData = $this->pluginDataRoot();
        $count = 0;

        foreach ($sources as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (strtolower((string) ($row['type'] ?? '')) !== 'local') {
                continue;
            }

            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $path = str_starts_with($url, 'file://') ? substr($url, 7) : $url;

            if (str_starts_with(str_replace('\\', '/', $path), 'data/')) {
                $sources[$index]['url'] = ltrim(substr(str_replace('\\', '/', $path), strlen('data/')), '/');
                $count++;
                continue;
            }

            if ($this->isAbsolutePath($path) && $this->isPathInsidePlugin($path)) {
                $relative = $this->relativeFrom($pluginData, $path);
                $pathNorm = str_replace('\\', '/', $path);
                $dataNorm = str_replace('\\', '/', $pluginData);
                $underData = str_starts_with($pathNorm, $dataNorm);
                if ($relative !== basename($path) || $underData) {
                    $sources[$index]['url'] = str_replace('\\', '/', $relative);
                } else {
                    $sources[$index]['url'] = basename($path);
                }
                $count++;
            }
        }

        $config['sources'] = $sources;

        return ['config' => $config, 'count' => $count];
    }

    /**
     * @return list<string>
     */
    private function moveSqliteFamily(string $sourceDb, string $destDb): array
    {
        $moved = [];
        $this->ensureDirectory(dirname($destDb));

        foreach (self::DB_SUFFIXES as $suffix) {
            $src = $sourceDb . $suffix;
            if (!is_file($src)) {
                continue;
            }
            $dest = $destDb . $suffix;
            if (is_file($dest) && realpath($src) === realpath($dest)) {
                continue;
            }
            if (is_file($dest) && $suffix === '') {
                // Keep existing user-data DB; drop legacy copy after backup name.
                $backup = $dest . '.legacy-plugin-' . gmdate('YmdHis');
                if (@rename($src, $backup) || (@copy($src, $backup) && @unlink($src))) {
                    $moved[] = $src . ' => ' . $backup;
                }
                continue;
            }
            if ($this->moveFile($src, $dest)) {
                $moved[] = $src . ' => ' . $dest;
            }
        }

        return $moved;
    }

    private function moveFile(string $source, string $destination): bool
    {
        if (!is_file($source)) {
            return false;
        }

        if (is_file($destination)) {
            $srcReal = realpath($source);
            $dstReal = realpath($destination);
            if ($srcReal !== false && $srcReal === $dstReal) {
                return false;
            }
            // Prefer keeping the destination; remove the plugin copy.
            return @unlink($source);
        }

        $this->ensureDirectory(dirname($destination));
        if (@rename($source, $destination)) {
            return true;
        }
        if (@copy($source, $destination) && @unlink($source)) {
            return true;
        }

        $this->logger->warning('OpenCalendar could not move runtime file out of the plugin directory.', [
            'source' => $source,
            'destination' => $destination,
        ]);

        return false;
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create OpenCalendar data directory: ' . $directory);
        }
    }

    /**
     * @return list<string>
     */
    private function listFilesRecursive(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function removeEmptyDirectories(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            }
        }

        // Keep packaged data/.gitkeep if present; remove empty runtime dirs only.
        $children = scandir($directory) ?: [];
        $leftover = array_values(array_filter(
            $children,
            static fn (string $name): bool => $name !== '.' && $name !== '..' && $name !== '.gitkeep'
        ));
        if ($leftover === []) {
            // Leave the directory if .gitkeep exists (packaged); otherwise remove.
            if (!is_file($directory . '/.gitkeep')) {
                @rmdir($directory);
            }
        }
    }

    private function relativeFrom(string $base, string $path): string
    {
        $baseNorm = rtrim(str_replace('\\', '/', $base), '/') . '/';
        $pathNorm = str_replace('\\', '/', $path);
        if (str_starts_with($pathNorm, $baseNorm)) {
            return ltrim(substr($pathNorm, strlen($baseNorm)), '/');
        }

        return basename($path);
    }

    private function purgeOrphanSqliteSidecars(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach ($this->listFilesRecursive($directory) as $file) {
            if ($this->isSqliteSidecarFile($file)) {
                @unlink($file);
            }
        }

        $this->removeEmptyDirectories($directory);
    }

    private function isSqlitePrimaryFile(string $path): bool
    {
        $base = basename($path);

        return str_ends_with($base, '.db') || str_ends_with($base, '.sqlite');
    }

    private function isSqliteSidecarFile(string $path): bool
    {
        $base = basename($path);

        foreach (['-wal', '-shm', '-journal'] as $suffix) {
            if (str_ends_with($base, '.db' . $suffix) || str_ends_with($base, '.sqlite' . $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return strlen($path) > 2
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '\\' || $path[2] === '/');
    }
}
