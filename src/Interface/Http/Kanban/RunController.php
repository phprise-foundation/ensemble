<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\ReadIssue;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Contract\Runner\RunRepository;
use App\Domain\Kanban\IssueKey;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
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
    ) {
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
