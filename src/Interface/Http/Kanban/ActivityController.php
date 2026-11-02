<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Runner\RunRepository;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Kanban\Issue;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ActivityController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private WorkspaceGuard $guard,
        private IssueRepository $issues,
        private AgentRepository $agents,
        private RunRepository $runs,
        private JsonResponder $responder,
    ) {
    }

    public function show(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->guard->assertMember($user, $workspaceId);

        $issues = [];
        $byId = [];
        foreach ($this->issues->forWorkspace($workspaceId) as $issue) {
            $byId[$issue->id()->value()] = $issue;
            $issues[] = [
                'key' => $issue->key()->value(),
                'statusId' => $issue->statusId()->value(),
                'assigneeId' => $issue->assigneeId(),
                'blocked' => $issue->blocked(),
                'readyToMerge' => $issue->readyToMerge(),
                'position' => $issue->position(),
                'enteredAt' => $issue->enteredAt()->format(DATE_ATOM),
                'updatedAt' => $issue->updatedAt()->format(DATE_ATOM),
            ];
        }

        $agents = [];
        foreach ($this->agents->forWorkspace($workspaceId) as $agent) {
            $agents[] = [
                'id' => $agent->id()->value(),
                'name' => $agent->name(),
                'active' => $agent->active(),
                'roleIds' => $agent->roleIds()->values(),
                'language' => $agent->language(),
            ];
        }

        $runs = [];
        foreach ($this->runs->activeForWorkspace($workspaceId) as $run) {
            $issue = $byId[$run->issueId()->value()] ?? null;
            $runs[] = [
                'id' => $run->id()->value(),
                'agentId' => $run->agentId()->value(),
                'issueId' => $run->issueId()->value(),
                'issueKey' => $issue?->key()->value(),
                'issueSummary' => $issue?->summary(),
                'statusId' => $issue?->statusId()->value(),
                'state' => $run->state()->value,
                'startedAt' => $run->startedAt()?->format(DATE_ATOM),
                'createdAt' => $run->createdAt()->format(DATE_ATOM),
            ];
        }

        return $this->responder->respond([
            'issues' => $issues,
            'agents' => $agents,
            'runs' => $runs,
            'serverTime' => gmdate(DATE_ATOM),
        ]);
    }
}
