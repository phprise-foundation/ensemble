<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use PDO;

final class SqliteDatabase
{
    private ?PDO $pdo = null;

    public function __construct(private string $path)
    {
    }

    public function connection(): PDO
    {
        return $this->pdo ??= $this->open();
    }

    private function open(): PDO
    {
        $this->ensureDirectory();
        $pdo = new PDO('sqlite:' . $this->path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        (new Schema())->apply($pdo);
        (new Seed())->apply($pdo);

        return $pdo;
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
