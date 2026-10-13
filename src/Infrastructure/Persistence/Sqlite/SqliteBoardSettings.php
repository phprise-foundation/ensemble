<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sqlite;

use App\Domain\Contract\BoardSettings;
use App\Domain\Workflow\Board;
use Override;
use PDO;

final class SqliteBoardSettings implements BoardSettings
{
    public function __construct(private PDO $pdo)
    {
    }

    #[Override]
    public function get(): Board
    {
        /** @var array{name: string, aging_hours: int|string} $row */
        $row = $this->pdo->query('SELECT name, aging_hours FROM board WHERE id = 1')->fetch();

        return new Board($row['name'], (int) $row['aging_hours']);
    }

    #[Override]
    public function save(Board $board): void
    {
        $statement = $this->pdo->prepare('UPDATE board SET name = ?, aging_hours = ? WHERE id = 1');
        $statement->execute([$board->name(), $board->agingHours()]);
    }
}
