<?php

declare(strict_types=1);

namespace App\Domain\Board;

use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;

final readonly class ItemEvent
{
    public function __construct(
        private ?ColumnId $from,
        private ColumnId $to,
        private string $actor,
        private DateTimeImmutable $at,
    ) {
    }

    public function from(): ?ColumnId
    {
        return $this->from;
    }

    public function to(): ColumnId
    {
        return $this->to;
    }

    public function actor(): string
    {
        return $this->actor;
    }

    public function at(): DateTimeImmutable
    {
        return $this->at;
    }
}
