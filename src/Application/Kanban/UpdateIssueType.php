<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Level;
use App\Domain\Kanban\Position;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateIssueType
{
    public function __construct(
        private IssueTypeRepository $issueTypes,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        IssueTypeId $id,
        ?string $name,
        ?Level $level,
        ?Position $position,
    ): IssueType {
        $this->guard->assertManager($actor, $workspaceId);
        $issueType = $this->issueTypes->find($workspaceId, $id) ?? throw NotFoundException::issueType();
        if ($name !== null) {
            $issueType->rename($name);
        }
        if ($level !== null) {
            $issueType->changeLevel($level);
        }
        if ($position !== null) {
            $issueType->placeAt($position);
        }
        $issueType->touch(new DateTimeImmutable());
        $this->issueTypes->update($issueType);

        return $issueType;
    }
}
