<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Kanban\Workdir;
use App\Domain\Kanban\WorkdirId;
use App\Domain\Kanban\WorkdirKind;

final readonly class BackfillIssueWorkdirs
{
    public function __construct(
        private IssueRepository $issues,
        private WorkdirRepository $workdirs,
    ) {
    }

    public function execute(): int
    {
        $count = 0;
        foreach ($this->issues->withoutWorkdir() as $issue) {
            $workdir = new Workdir(
                WorkdirId::generate(),
                $issue->workspaceId(),
                $issue->id(),
                WorkdirKind::Scratch,
            );
            $this->workdirs->add($workdir);
            $issue->assignWorkdir($workdir->id());
            $this->issues->update($issue);
            $count++;
        }

        return $count;
    }
}
