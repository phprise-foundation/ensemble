<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Domain\Kanban\FieldDefinitionId;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\TransitionId;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\UserId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Kanban\FieldDefinitionController;
use App\Interface\Http\Kanban\IssueTypeController;
use App\Interface\Http\Kanban\StatusController;
use App\Interface\Http\Kanban\TransitionController;
use App\Interface\Http\Kanban\WorkflowController;
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
}
