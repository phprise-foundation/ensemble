<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Contract\Kanban\FieldDefinitionRepository;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Contract\Kanban\WorkdirRepository;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\FieldDefinition;
use App\Domain\Kanban\Workdir;
use App\Domain\Team\Profile;
use App\Domain\Team\Role;
use App\Domain\Team\Team;
use App\Domain\Vcs\GitRepository;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ReadSnapshot
{
    public function __construct(
        private WorkspaceRepository $workspaces,
        private WorkflowRepository $workflows,
        private IssueTypeRepository $issueTypes,
        private StatusRepository $statuses,
        private TransitionRepository $transitions,
        private BoardRepository $boards,
        private BoardColumnRepository $columns,
        private IssueRepository $issues,
        private FieldDefinitionRepository $fields,
        private WorkdirRepository $workdirs,
        private TeamRepository $teams,
        private RoleRepository $roles,
        private AgentRepository $agents,
        private ProfileRepository $profiles,
        private GitRepositories $repositories,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId): Snapshot
    {
        $this->guard->assertMember($actor, $workspaceId);
        $workspace = $this->workspaces->find($workspaceId) ?? throw NotFoundException::workspace();
        $teams = $this->teams->all($workspaceId);
        $issues = $this->issues->forWorkspace($workspaceId);

        return new Snapshot(
            workspace: $workspace,
            workflows: $this->workflows->forWorkspace($workspaceId),
            issueTypes: $this->issueTypesFor($workspaceId),
            statuses: $this->statusesFor($workspaceId),
            transitions: $this->transitionsFor($workspaceId),
            boards: $this->boards->forWorkspace($workspaceId),
            columns: $this->columnsFor($workspaceId),
            issues: $issues,
            workdirs: $this->workdirsFor($workspaceId, $issues),
            teams: $teams,
            roles: $this->rolesFor($teams),
            profiles: $this->profilesFor($teams),
            agents: $this->agents->forWorkspace($workspaceId),
            repositories: $this->repositories->all($workspaceId),
            fields: $this->fieldsFor($workspaceId),
        );
    }

    /** @return list<IssueType> */
    private function issueTypesFor(WorkspaceId $workspaceId): array
    {
        $types = [];
        foreach ($this->workflows->forWorkspace($workspaceId) as $workflow) {
            foreach ($this->issueTypes->forWorkflow($workspaceId, $workflow->id()) as $type) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /** @return array<string, list<FieldDefinition>> */
    private function fieldsFor(WorkspaceId $workspaceId): array
    {
        $fields = [];
        foreach ($this->issueTypesFor($workspaceId) as $type) {
            $fields[$type->id()->value()] = $this->fields->forIssueType($workspaceId, $type->id());
        }

        return $fields;
    }

    /** @return list<Status> */
    private function statusesFor(WorkspaceId $workspaceId): array
    {
        $statuses = [];
        foreach ($this->workflows->forWorkspace($workspaceId) as $workflow) {
            foreach ($this->statuses->forWorkflow($workspaceId, $workflow->id()) as $status) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    /** @return list<Transition> */
    private function transitionsFor(WorkspaceId $workspaceId): array
    {
        $transitions = [];
        foreach ($this->workflows->forWorkspace($workspaceId) as $workflow) {
            foreach ($this->transitions->forWorkflow($workspaceId, $workflow->id()) as $transition) {
                $transitions[] = $transition;
            }
        }

        return $transitions;
    }

    /** @return list<BoardColumn> */
    private function columnsFor(WorkspaceId $workspaceId): array
    {
        $columns = [];
        foreach ($this->boards->forWorkspace($workspaceId) as $board) {
            foreach ($this->columns->forBoard($workspaceId, $board->id()) as $column) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @param list<Issue> $issues
     * @return array<string, Workdir>
     */
    private function workdirsFor(WorkspaceId $workspaceId, array $issues): array
    {
        $workdirs = [];
        foreach ($issues as $issue) {
            $id = $issue->workdirId();
            if ($id !== null && ! isset($workdirs[$id->value()])) {
                $workdir = $this->workdirs->find($workspaceId, $id);
                if ($workdir !== null) {
                    $workdirs[$id->value()] = $workdir;
                }
            }
        }

        return $workdirs;
    }

    /**
     * @param list<Team> $teams
     * @return list<Role>
     */
    private function rolesFor(array $teams): array
    {
        $roles = [];
        foreach ($teams as $team) {
            foreach ($this->roles->forTeam($team->workspaceId(), $team->id()) as $role) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * @param list<Team> $teams
     * @return list<Profile>
     */
    private function profilesFor(array $teams): array
    {
        $profiles = [];
        foreach ($teams as $team) {
            foreach ($this->profiles->forTeam($team->workspaceId(), $team->id()) as $profile) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }
}
