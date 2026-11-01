<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Application\Kanban\CommentIssue;
use App\Application\Kanban\MoveIssue;
use App\Application\Kanban\ReadIssue;
use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Contract\Runner\RunRepository;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusType;
use App\Domain\Runner\CommitMessage;
use App\Domain\Team\AgentId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\PullRequests;

final readonly class MergePullRequest
{
    public function __construct(
        private ReadIssue $readIssue,
        private RunRepository $runs,
        private WorkdirRepository $workdirs,
        private StatusRepository $statuses,
        private MoveIssue $moveIssue,
        private CommentIssue $commentIssue,
        private PullRequests $pullRequests,
        private AgentRepository $agents,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return array{issue: Issue, merge: array{url: string, sha: ?string, message: string, branchDeleted: bool}} */
    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        IssueKey $key,
        string $method = 'merge',
    ): array {
        $this->guard->assertManager($actor, $workspaceId);
        $issue = $this->readIssue->execute($actor, $workspaceId, $key);
        if (! $issue->readyToMerge()) {
            throw new ValidationException('readyToMerge', 'The issue is not ready to merge.');
        }

        [$url, $implementerId] = $this->latestPullRequest($workspaceId, $issue);
        $number = $this->pullRequestNumber($url);
        $implementer = $this->agents->find($workspaceId, $implementerId);
        $implementerName = $implementer?->name() ?? 'agent';
        $workdirId = $issue->workdirId()
            ?? throw new ValidationException('workdir', 'The issue has no workdir.');
        $workdir = $this->workdirs->find($workspaceId, $workdirId)
            ?? throw new ValidationException('workdir', 'The issue workdir was not found.');

        $target = $this->doneStatus($workspaceId, $issue);
        $this->moveIssue->assertCanMove($issue, $target);

        $branch = $workdir->branch()?->value() ?? '';
        $mergeTitle = $this->mergeCommitTitle($issue, $implementerName, $branch, $number);
        $mergeBody = implode("\n", [
            'Implemented by: ' . $implementerName,
            'Merged by: ' . $actor->login()->value(),
            'Issue: ' . $issue->key()->value(),
        ]);
        $merge = $this->pullRequests->merge($workdir, $number, $method, $mergeTitle, $mergeBody);
        if ($merge->isFailed()) {
            throw new ConflictException($merge->message());
        }

        $moved = $this->moveIssue->execute($actor, $workspaceId, $key, $target->id());
        $branchDeleted = false;
        $branchNote = '';
        if ($branch !== '') {
            $deletion = $this->pullRequests->deleteBranch($workdir, $branch);
            $branchDeleted = $deletion->ok;
            $branchNote = $deletion->ok
                ? ' The work branch was deleted.'
                : ' The branch could not be deleted: ' . $deletion->message;
        }

        $detail = $merge->sha() === null ? '' : ' (' . mb_substr($merge->sha(), 0, 12) . ')';
        $this->commentIssue->execute(
            $actor,
            $workspaceId,
            $key,
            'Merged pull request #' . $number . $detail . ' from ' . $implementerName . ' into the base branch.' . $branchNote,
        );

        return [
            'issue' => $moved,
            'merge' => [
                'url' => $url,
                'sha' => $merge->sha(),
                'message' => $merge->message(),
                'branchDeleted' => $branchDeleted,
            ],
        ];
    }

    private function mergeCommitTitle(Issue $issue, string $implementerName, string $branch, int $number): string
    {
        return mb_substr(
            CommitMessage::typeForBranch($branch) . '(' . $implementerName . '/' . $issue->key()->value() . '): ' . $issue->summary() . ' (#' . $number . ')',
            0,
            240,
        );
    }

    /** @return array{0: string, 1: AgentId} */
    private function latestPullRequest(WorkspaceId $workspaceId, Issue $issue): array
    {
        foreach (array_reverse($this->runs->forIssue($workspaceId, $issue->id())) as $run) {
            $url = $run->pullRequestUrl();
            if ($url !== null) {
                return [$url, $run->agentId()];
            }
        }

        throw new ValidationException('pullRequest', 'The issue has no pull request to merge.');
    }

    private function pullRequestNumber(string $url): int
    {
        if (preg_match('#/pull/(\d+)(?:\D|$)#', $url, $matches) !== 1) {
            throw new ValidationException('pullRequest', 'The pull request URL has no number.');
        }

        return (int) $matches[1];
    }

    private function doneStatus(WorkspaceId $workspaceId, Issue $issue): Status
    {
        $finals = array_values(array_filter(
            $this->statuses->forIssueType($workspaceId, $issue->issueTypeId()),
            static fn(Status $status): bool => $status->type() === StatusType::Final,
        ));
        if ($finals === []) {
            throw new ValidationException('status', 'The issue type has no final status.');
        }

        foreach ($finals as $status) {
            $name = mb_strtolower($status->name());
            if (str_contains($name, 'done') || str_contains($name, 'conclu')) {
                return $status;
            }
        }

        return $finals[0];
    }
}
