<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Storage;

/**
 * Applies numbered SQL migrations and keeps schema_migrations in sync.
 */
final class Migrator
{
    public function __construct(
        private readonly Database $db,
        private readonly string $migrationsPath,
    ) {
    }

    public function migrate(): void
    {
        $pdo = $this->db->connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version INTEGER PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );

        $applied = [];
        foreach ($this->db->fetchAll('SELECT version FROM schema_migrations ORDER BY version') as $row) {
            $applied[(int) $row['version']] = true;
        }

        $files = glob(rtrim($this->migrationsPath, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $base = basename($file);
            if (!preg_match('/^(\d+)_/', $base, $m)) {
                continue;
            }

            $version = (int) $m[1];
            if (isset($applied[$version])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new \RuntimeException('Empty migration file: ' . $file);
            }

            $this->db->beginTransaction();
            try {
                $pdo->exec($sql);
                $this->db->execute(
                    'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)',
                    [
                        'version' => $version,
                        'applied_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                            ->format(\DateTimeInterface::ATOM),
                    ]
                );
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                throw new \RuntimeException('Migration failed: ' . $base . ' — ' . $e->getMessage(), 0, $e);
            }
        }
    }
}
