<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Services;

use Grav\Plugin\OpenCalendar\Services\Container;
use Grav\Plugin\OpenCalendar\Services\PluginConfigPatcher;
use Grav\Plugin\OpenCalendar\Services\RuntimeDataMigrator;
use PHPUnit\Framework\TestCase;

final class RuntimeDataMigratorTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/oc-migrate-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/plugin/data', 0777, true);
        mkdir($this->root . '/user-data', 0777, true);
        mkdir($this->root . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testMovesSqliteAndCalendarFilesOutOfPlugin(): void
    {
        $plugin = $this->root . '/plugin';
        $userData = $this->root . '/user-data';

        file_put_contents($plugin . '/data/opencalendar.db', 'db');
        file_put_contents($plugin . '/data/opencalendar.db-wal', 'wal');
        file_put_contents($plugin . '/data/station.ics', "BEGIN:VCALENDAR\nEND:VCALENDAR\n");

        // First test only checks file moves + config rewrite (no SQLite open).
        $migrator = new RuntimeDataMigrator($plugin, $userData);
        $result = $migrator->migrate([
            'storage' => ['path' => 'data/opencalendar.db'],
            'sources' => [
                [
                    'name' => 'Station',
                    'type' => 'local',
                    'url' => 'data/station.ics',
                    'enabled' => true,
                ],
            ],
        ]);

        self::assertTrue($result['migrated']);
        self::assertTrue($result['storage_rewritten']);
        self::assertSame(1, $result['sources_rewritten']);
        self::assertSame(
            'user-data://opencalendar/opencalendar.db',
            $result['config']['storage']['path']
        );
        self::assertSame('station.ics', $result['config']['sources'][0]['url']);
        self::assertFileExists($userData . '/opencalendar/opencalendar.db');
        self::assertFileExists($userData . '/opencalendar/opencalendar.db-wal');
        self::assertFileExists($userData . '/opencalendar/station.ics');
        self::assertFileDoesNotExist($plugin . '/data/opencalendar.db');
        self::assertFileDoesNotExist($plugin . '/data/station.ics');
    }

    public function testContainerResolvesRelativeStorageUnderUserData(): void
    {
        $plugin = $this->root . '/plugin';
        $userData = $this->root . '/user-data';

        $container = new Container(
            config: ['storage' => ['path' => 'data/opencalendar.db']],
            pluginPath: $plugin,
            userDataPath: $userData,
        );
        $container->boot();

        self::assertSame(
            $userData . '/opencalendar/opencalendar.db',
            $container->database()->path()
        );
        self::assertSame(
            [$userData . '/opencalendar'],
            $container->localSourceBases()
        );
        self::assertNotContains($plugin, $container->localSourceBases());
    }

    public function testConfigPatcherPersistsCanonicalStoragePath(): void
    {
        $configFile = $this->root . '/config/opencalendar.yaml';
        file_put_contents($configFile, "enabled: true\nstorage:\n  path: data/opencalendar.db\n");

        $plugin = $this->root . '/plugin';
        $userData = $this->root . '/user-data';
        $pdo = new \PDO('sqlite:' . $plugin . '/data/opencalendar.db');
        $pdo->exec('CREATE TABLE probe (id INTEGER PRIMARY KEY)');
        $pdo = null;

        $container = new Container(
            config: [
                'enabled' => true,
                'storage' => ['path' => 'data/opencalendar.db', 'wal_mode' => false],
            ],
            pluginPath: $plugin,
            userDataPath: $userData,
            configFilePath: $configFile,
        );
        $container->boot();

        $patcher = new PluginConfigPatcher($configFile);
        $saved = $patcher->read();
        self::assertSame('user-data://opencalendar/opencalendar.db', $saved['storage']['path']);
        self::assertFileExists($userData . '/opencalendar/opencalendar.db');
        self::assertFileDoesNotExist($plugin . '/data/opencalendar.db');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($path);
    }
}
