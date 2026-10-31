<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Domain\Contract\Kanban\IssueCommentRepository;
use App\Domain\Contract\Kanban\IssueEventRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueComment;
use App\Domain\Kanban\IssueCommentId;
use App\Domain\Kanban\IssueEvent;
use App\Domain\Kanban\IssueEventId;
use App\Domain\Kanban\IssueEventType;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReportStage
{
    public function __construct(
        private IssueRepository $issues,
        private IssueCommentRepository $comments,
        private IssueEventRepository $events,
        private AgentRepository $agents,
        private RoleRepository $roles,
    ) {
    }

    public function failed(Issue $issue, string $summary, string $detail): void
    {
        $unblocker = $this->unblockerOf($issue->workspaceId());
        $issue->block(true);
        if ($unblocker !== null) {
            $issue->assign($unblocker);
        }
        $this->issues->update($issue);

        $mention = $unblocker === null ? '' : "\n@" . $this->agentName($issue->workspaceId(), $unblocker) . ' please unblock.';
        $body = mb_substr('Run failed: ' . $summary . ($detail === '' ? '' : "\n" . $detail), 0, 900) . $mention;
        $this->comment($issue, $body, 'Run failed: ' . $summary);
    }

    public function readyToMerge(Issue $issue, string $branch): void
    {
        $this->comment(
            $issue,
            'QA passed on ' . $branch . '. Ready to merge; the branch stays until the merge.',
            'QA passed',
        );
    }

    private function comment(Issue $issue, string $body, string $summary): void
    {
        $this->comments->add(new IssueComment(
            IssueCommentId::generate(),
            $issue->workspaceId(),
            $issue->id(),
            'runner',
            mb_substr($body, 0, 1023),
        ));
        $this->events->add(new IssueEvent(
            IssueEventId::generate(),
            $issue->workspaceId(),
            $issue->id(),
            IssueEventType::Comment,
            null,
            null,
            'runner',
            mb_substr($summary, 0, 140),
        ));
    }

    private function unblockerOf(WorkspaceId $workspaceId): ?string
    {
        foreach ($this->agents->forWorkspace($workspaceId) as $agent) {
            if (! $agent->active()) {
                continue;
            }

            foreach ($agent->roleIds()->all() as $roleId) {
                $role = $this->roles->find($workspaceId, $roleId);
                if ($role !== null && $role->unblock()) {
                    return $agent->id()->value();
                }
            }
        }

        return null;
    }

    private function agentName(WorkspaceId $workspaceId, string $agentId): string
    {
        $agent = $this->agents->find($workspaceId, new AgentId($agentId));

        return $agent?->name() ?? 'the team';
    }
}
