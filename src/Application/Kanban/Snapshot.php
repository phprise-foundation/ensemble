<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Domain\Kanban\Board;
use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\Workflow;
use App\Domain\Kanban\FieldDefinition;
use App\Domain\Kanban\Workdir;
use App\Domain\Tenancy\Workspace;
use App\Domain\Team\Agent;
use App\Domain\Team\Profile;
use App\Domain\Team\Role;
use App\Domain\Team\Team;
use App\Domain\Vcs\GitRepository;

/** One read the board needs to boot: workspace, board configuration and issues. */
final readonly class Snapshot
{
    /**
     * @param list<Workflow>   $workflows
     * @param list<IssueType>  $issueTypes
     * @param list<Status>     $statuses
     * @param list<Transition> $transitions
     * @param list<Board>      $boards
     * @param list<BoardColumn> $columns
     * @param list<Issue>      $issues
     * @param array<string, Workdir> $workdirs
     * @param list<Team>       $teams
     * @param list<Role>       $roles
     * @param list<Profile>    $profiles
     * @param list<Agent>      $agents
     * @param list<GitRepository> $repositories
     * @param array<string, list<FieldDefinition>> $fields
     */
    public function __construct(
        public Workspace $workspace,
        public array $workflows,
        public array $issueTypes,
        public array $statuses,
        public array $transitions,
        public array $boards,
        public array $columns,
        public array $issues,
        public array $workdirs,
        public array $teams,
        public array $roles,
        public array $profiles,
        public array $agents,
        public array $repositories,
        public array $fields,
    ) {
    }
}
