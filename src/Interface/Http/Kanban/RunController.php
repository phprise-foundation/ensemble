<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Runner\RequestRun;
use App\Application\Runner\ResolveWorkdirConflict;
use App\Application\Kanban\ReadIssue;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Contract\Runner\RunRepository;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\Tenancy\JsonBody;
use App\Interface\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RunController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ReadIssue $readIssue,
        private RunRepository $runs,
        private RunPresenter $presenter,
        private JsonResponder $responder,
        private ResolveWorkdirConflict $resolveConflict,
        private WorkdirPresenter $bindings,
        private RequestRun $requestRun,
    ) {
    }

    public function resolve(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueKey $key,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $strategy = JsonBody::string($body, 'strategy') ?? '';
        $workdir = $this->resolveConflict->execute($user, $workspaceId, $key, $strategy);

        return $this->responder->respond(['binding' => $this->bindings->present($workdir)]);
    }

    public function request(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueKey $key,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $run = $this->requestRun->execute($user, $workspaceId, $key);

        return $this->responder->respond(['run' => $this->presenter->present($run)], 201);
    }

    public function list(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueKey $key,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $issue = $this->readIssue->execute($user, $workspaceId, $key);

        return $this->responder->respond([
            'runs' => $this->presenter->presentMany($this->runs->forIssue($workspaceId, $issue->id())),
        ]);
    }
}
