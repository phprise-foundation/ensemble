<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\ReadSnapshot;
use App\Application\Kanban\Snapshot;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\Kanban\FieldDefinitionPresenter;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Team\AgentPresenter;
use App\Interface\Http\Team\ProfilePresenter;
use App\Interface\Http\Team\RolePresenter;
use App\Interface\Http\Team\TeamPresenter;
use App\Interface\Http\Tenancy\WorkspacePresenter;
use App\Interface\Http\Vcs\RepositoryPresenter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SnapshotController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ReadSnapshot $snapshot,
        private AgentPresenter $agents,
        private ProfilePresenter $profiles,
        private RolePresenter $roles,
        private TeamPresenter $teams,
        private WorkspacePresenter $workspaces,
        private RepositoryPresenter $repositories,
        private JsonResponder $responder,
    ) {
    }

    /** Presents the full board snapshot the frontend boots on. */
    private function issueTypesWithFields(Snapshot $snapshot): array
    {
        $presenter = new IssueTypePresenter();
        $fields = new FieldDefinitionPresenter();
        $presented = [];
        foreach ($presenter->presentMany($snapshot->issueTypes) as $issueType) {
            $issueTypeId = (string) $issueType['id'];
            $issueType['fields'] = $fields->presentMany($snapshot->fields[$issueTypeId] ?? []);
            $presented[] = $issueType;
        }

        return $presented;
    }
    public function show(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $snapshot = $this->snapshot->execute($user, $workspaceId);

        return $this->responder->respond([
            'workspaces' => [$this->workspaces->present($snapshot->workspace, null)],
            'workflows' => (new WorkflowPresenter())->presentMany($snapshot->workflows),
            'issueTypes' => $this->issueTypesWithFields($snapshot),
            'statuses' => (new StatusPresenter())->presentMany($snapshot->statuses),
            'transitions' => (new TransitionPresenter())->presentMany($snapshot->transitions),
            'boards' => (new BoardPresenter())->presentMany($snapshot->boards),
            'columns' => (new ColumnPresenter())->presentMany($snapshot->columns),
            'issues' => (new IssuePresenter())->presentMany($snapshot->issues, $snapshot->workdirs),
            'teams' => $this->teams->presentMany($snapshot->teams),
            'roles' => $this->roles->presentMany($snapshot->roles),
            'profiles' => $this->profiles->presentMany($snapshot->profiles),
            'agents' => $this->agents->presentMany($snapshot->agents),
            'sources' => $this->repositories->presentMany($snapshot->repositories),
        ]);
    }
}
