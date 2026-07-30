<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Tests\Unit\Services;

use Grav\Plugin\OpenCalendar\Services\CalendarUploadService;
use Grav\Plugin\OpenCalendar\Services\PluginSourcesWriter;
use PHPUnit\Framework\TestCase;

final class CalendarUploadServiceTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/oc-upload-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/uploads', 0777, true);
        mkdir($this->tmpDir . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $file->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($this->tmpDir);
    }

    public function testStoresValidIcsAndBuildsLocalSourceRow(): void
    {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:1\r\nDTSTART:20260801T100000Z\r\nDTEND:20260801T110000Z\r\nSUMMARY:Drill\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        $tmp = $this->tmpDir . '/incoming.ics';
        file_put_contents($tmp, $ics);

        $service = new CalendarUploadService($this->tmpDir . '/uploads');
        $stored = $service->storeUploadedFile([
            'name' => 'Club Calendar.ics',
            'type' => 'text/calendar',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($ics),
        ]);

        self::assertFileExists($stored['path']);
        self::assertSame('ics', $stored['format']);
        self::assertStringStartsWith('uploads/', $stored['relative_url']);

        $row = $service->buildSourceRow('Club Calendar', $stored['relative_url']);
        self::assertSame('local', $row['type']);
        self::assertSame($stored['relative_url'], $row['url']);
        self::assertTrue($row['enabled']);
    }

    public function testRejectsNonCalendarPayload(): void
    {
        $tmp = $this->tmpDir . '/bad.ics';
        file_put_contents($tmp, 'not a calendar');

        $service = new CalendarUploadService($this->tmpDir . '/uploads');

        $this->expectException(\RuntimeException::class);
        $service->storeUploadedFile([
            'name' => 'bad.ics',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => 14,
        ]);
    }

    public function testSourcesWriterUpsertsByName(): void
    {
        if (!function_exists('yaml_parse') && !class_exists(\Grav\Common\Yaml::class)) {
            // Writer can still create a new file via dumpSimpleYaml when file does not exist.
            $configPath = $this->tmpDir . '/config/opencalendar.yaml';
            $writer = new PluginSourcesWriter($configPath);
            $first = $writer->upsertByName([
                'name' => 'Uploaded',
                'enabled' => true,
                'type' => 'local',
                'url' => 'uploads/a.ics',
                'refresh' => 'inherit',
            ]);
            self::assertTrue($first['created']);
            self::assertSame('uploaded', $first['key']);
            self::assertFileExists($configPath);

            // Second upsert needs a parser — skip if unavailable.
            if (!function_exists('yaml_parse')) {
                self::assertStringContainsString('uploads/a.ics', (string) file_get_contents($configPath));

                return;
            }

            $second = $writer->upsertByName([
                'name' => 'Uploaded',
                'enabled' => true,
                'type' => 'local',
                'url' => 'uploads/b.ics',
                'refresh' => 'inherit',
            ]);
            self::assertFalse($second['created']);
            self::assertCount(1, $second['sources']);
            self::assertSame('uploads/b.ics', $second['sources'][0]['url']);

            return;
        }

        $configPath = $this->tmpDir . '/config/opencalendar.yaml';
        $writer = new PluginSourcesWriter($configPath);
        $first = $writer->upsertByName([
            'name' => 'Uploaded',
            'enabled' => true,
            'type' => 'local',
            'url' => 'uploads/a.ics',
            'refresh' => 'inherit',
        ]);
        $second = $writer->upsertByName([
            'name' => 'Uploaded',
            'enabled' => true,
            'type' => 'local',
            'url' => 'uploads/b.ics',
            'refresh' => 'inherit',
        ]);

        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertCount(1, $second['sources']);
        self::assertSame('uploads/b.ics', $second['sources'][0]['url']);
    }
}
