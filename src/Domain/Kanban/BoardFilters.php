<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

final readonly class BoardFilters
{
    /** @var list<IssueTypeId> */
    private array $issueTypeIds;
    /** @var list<StatusId> */
    private array $statusIds;

    /**
     * @param list<IssueTypeId> $issueTypeIds
     * @param list<StatusId> $statusIds
     */
    public function __construct(array $issueTypeIds = [], array $statusIds = [])
    {
        $this->issueTypeIds = $issueTypeIds;
        $this->statusIds = $statusIds;
    }

    /** @return list<IssueTypeId> */
    public function issueTypeIds(): array
    {
        return $this->issueTypeIds;
    }

    /** @return list<StatusId> */
    public function statusIds(): array
    {
        return $this->statusIds;
    }

    public function isEmpty(): bool
    {
        return $this->issueTypeIds === [] && $this->statusIds === [];
    }
}
