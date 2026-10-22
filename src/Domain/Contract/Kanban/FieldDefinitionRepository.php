<?php

declare(strict_types=1);

namespace App\Domain\Contract\Kanban;

use App\Domain\Kanban\FieldDefinition;
use App\Domain\Kanban\FieldDefinitionId;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Slug;
use App\Domain\Tenancy\WorkspaceId;

interface FieldDefinitionRepository
{
    public function add(FieldDefinition $field): void;

    public function update(FieldDefinition $field): void;

    public function delete(FieldDefinition $field): void;

    public function find(WorkspaceId $workspaceId, FieldDefinitionId $id): ?FieldDefinition;

    public function findByKey(IssueTypeId $issueTypeId, Slug $key): ?FieldDefinition;

    /** @return list<FieldDefinition> */
    public function forIssueType(WorkspaceId $workspaceId, IssueTypeId $issueTypeId): array;

    public function countForIssueType(IssueTypeId $issueTypeId): int;
}
