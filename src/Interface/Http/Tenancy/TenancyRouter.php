<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Domain\Kanban\BoardColumnId;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\FieldDefinitionId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\TransitionId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Team\ProfileId;
use App\Domain\Team\RoleId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\GitRepositoryId;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Kanban\BoardController;
use App\Interface\Http\Kanban\ColumnController;
use App\Interface\Http\Kanban\FieldDefinitionController;
use App\Interface\Http\Kanban\IssueController;
use App\Interface\Http\Kanban\IssueTypeController;
use App\Interface\Http\Kanban\StatusController;
use App\Interface\Http\Kanban\TransitionController;
use App\Interface\Http\Kanban\WorkflowController;
use App\Interface\Http\Security\SecretController;
use App\Interface\Http\Team\ProfileController;
use App\Interface\Http\Team\RoleController;
use App\Interface\Http\Team\TeamController;
use App\Interface\Http\Vcs\RepositoryController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final readonly class TenancyRouter
{
    public function __construct(
        private SessionController $sessions,
        private UserController $users,
        private WorkspaceController $workspaces,
        private WorkflowController $workflows,
        private IssueTypeController $issueTypes,
        private FieldDefinitionController $fields,
        private StatusController $statuses,
        private TransitionController $transitions,
        private BoardController $boards,
        private ColumnController $columns,
        private IssueController $issues,
        private TeamController $teams,
        private RoleController $roles,
        private ProfileController $profiles,
        private SecretController $secrets,
        private RepositoryController $repositories,
        private TenancyErrorResponder $errors,
        private JsonResponder $responder,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $this->dispatch($request);
        } catch (Throwable $error) {
            return $this->errors->respond($error);
        }
    }

    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $path = (string) parse_url($request->getUri()->getPath(), PHP_URL_PATH);
        $method = $request->getMethod();
        $segments = array_values(array_filter(explode('/', $path)));

        return match (true) {
            $method === 'POST' && $path === '/api/auth/login' => $this->sessions->login($request),
            $method === 'POST' && $path === '/api/auth/logout' => $this->sessions->logout($request),
            $method === 'GET' && $path === '/api/me' => $this->sessions->me($request),
            $method === 'GET' && $this->isUsersRoot($segments) => $this->users->list($request),
            $method === 'POST' && $this->isUsersRoot($segments) => $this->users->create($request),
            $method === 'GET' && $this->isWorkspacesRoot($segments) => $this->workspaces->list($request),
            $method === 'POST' && $this->isWorkspacesRoot($segments) => $this->workspaces->create($request),
            $method === 'GET' && $this->isWorkspace($segments) => $this->workspaces->show($request, $this->workspaceId($segments)),
            $method === 'PATCH' && $this->isWorkspace($segments) => $this->workspaces->update($request, $this->workspaceId($segments)),
            $method === 'GET' && $this->isMembers($segments) => $this->workspaces->members($request, $this->workspaceId($segments)),
            $method === 'POST' && $this->isMembers($segments) => $this->workspaces->addMember($request, $this->workspaceId($segments)),
            $method === 'DELETE' && $this->isMember($segments) => $this->workspaces->removeMember(
                $request,
                $this->workspaceId($segments),
                new UserId($segments[4] ?? ''),
            ),
            $method === 'GET' && $this->isWorkflows($segments) => $this->workflows->list($request, $this->workspaceId($segments)),
            $method === 'POST' && $this->isWorkflows($segments) => $this->workflows->create($request, $this->workspaceId($segments)),
            $method === 'GET' && $this->isWorkflow($segments) => $this->workflows->show($request, $this->workspaceId($segments), $this->workflowId($segments)),
            $method === 'PATCH' && $this->isWorkflow($segments) => $this->workflows->update($request, $this->workspaceId($segments), $this->workflowId($segments)),
            $method === 'DELETE' && $this->isWorkflow($segments) => $this->workflows->delete($request, $this->workspaceId($segments), $this->workflowId($segments)),
            $method === 'GET' && $this->isWorkflowIssueTypes($segments) => $this->issueTypes->list($request, $this->workspaceId($segments), $this->workflowId($segments)),
            $method === 'POST' && $this->isWorkflowIssueTypes($segments) => $this->issueTypes->create($request, $this->workspaceId($segments), $this->workflowId($segments)),
            $method === 'GET' && $this->isIssueType($segments) => $this->issueTypes->show($request, $this->workspaceId($segments), $this->issueTypeId($segments)),
            $method === 'PATCH' && $this->isIssueType($segments) => $this->issueTypes->update($request, $this->workspaceId($segments), $this->issueTypeId($segments)),
            $method === 'DELETE' && $this->isIssueType($segments) => $this->issueTypes->delete($request, $this->workspaceId($segments), $this->issueTypeId($segments)),
            $method === 'GET' && $this->isIssueTypeFields($segments) => $this->fields->list($request, $this->workspaceId($segments), $this->issueTypeId($segments)),
            $method === 'POST' && $this->isIssueTypeFields($segments) => $this->fields->create($request, $this->workspaceId($segments), $this->issueTypeId($segments)),
            $method === 'GET' && $this->isField($segments) => $this->fields->show($request, $this->workspaceId($segments), $this->fieldId($segments)),
            $method === 'PATCH' && $this->isField($segments) => $this->fields->update($request, $this->workspaceId($segments), $this->fieldId($segments)),
            $method === 'DELETE' && $this->isField($segments) => $this->fields->delete($request, $this->workspaceId($segments), $this->fieldId($segments)),
            $method === 'GET' && $this->isWorkflowStatuses($segments) => $this->statuses->list($request, $this->workspaceId($segments), $this->workflowId($segments)),
            $method === 'POST' && $this->isWorkflowStatuses($segments) => $this->statuses->create($request, $this->workspaceId($segments), $this->workflowId($segments)),
            $method === 'GET' && $this->isStatus($segments) => $this->statuses->show($request, $this->workspaceId($segments), $this->statusId($segments)),
            $method === 'PATCH' && $this->isStatus($segments) => $this->statuses->update($request, $this->workspaceId($segments), $this->statusId($segments)),
            $method === 'DELETE' && $this->isStatus($segments) => $this->statuses->delete($request, $this->workspaceId($segments), $this->statusId($segments)),
            $method === 'GET' && $this->isWorkflowTransitions($segments) => $this->transitions->list($request, $this->workspaceId($segments), $this->workflowId($segments)),
            $method === 'POST' && $this->isWorkflowTransitions($segments) => $this->transitions->create($request, $this->workspaceId($segments), $this->workflowId($segments)),
            $method === 'GET' && $this->isTransition($segments) => $this->transitions->show($request, $this->workspaceId($segments), $this->transitionId($segments)),
            $method === 'PATCH' && $this->isTransition($segments) => $this->transitions->update($request, $this->workspaceId($segments), $this->transitionId($segments)),
            $method === 'DELETE' && $this->isTransition($segments) => $this->transitions->delete($request, $this->workspaceId($segments), $this->transitionId($segments)),
            $method === 'GET' && $this->isBoards($segments) => $this->boards->list($request, $this->workspaceId($segments)),
            $method === 'POST' && $this->isBoards($segments) => $this->boards->create($request, $this->workspaceId($segments)),
            $method === 'GET' && $this->isBoard($segments) => $this->boards->show($request, $this->workspaceId($segments), $this->boardId($segments)),
            $method === 'PATCH' && $this->isBoard($segments) => $this->boards->update($request, $this->workspaceId($segments), $this->boardId($segments)),
            $method === 'DELETE' && $this->isBoard($segments) => $this->boards->delete($request, $this->workspaceId($segments), $this->boardId($segments)),
            $method === 'GET' && $this->isBoardColumns($segments) => $this->columns->list($request, $this->workspaceId($segments), $this->boardId($segments)),
            $method === 'POST' && $this->isBoardColumns($segments) => $this->columns->create($request, $this->workspaceId($segments), $this->boardId($segments)),
            $method === 'GET' && $this->isColumn($segments) => $this->columns->show($request, $this->workspaceId($segments), $this->columnId($segments)),
            $method === 'PATCH' && $this->isColumn($segments) => $this->columns->update($request, $this->workspaceId($segments), $this->columnId($segments)),
            $method === 'DELETE' && $this->isColumn($segments) => $this->columns->delete($request, $this->workspaceId($segments), $this->columnId($segments)),
            $method === 'GET' && $this->isIssues($segments) => $this->issues->list($request, $this->workspaceId($segments)),
            $method === 'POST' && $this->isIssues($segments) => $this->issues->create($request, $this->workspaceId($segments)),
            $method === 'GET' && $this->isIssue($segments) => $this->issues->show($request, $this->workspaceId($segments), $this->issueKey($segments)),
            $method === 'PATCH' && $this->isIssue($segments) => $this->issues->update($request, $this->workspaceId($segments), $this->issueKey($segments)),
            $method === 'POST' && $this->isIssueMove($segments) => $this->issues->move($request, $this->workspaceId($segments), $this->issueKey($segments)),
            $method === 'POST' && $this->isIssueComments($segments) => $this->issues->comment($request, $this->workspaceId($segments), $this->issueKey($segments)),
            $method === 'POST' && $this->isIssueRepository($segments) => $this->issues->attach($request, $this->workspaceId($segments), $this->issueKey($segments)),
            $method === 'GET' && $this->isTeams($segments) => $this->teams->list($request, $this->workspaceId($segments)),
            $method === 'POST' && $this->isTeams($segments) => $this->teams->create($request, $this->workspaceId($segments)),
            $method === 'GET' && $this->isTeam($segments) => $this->teams->show($request, $this->workspaceId($segments), $this->teamId($segments)),
            $method === 'PATCH' && $this->isTeam($segments) => $this->teams->update($request, $this->workspaceId($segments), $this->teamId($segments)),
            $method === 'DELETE' && $this->isTeam($segments) => $this->teams->delete($request, $this->workspaceId($segments), $this->teamId($segments)),
            $method === 'GET' && $this->isTeamRoles($segments) => $this->roles->list($request, $this->workspaceId($segments), $this->teamId($segments)),
            $method === 'POST' && $this->isTeamRoles($segments) => $this->roles->create($request, $this->workspaceId($segments), $this->teamId($segments)),
            $method === 'GET' && $this->isRole($segments) => $this->roles->show($request, $this->workspaceId($segments), $this->roleId($segments)),
            $method === 'PATCH' && $this->isRole($segments) => $this->roles->update($request, $this->workspaceId($segments), $this->roleId($segments)),
            $method === 'DELETE' && $this->isRole($segments) => $this->roles->delete($request, $this->workspaceId($segments), $this->roleId($segments)),
            $method === 'GET' && $this->isTeamProfiles($segments) => $this->profiles->list($request, $this->workspaceId($segments), $this->teamId($segments)),
            $method === 'POST' && $this->isTeamProfiles($segments) => $this->profiles->create($request, $this->workspaceId($segments), $this->teamId($segments)),
            $method === 'GET' && $this->isProfile($segments) => $this->profiles->show($request, $this->workspaceId($segments), $this->profileId($segments)),
            $method === 'PATCH' && $this->isProfile($segments) => $this->profiles->update($request, $this->workspaceId($segments), $this->profileId($segments)),
            $method === 'DELETE' && $this->isProfile($segments) => $this->profiles->delete($request, $this->workspaceId($segments), $this->profileId($segments)),
            $method === 'GET' && $this->isSecrets($segments) => $this->secrets->list($request, $this->workspaceId($segments)),
            $method === 'POST' && $this->isSecrets($segments) => $this->secrets->create($request, $this->workspaceId($segments)),
            $method === 'GET' && $this->isSecret($segments) => $this->secrets->show($request, $this->workspaceId($segments), $this->secretId($segments)),
            $method === 'PATCH' && $this->isSecret($segments) => $this->secrets->rotate($request, $this->workspaceId($segments), $this->secretId($segments)),
            $method === 'DELETE' && $this->isSecret($segments) => $this->secrets->delete($request, $this->workspaceId($segments), $this->secretId($segments)),
            $method === 'GET' && $this->isRepositories($segments) => $this->repositories->list($request, $this->workspaceId($segments)),
            $method === 'POST' && $this->isRepositories($segments) => $this->repositories->create($request, $this->workspaceId($segments)),
            $method === 'GET' && $this->isRepository($segments) => $this->repositories->show($request, $this->workspaceId($segments), $this->repositoryId($segments)),
            $method === 'PATCH' && $this->isRepository($segments) => $this->repositories->update($request, $this->workspaceId($segments), $this->repositoryId($segments)),
            $method === 'DELETE' && $this->isRepository($segments) => $this->repositories->delete($request, $this->workspaceId($segments), $this->repositoryId($segments)),
            default => $this->responder->error('not found', 404),
        };
    }

    /** @param list<string> $segments */
    private function isUsersRoot(array $segments): bool
    {
        return $segments === ['api', 'users'];
    }

    /** @param list<string> $segments */
    private function isWorkspacesRoot(array $segments): bool
    {
        return $segments === ['api', 'workspaces'];
    }

    /** @param list<string> $segments */
    private function isWorkspace(array $segments): bool
    {
        return count($segments) === 3 && $segments[0] === 'api' && $segments[1] === 'workspaces';
    }

    /** @param list<string> $segments */
    private function isMembers(array $segments): bool
    {
        return count($segments) === 4
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'members';
    }

    /** @param list<string> $segments */
    private function isMember(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'members';
    }

    /** @param list<string> $segments */
    private function isWorkflows(array $segments): bool
    {
        return count($segments) === 4
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'workflows';
    }

    /** @param list<string> $segments */
    private function isWorkflow(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'workflows';
    }

    /** @param list<string> $segments */
    private function isWorkflowIssueTypes(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'workflows'
            && $segments[5] === 'issue-types';
    }

    /** @param list<string> $segments */
    private function isIssueType(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'issue-types';
    }

    /** @param list<string> $segments */
    private function isIssueTypeFields(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'issue-types'
            && $segments[5] === 'fields';
    }

    /** @param list<string> $segments */
    private function isField(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'fields';
    }

    /** @param list<string> $segments */
    private function isWorkflowStatuses(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'workflows'
            && $segments[5] === 'statuses';
    }

    /** @param list<string> $segments */
    private function isStatus(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'statuses';
    }

    /** @param list<string> $segments */
    private function isWorkflowTransitions(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'workflows'
            && $segments[5] === 'transitions';
    }

    /** @param list<string> $segments */
    private function isTransition(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'transitions';
    }

    /** @param list<string> $segments */
    private function isBoards(array $segments): bool
    {
        return count($segments) === 4
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'boards';
    }

    /** @param list<string> $segments */
    private function isBoard(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'boards';
    }

    /** @param list<string> $segments */
    private function isBoardColumns(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'boards'
            && $segments[5] === 'columns';
    }

    /** @param list<string> $segments */
    private function isColumn(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'columns';
    }

    /** @param list<string> $segments */
    private function isIssues(array $segments): bool
    {
        return count($segments) === 4
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'issues';
    }

    /** @param list<string> $segments */
    private function isIssue(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'issues';
    }

    /** @param list<string> $segments */
    private function isIssueMove(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'issues'
            && $segments[5] === 'move';
    }

    /** @param list<string> $segments */
    private function isIssueComments(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'issues'
            && $segments[5] === 'comments';
    }

    /** @param list<string> $segments */
    private function isIssueRepository(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'issues'
            && $segments[5] === 'repository';
    }

    /** @param list<string> $segments */
    private function isTeams(array $segments): bool
    {
        return count($segments) === 4
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'teams';
    }

    /** @param list<string> $segments */
    private function isTeam(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'teams';
    }

    /** @param list<string> $segments */
    private function isTeamRoles(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'teams'
            && $segments[5] === 'roles';
    }

    /** @param list<string> $segments */
    private function isRole(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'roles';
    }

    /** @param list<string> $segments */
    private function isTeamProfiles(array $segments): bool
    {
        return count($segments) === 6
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'teams'
            && $segments[5] === 'profiles';
    }

    /** @param list<string> $segments */
    private function isProfile(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'profiles';
    }

    /** @param list<string> $segments */
    private function isSecrets(array $segments): bool
    {
        return count($segments) === 4
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'secrets';
    }

    /** @param list<string> $segments */
    private function isSecret(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'secrets';
    }

    /** @param list<string> $segments */
    private function isRepositories(array $segments): bool
    {
        return count($segments) === 4
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'repositories';
    }

    /** @param list<string> $segments */
    private function isRepository(array $segments): bool
    {
        return count($segments) === 5
            && $segments[0] === 'api'
            && $segments[1] === 'workspaces'
            && $segments[3] === 'repositories';
    }

    /** @param list<string> $segments */
    private function workspaceId(array $segments): WorkspaceId
    {
        return new WorkspaceId($segments[2] ?? '');
    }

    /** @param list<string> $segments */
    private function workflowId(array $segments): WorkflowId
    {
        return new WorkflowId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function issueTypeId(array $segments): IssueTypeId
    {
        return new IssueTypeId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function fieldId(array $segments): FieldDefinitionId
    {
        return new FieldDefinitionId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function statusId(array $segments): StatusId
    {
        return new StatusId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function transitionId(array $segments): TransitionId
    {
        return new TransitionId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function boardId(array $segments): BoardId
    {
        return new BoardId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function columnId(array $segments): BoardColumnId
    {
        return new BoardColumnId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function issueKey(array $segments): IssueKey
    {
        return new IssueKey($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function teamId(array $segments): TeamId
    {
        return new TeamId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function roleId(array $segments): RoleId
    {
        return new RoleId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function profileId(array $segments): ProfileId
    {
        return new ProfileId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function secretId(array $segments): WorkspaceSecretId
    {
        return new WorkspaceSecretId($segments[4] ?? '');
    }

    /** @param list<string> $segments */
    private function repositoryId(array $segments): GitRepositoryId
    {
        return new GitRepositoryId($segments[4] ?? '');
    }
}
