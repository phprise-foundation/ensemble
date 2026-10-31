<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Application\Kanban\ReadIssue;
use App\Domain\Contract\Runner\RunRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\IssueKey;
use App\Domain\Runner\Run;
use App\Domain\Runner\RunId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Team\AgentId;

final readonly class RequestRun
{
    public function __construct(
        private ReadIssue $issues,
        private RunRepository $runs,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, IssueKey $key): Run
    {
        $issue = $this->issues->execute($actor, $workspaceId, $key);
        if ($issue->readyToMerge()) {
            throw new ConflictException('the issue is ready to merge; merge it instead of rerunning');
        }

        $assignee = $issue->assigneeId()
            ?? throw new ValidationException('assigneeId', 'Assign an agent before running this issue.');

        if ($this->runs->activeForIssue($issue->id()) !== null) {
            throw new ConflictException('the issue already has an active run');
        }

        $run = new Run(
            RunId::generate(),
            $workspaceId,
            new AgentId($assignee),
            $issue->id(),
        );
        $this->runs->add($run);

        return $run;
    }
}
