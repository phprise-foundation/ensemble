<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'issue_sequences')]
final class IssueSequenceRecord
{
    #[ORM\Id]
    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'next_number', type: 'integer')]
    private int $nextNumber;

    public function __construct(string $workspaceId, int $nextNumber)
    {
        $this->workspaceId = $workspaceId;
        $this->nextNumber = $nextNumber;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function nextNumber(): int
    {
        return $this->nextNumber;
    }

    public function advance(): void
    {
        $this->nextNumber++;
    }
}
