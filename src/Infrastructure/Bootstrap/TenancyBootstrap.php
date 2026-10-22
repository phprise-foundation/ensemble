<?php

declare(strict_types=1);

namespace App\Infrastructure\Bootstrap;

use App\Application\Kanban\CreateBoard;
use App\Application\Kanban\CreateColumn;
use App\Application\Kanban\CommentIssue;
use App\Application\Kanban\CreateFieldDefinition;
use App\Application\Kanban\CreateIssue;
use App\Application\Kanban\CreateStatus;
use App\Application\Kanban\CreateTransition;
use App\Application\Kanban\CreateIssueType;
use App\Application\Kanban\CreateWorkflow;
use App\Application\Kanban\DeleteBoard;
use App\Application\Kanban\DeleteColumn;
use App\Application\Kanban\DeleteFieldDefinition;
use App\Application\Kanban\DeleteStatus;
use App\Application\Kanban\DeleteTransition;
use App\Application\Kanban\DeleteIssueType;
use App\Application\Kanban\DeleteWorkflow;
use App\Application\Kanban\ListBoards;
use App\Application\Kanban\ListColumns;
use App\Application\Kanban\ListFieldDefinitions;
use App\Application\Kanban\ListIssueComments;
use App\Application\Kanban\ListIssueEvents;
use App\Application\Kanban\ListIssues;
use App\Application\Kanban\ListStatuses;
use App\Application\Kanban\ListTransitions;
use App\Application\Kanban\ListIssueTypes;
use App\Application\Kanban\ListWorkflows;
use App\Application\Kanban\ReadBoard;
use App\Application\Kanban\ReadColumn;
use App\Application\Kanban\MoveIssue;
use App\Application\Kanban\ReadFieldDefinition;
use App\Application\Kanban\ReadIssue;
use App\Application\Kanban\ReadStatus;
use App\Application\Kanban\ReadTransition;
use App\Application\Kanban\ReadIssueType;
use App\Application\Kanban\ReadWorkflow;
use App\Application\Kanban\UpdateBoard;
use App\Application\Kanban\UpdateColumn;
use App\Application\Kanban\UpdateFieldDefinition;
use App\Application\Kanban\UpdateIssue;
use App\Application\Kanban\UpdateStatus;
use App\Application\Kanban\UpdateTransition;
use App\Application\Kanban\UpdateIssueType;
use App\Application\Kanban\UpdateWorkflow;
use App\Application\Kanban\ValidateBoardFilters;
use App\Application\Kanban\ValidateColumnStatuses;
use App\Application\Kanban\ValidateFieldValues;
use App\Application\Security\AuthenticateUser;
use App\Application\Security\ReadCurrentUser;
use App\Application\Tenancy\AddWorkspaceMember;
use App\Application\Tenancy\CreateWorkspace;
use App\Application\Tenancy\ListWorkspaceMembers;
use App\Application\Tenancy\ListWorkspaces;
use App\Application\Tenancy\ReadWorkspace;
use App\Application\Tenancy\RegisterUser;
use App\Application\Tenancy\RemoveWorkspaceMember;
use App\Application\Tenancy\UpdateWorkspace;
use App\Application\Tenancy\WorkspaceGuard;
use App\Infrastructure\Authentication\ArgonPasswordHasher;
use App\Infrastructure\Authentication\RandomTokenGenerator;
use App\Infrastructure\Persistence\Doctrine\DoctrineEntityManagerFactory;
use App\Infrastructure\Persistence\Doctrine\DoctrineIssueKeyGenerator;
use App\Infrastructure\Persistence\Doctrine\Mapper\BoardColumnRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\BoardRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\FieldDefinitionRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueCommentRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueEventRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\IssueTypeRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\MembershipRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\SessionRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\StatusRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\TransitionRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\UserRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkflowRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Mapper\WorkspaceRecordMapper;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineBoardColumnRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineBoardRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineFieldDefinitionRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineIssueCommentRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineIssueEventRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineIssueRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineIssueTypeRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineMembershipRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineSessionRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineStatusRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineTransitionRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineWorkflowRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineWorkspaceRepository;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Kanban\BoardController;
use App\Interface\Http\Kanban\BoardPresenter;
use App\Interface\Http\Kanban\ColumnController;
use App\Interface\Http\Kanban\ColumnPresenter;
use App\Interface\Http\Kanban\FieldDefinitionController;
use App\Interface\Http\Kanban\FieldDefinitionPresenter;
use App\Interface\Http\Kanban\IssueCommentPresenter;
use App\Interface\Http\Kanban\IssueController;
use App\Interface\Http\Kanban\IssueEventPresenter;
use App\Interface\Http\Kanban\IssuePresenter;
use App\Interface\Http\Kanban\IssueTypeController;
use App\Interface\Http\Kanban\IssueTypePresenter;
use App\Interface\Http\Kanban\StatusController;
use App\Interface\Http\Kanban\StatusPresenter;
use App\Interface\Http\Kanban\TransitionController;
use App\Interface\Http\Kanban\TransitionPresenter;
use App\Interface\Http\Kanban\WorkflowController;
use App\Interface\Http\Kanban\WorkflowPresenter;
use App\Interface\Http\Tenancy\MemberPresenter;
use App\Interface\Http\Tenancy\SessionController;
use App\Interface\Http\Tenancy\TenancyErrorResponder;
use App\Interface\Http\Tenancy\TenancyRouter;
use App\Interface\Http\Tenancy\UserController;
use App\Interface\Http\Tenancy\UserPresenter;
use App\Interface\Http\Tenancy\WorkspaceController;
use App\Interface\Http\Tenancy\WorkspacePresenter;

final readonly class TenancyBootstrap
{
    public function __construct(
        private DoctrineEntityManagerFactory $entityManagers,
        private JsonResponder $responder,
    ) {
    }

    public function router(): TenancyRouter
    {
        $entityManager = $this->entityManagers->create();
        $users = new DoctrineUserRepository($entityManager, new UserRecordMapper());
        $workspaces = new DoctrineWorkspaceRepository($entityManager, new WorkspaceRecordMapper());
        $memberships = new DoctrineMembershipRepository($entityManager, new MembershipRecordMapper());
        $sessions = new DoctrineSessionRepository($entityManager, new SessionRecordMapper());
        $hasher = new ArgonPasswordHasher();
        $guard = new WorkspaceGuard($workspaces, $memberships);
        $currentUser = new ReadCurrentUser($sessions, $users);
        $userPresenter = new UserPresenter();
        $workspacePresenter = new WorkspacePresenter();
        $memberPresenter = new MemberPresenter($userPresenter);
        $workflows = new DoctrineWorkflowRepository($entityManager, new WorkflowRecordMapper());
        $issueTypes = new DoctrineIssueTypeRepository($entityManager, new IssueTypeRecordMapper());
        $fields = new DoctrineFieldDefinitionRepository($entityManager, new FieldDefinitionRecordMapper());
        $workflowPresenter = new WorkflowPresenter();
        $issueTypePresenter = new IssueTypePresenter();
        $fieldPresenter = new FieldDefinitionPresenter();
        $statuses = new DoctrineStatusRepository($entityManager, new StatusRecordMapper());
        $transitions = new DoctrineTransitionRepository($entityManager, new TransitionRecordMapper());
        $statusPresenter = new StatusPresenter();
        $transitionPresenter = new TransitionPresenter();
        $boards = new DoctrineBoardRepository($entityManager, new BoardRecordMapper());
        $boardColumns = new DoctrineBoardColumnRepository($entityManager, new BoardColumnRecordMapper());
        $validateBoardFilters = new ValidateBoardFilters($issueTypes, $statuses);
        $validateColumnStatuses = new ValidateColumnStatuses($statuses);
        $boardPresenter = new BoardPresenter();
        $columnPresenter = new ColumnPresenter();
        $issueRepository = new DoctrineIssueRepository($entityManager, new IssueRecordMapper());
        $issueComments = new DoctrineIssueCommentRepository($entityManager, new IssueCommentRecordMapper());
        $issueEvents = new DoctrineIssueEventRepository($entityManager, new IssueEventRecordMapper());
        $issueKeys = new DoctrineIssueKeyGenerator($entityManager);
        $validateFieldValues = new ValidateFieldValues();
        $issuePresenter = new IssuePresenter();
        $issueCommentPresenter = new IssueCommentPresenter();
        $issueEventPresenter = new IssueEventPresenter();

        return new TenancyRouter(
            new SessionController(
                new AuthenticateUser($users, $sessions, $hasher, new RandomTokenGenerator()),
                $currentUser,
                $sessions,
                new ListWorkspaces($workspaces, $memberships),
                $userPresenter,
                $workspacePresenter,
                $this->responder,
            ),
            new UserController(
                $currentUser,
                $guard,
                new RegisterUser($users, $hasher),
                $users,
                $userPresenter,
                $this->responder,
            ),
            new WorkspaceController(
                $currentUser,
                $guard,
                new ListWorkspaces($workspaces, $memberships),
                new CreateWorkspace($workspaces, $memberships),
                new ReadWorkspace($guard),
                new UpdateWorkspace($workspaces, $guard),
                new ListWorkspaceMembers($guard, $memberships, $users),
                new AddWorkspaceMember($guard, $users, $memberships),
                new RemoveWorkspaceMember($guard, $memberships),
                $workspacePresenter,
                $memberPresenter,
                $this->responder,
            ),
            new WorkflowController(
                $currentUser,
                new ListWorkflows($workflows, $guard),
                new CreateWorkflow($workflows, $guard),
                new ReadWorkflow($workflows, $guard),
                new UpdateWorkflow($workflows, $guard),
                new DeleteWorkflow($workflows, $issueTypes, $guard),
                $workflowPresenter,
                $this->responder,
            ),
            new IssueTypeController(
                $currentUser,
                new ListIssueTypes($workflows, $issueTypes, $guard),
                new CreateIssueType($workflows, $issueTypes, $guard),
                new ReadIssueType($issueTypes, $guard),
                new UpdateIssueType($issueTypes, $guard),
                new DeleteIssueType($issueTypes, $fields, $guard),
                $issueTypePresenter,
                $this->responder,
            ),
            new FieldDefinitionController(
                $currentUser,
                new ListFieldDefinitions($issueTypes, $fields, $guard),
                new CreateFieldDefinition($issueTypes, $fields, $guard),
                new ReadFieldDefinition($fields, $guard),
                new UpdateFieldDefinition($fields, $guard),
                new DeleteFieldDefinition($fields, $guard),
                $fieldPresenter,
                $this->responder,
            ),
            new StatusController(
                $currentUser,
                new ListStatuses($workflows, $issueTypes, $statuses, $guard),
                new CreateStatus($workflows, $issueTypes, $statuses, $guard),
                new ReadStatus($statuses, $guard),
                new UpdateStatus($statuses, $guard),
                new DeleteStatus($statuses, $transitions, $guard),
                $statusPresenter,
                $this->responder,
            ),
            new TransitionController(
                $currentUser,
                new ListTransitions($workflows, $transitions, $guard),
                new CreateTransition($workflows, $statuses, $transitions, $guard),
                new ReadTransition($transitions, $guard),
                new UpdateTransition($transitions, $guard),
                new DeleteTransition($transitions, $guard),
                $transitionPresenter,
                $this->responder,
            ),
            new BoardController(
                $currentUser,
                new ListBoards($boards, $guard),
                new CreateBoard($workflows, $boards, $validateBoardFilters, $guard),
                new ReadBoard($boards, $guard),
                new UpdateBoard($boards, $validateBoardFilters, $guard),
                new DeleteBoard($boards, $boardColumns, $guard),
                new ListColumns($boards, $boardColumns, $guard),
                $boardPresenter,
                $columnPresenter,
                $this->responder,
            ),
            new ColumnController(
                $currentUser,
                new ListColumns($boards, $boardColumns, $guard),
                new CreateColumn($boards, $boardColumns, $validateColumnStatuses, $guard),
                new ReadColumn($boardColumns, $guard),
                new UpdateColumn($boards, $boardColumns, $validateColumnStatuses, $guard),
                new DeleteColumn($boardColumns, $guard),
                $columnPresenter,
                $this->responder,
            ),
            new IssueController(
                $currentUser,
                new ReadWorkspace($guard),
                new ListIssues($issueRepository, $boards, $boardColumns, $guard),
                new CreateIssue($issueRepository, $issueTypes, $statuses, $fields, $issueEvents, $issueKeys, $validateFieldValues, $guard),
                new ReadIssue($issueRepository, $guard),
                new UpdateIssue($issueRepository, $fields, $validateFieldValues, $guard),
                new MoveIssue($issueRepository, $statuses, $transitions, $issueEvents, $guard),
                new CommentIssue($issueRepository, $issueComments, $guard),
                new ListIssueComments($issueRepository, $issueComments, $guard),
                new ListIssueEvents($issueRepository, $issueEvents, $guard),
                $issuePresenter,
                $issueCommentPresenter,
                $issueEventPresenter,
                $this->responder,
            ),
            new TenancyErrorResponder($this->responder),
            $this->responder,
        );
    }
}
