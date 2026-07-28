<?php

declare(strict_types=1);

namespace Grav\Plugin\OpenCalendar\Storage;

/**
 * Thin PDO wrapper for the OpenCalendar SQLite database.
 */
final class Database
{
    private ?\PDO $pdo = null;

    public function __construct(
        private readonly string $path,
        private readonly bool $walMode = true,
    ) {
    }

    public function connection(): \PDO
    {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create database directory: ' . $dir);
        }

        $this->pdo = new \PDO('sqlite:' . $this->path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        if ($this->walMode) {
            $this->pdo->exec('PRAGMA journal_mode = WAL');
        }

        return $this->pdo;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function beginTransaction(): void
    {
        $this->connection()->beginTransaction();
    }

    public function commit(): void
    {
        if ($this->connection()->inTransaction()) {
            $this->connection()->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->connection()->inTransaction()) {
            $this->connection()->rollBack();
        }
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->execute($sql, $params)->fetchAll();
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->execute($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection()->lastInsertId();
    }

    public function vacuum(): void
    {
        $this->connection()->exec('VACUUM');
    }

    public function close(): void
    {
        $this->pdo = null;
    }
}
