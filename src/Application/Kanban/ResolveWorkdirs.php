<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\Workdir;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ResolveWorkdirs
{
    public function __construct(
        private WorkdirRepository $workdirs,
        private WorkspaceGuard $guard,
    ) {
    }

    /**
     * @param list<Issue> $issues
     * @return array<string, Workdir>
     */
    public function execute(User $actor, WorkspaceId $workspaceId, array $issues): array
    {
        $this->guard->assertMember($actor, $workspaceId);
        $ids = [];
        foreach ($issues as $issue) {
            $id = $issue->workdirId();
            if ($id !== null) {
                $ids[$id->value()] = $id;
            }
        }

        /** @var array<string, Workdir> $resolved */
        $resolved = [];
        foreach ($this->workdirs->findMany($workspaceId, array_values($ids)) as $workdir) {
            $resolved[$workdir->id()->value()] = $workdir;
        }

        return $resolved;
    }
}
