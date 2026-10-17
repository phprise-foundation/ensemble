<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use PDO;

final class Schema
{
    public function apply(PDO $pdo): void
    {
        foreach ($this->statements() as $statement) {
            $pdo->exec($statement);
        }
        $this->ensureColumnRole($pdo);
    }

    /** @return list<string> */
    private function statements(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS board (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                name TEXT NOT NULL,
                aging_hours INTEGER NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS agents (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT \'\',
                active INTEGER NOT NULL DEFAULT 1,
                rule TEXT NOT NULL DEFAULT \'\',
                profile TEXT NOT NULL DEFAULT \'\',
                model TEXT NOT NULL DEFAULT \'\',
                fallback_model TEXT NOT NULL DEFAULT \'\',
                interval INTEGER NOT NULL DEFAULT 300,
                workspace TEXT NOT NULL DEFAULT \'/workspace\'
            )',
            'CREATE TABLE IF NOT EXISTS columns (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                position INTEGER NOT NULL,
                is_final INTEGER NOT NULL DEFAULT 0,
                role_id TEXT NULL REFERENCES agents(id) ON DELETE SET NULL
            )',
            'CREATE TABLE IF NOT EXISTS column_transitions (
                from_column_id TEXT NOT NULL REFERENCES columns(id) ON DELETE CASCADE,
                to_column_id TEXT NOT NULL REFERENCES columns(id) ON DELETE CASCADE,
                position INTEGER NOT NULL,
                PRIMARY KEY (from_column_id, to_column_id)
            )',
            'CREATE TABLE IF NOT EXISTS items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                column_id TEXT NOT NULL REFERENCES columns(id),
                agent_id TEXT NULL REFERENCES agents(id) ON DELETE SET NULL,
                title TEXT NOT NULL,
                body TEXT NOT NULL DEFAULT \'\',
                worker TEXT NOT NULL DEFAULT \'\',
                blocked INTEGER NOT NULL DEFAULT 0,
                blocked_by TEXT NOT NULL DEFAULT \'\',
                closed INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                entered_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS item_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER NOT NULL REFERENCES items(id),
                from_column_id TEXT NULL,
                to_column_id TEXT NOT NULL,
                actor TEXT NOT NULL DEFAULT \'\',
                created_at TEXT NOT NULL
            )',
            'CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER NOT NULL REFERENCES items(id),
                author TEXT NOT NULL,
                body TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
            'CREATE INDEX IF NOT EXISTS idx_item_events_item ON item_events(item_id, id)',
            'CREATE INDEX IF NOT EXISTS idx_comments_item ON comments(item_id, id)',
            'CREATE TABLE IF NOT EXISTS infractions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER NOT NULL REFERENCES items(id),
                infractor_agent TEXT NOT NULL,
                infractor_role TEXT NOT NULL DEFAULT \'\',
                infractor_model TEXT NOT NULL DEFAULT \'\',
                detected_by TEXT NOT NULL,
                cause TEXT NOT NULL,
                severity TEXT NOT NULL,
                evidence TEXT NOT NULL,
                blocked INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT \'declared\',
                adjudicated_by TEXT NOT NULL DEFAULT \'\',
                created_at TEXT NOT NULL,
                resolved_at TEXT NULL
            )',
            'CREATE INDEX IF NOT EXISTS idx_infractions_item ON infractions(item_id, id)',
        ];
    }

    private function ensureColumnRole(PDO $pdo): void
    {
        /** @var list<array<string, mixed>> $columns */
        $columns = $pdo->query('PRAGMA table_info(columns)')->fetchAll();
        foreach ($columns as $column) {
            if (($column['name'] ?? '') === 'role_id') {
                return;
            }
        }
        $pdo->exec('ALTER TABLE columns ADD COLUMN role_id TEXT REFERENCES agents(id)');
    }
}
