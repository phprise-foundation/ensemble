<?php

declare(strict_types=1);

namespace App\Infrastructure\Workspace;

use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Contract\Kanban\IssueTypeRepository;
use App\Domain\Contract\Kanban\StatusRepository;
use App\Domain\Contract\Kanban\TransitionRepository;
use App\Domain\Contract\Kanban\WorkflowRepository;
use App\Domain\Contract\Team\AgentRepository;
use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Contract\Team\RoleRepository;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Contract\Tenancy\WorkspaceDefaults;
use App\Domain\Exception\ValidationException;
use App\Domain\Kanban\Board;
use App\Domain\Kanban\BoardColumn;
use App\Domain\Kanban\BoardColumnId;
use App\Domain\Kanban\BoardFilters;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Level;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\Status;
use App\Domain\Kanban\StatusId;
use App\Domain\Kanban\StatusIds;
use App\Domain\Kanban\StatusType;
use App\Domain\Kanban\Transition;
use App\Domain\Kanban\TransitionActor;
use App\Domain\Kanban\TransitionEndpoint;
use App\Domain\Kanban\TransitionId;
use App\Domain\Kanban\Workflow;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Model\ModelCatalog;
use App\Domain\Model\ModelId;
use App\Domain\Team\Agent;
use App\Domain\Team\AgentId;
use App\Domain\Team\AvatarColor;
use App\Domain\Team\EmojiUsage;
use App\Domain\Team\Gender;
use App\Domain\Team\LoopInterval;
use App\Domain\Team\MaritalStatus;
use App\Domain\Team\Portrait;
use App\Domain\Team\Profile;
use App\Domain\Team\ProfileId;
use App\Domain\Team\Role;
use App\Domain\Team\RoleId;
use App\Domain\Team\RoleIds;
use App\Domain\Team\Team;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use Override;
use Symfony\Component\Yaml\Yaml;

final class YamlWorkspaceDefaults implements WorkspaceDefaults
{
    private const MAX_PORTRAIT_BYTES = 2_097_152;

    private const PALETTE = [
        '#4c9aff', '#ff991f', '#8777d9', '#00b8d9', '#36b37e', '#ff5630', '#ffab00', '#2684ff', '#6554c0',
    ];

    public function __construct(
        private string $path,
        private ModelCatalog $catalog,
        private TeamRepository $teams,
        private RoleRepository $roles,
        private ProfileRepository $profiles,
        private AgentRepository $agents,
        private WorkflowRepository $workflows,
        private IssueTypeRepository $issueTypes,
        private StatusRepository $statuses,
        private TransitionRepository $transitions,
        private BoardRepository $boards,
        private BoardColumnRepository $columns,
    ) {
    }

    #[Override]
    public function seed(WorkspaceId $workspaceId): void
    {
        $data = $this->read();
        $teamData = $this->map($data['team'] ?? null, 'team');
        $team = new Team(TeamId::generate(), $workspaceId, $this->text($teamData, 'name', 'team.name'));
        $this->teams->add($team);

        $roles = $this->seedRoles($workspaceId, $team->id(), $this->listOfMaps($teamData['roles'] ?? null, 'team.roles'));
        $profiles = $this->seedProfiles($workspaceId, $team->id(), $this->listOfMaps($teamData['profiles'] ?? null, 'team.profiles'));
        $this->seedAgents($workspaceId, $team->id(), $roles, $profiles, $this->listOfMaps($teamData['agents'] ?? null, 'team.agents'));

        $workflows = $this->seedWorkflows($workspaceId, $roles, $data);
        $this->seedBoards($workspaceId, $workflows, $data);
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $raw = Yaml::parseFile($this->path);
        if (! is_array($raw)) {
            throw new ValidationException('defaults', 'The workspace defaults file must be a mapping.');
        }
        /** @var array<string, mixed> $raw */
        return $raw;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, array{id: RoleId, model: string}>
     */
    private function seedRoles(WorkspaceId $workspaceId, TeamId $teamId, array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $key = $this->text($item, 'key', 'team.roles.key');
            if (isset($map[$key])) {
                throw new ValidationException('defaults', sprintf('Duplicate role key "%s".', $key));
            }
            $role = new Role(
                RoleId::generate(),
                $workspaceId,
                $teamId,
                $this->text($item, 'name', 'team.roles.name'),
                $this->optionalText($item, 'description'),
                $this->bool($item, 'unblock'),
                $this->bool($item, 'interactive'),
                implement: $this->bool($item, 'implement'),
                review: $this->bool($item, 'review'),
                test: $this->bool($item, 'test'),
                merge: $this->bool($item, 'merge'),
                deploy: $this->bool($item, 'deploy'),
                instructions: $this->optionalText($item, 'instructions'),
            );
            $this->roles->add($role);
            $map[$key] = ['id' => $role->id(), 'model' => $this->optionalText($item, 'model')];
        }

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, ProfileId>
     */
    private function seedProfiles(WorkspaceId $workspaceId, TeamId $teamId, array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $key = $this->text($item, 'key', 'team.profiles.key');
            if (isset($map[$key])) {
                throw new ValidationException('defaults', sprintf('Duplicate profile key "%s".', $key));
            }
            $advanced = isset($item['advanced']) ? $this->map($item['advanced'], 'team.profiles.advanced') : [];
            $profile = new Profile(
                ProfileId::generate(),
                $workspaceId,
                $teamId,
                $this->text($item, 'name', 'team.profiles.name'),
                $this->optionalText($item, 'style'),
                $this->optionalText($item, 'tone'),
                EmojiUsage::fromString($this->optionalText($item, 'emoji_usage') ?: 'Never'),
                $advanced,
            );
            $this->profiles->add($profile);
            $map[$key] = $profile->id();
        }

        return $map;
    }

    /**
     * @param array<string, array{id: RoleId, model: string}> $roles
     * @param array<string, ProfileId> $profiles
     * @param list<array<string, mixed>> $items
     */
    private function seedAgents(WorkspaceId $workspaceId, TeamId $teamId, array $roles, array $profiles, array $items): void
    {
        $index = 0;
        foreach ($items as $item) {
            $roleKey = $this->text($item, 'role', 'team.agents.role');
            $role = $roles[$roleKey] ?? throw new ValidationException('defaults', sprintf('Unknown role "%s".', $roleKey));
            $profileKey = $this->text($item, 'profile', 'team.agents.profile');
            $profileId = $profiles[$profileKey] ?? throw new ValidationException('defaults', sprintf('Unknown profile "%s".', $profileKey));
            $model = $this->optionalText($item, 'model');
            $model = $model !== '' ? $model : ($role['model'] !== '' ? $role['model'] : $this->catalog->defaultId()->value());
            $color = $this->optionalText($item, 'avatar_color');
            $color = $color !== '' ? $color : self::PALETTE[$index % count(self::PALETTE)];
            $marital = $this->optionalText($item, 'marital_status');
            $gender = $this->optionalText($item, 'gender');
            $agent = new Agent(
                AgentId::generate(),
                $workspaceId,
                $teamId,
                $this->text($item, 'name', 'team.agents.name'),
                $this->optionalText($item, 'who_am_i'),
                $this->optionalInt($item, 'age'),
                MaritalStatus::fromString($marital !== '' ? $marital : 'Single'),
                new RoleIds([$role['id']]),
                $profileId,
                new AvatarColor($color),
                new Portrait($this->portrait($this->optionalText($item, 'avatar'))),
                $this->model($model),
                '',
                new LoopInterval(300),
                true,
                gender: Gender::fromString($gender !== '' ? $gender : 'not_informed'),
            );
            $this->agents->add($agent);
            $index++;
        }
    }

    /**
     * @param array<string, array{id: RoleId, model: string}> $roles
     * @param array<string, mixed> $data
     * @return array<string, array{workflow: Workflow, issueTypes: array<string, IssueType>, statuses: array<string, array<string, Status>>}>
     */
    private function seedWorkflows(WorkspaceId $workspaceId, array $roles, array $data): array
    {
        $map = [];
        foreach ($this->listOfMaps($data['workflows'] ?? null, 'workflows') as $item) {
            $key = $this->text($item, 'key', 'workflows.key');
            if (isset($map[$key])) {
                throw new ValidationException('defaults', sprintf('Duplicate workflow key "%s".', $key));
            }
            $workflow = new Workflow(
                WorkflowId::generate(),
                $workspaceId,
                new Slug($key),
                $this->text($item, 'name', 'workflows.name'),
            );
            $this->workflows->add($workflow);

            $issueTypes = [];
            foreach ($this->listOfMaps($item['issue_types'] ?? null, 'workflows.issue_types') as $position => $typeData) {
                $typeKey = $this->text($typeData, 'key', 'workflows.issue_types.key');
                $issueType = new IssueType(
                    IssueTypeId::generate(),
                    $workspaceId,
                    $workflow->id(),
                    new Slug($typeKey),
                    $this->text($typeData, 'name', 'workflows.issue_types.name'),
                    new Level($this->int($typeData, 'level', 'workflows.issue_types.level')),
                    new Position($position),
                );
                $this->issueTypes->add($issueType);
                $issueTypes[$typeKey] = $issueType;
            }

            $statuses = $this->seedStatuses($workspaceId, $workflow, $issueTypes, $roles, $this->listOfMaps($item['statuses'] ?? null, 'workflows.statuses'));
            $this->seedTransitions($workspaceId, $workflow, $issueTypes, $statuses, $this->listOfMaps($item['transitions'] ?? null, 'workflows.transitions'));

            $map[$key] = ['workflow' => $workflow, 'issueTypes' => $issueTypes, 'statuses' => $statuses];
        }

        return $map;
    }

    /**
     * @param array<string, IssueType> $issueTypes
     * @param array<string, array{id: RoleId, model: string}> $roles
     * @param list<array<string, mixed>> $items
     * @return array<string, array<string, Status>>
     */
    private function seedStatuses(WorkspaceId $workspaceId, Workflow $workflow, array $issueTypes, array $roles, array $items): array
    {
        $map = [];
        foreach ($issueTypes as $typeKey => $issueType) {
            $position = 0;
            foreach ($items as $item) {
                $key = $this->text($item, 'key', 'workflows.statuses.key');
                $type = StatusType::fromString($this->text($item, 'type', 'workflows.statuses.type'));
                $color = $this->optionalText($item, 'color');
                $color = $color !== '' ? $color : match ($type) {
                    StatusType::Initial => '#6b7280',
                    StatusType::Transitional => '#4c9aff',
                    StatusType::Final => '#36b37e',
                };
                $status = new Status(
                    StatusId::generate(),
                    $workspaceId,
                    $workflow->id(),
                    $issueType->id(),
                    new Slug($key),
                    $this->text($item, 'name', 'workflows.statuses.name'),
                    $type,
                    $color,
                    new Position($position),
                    roleIds: $this->roleIds($this->listOfStrings($item['roles'] ?? [], 'workflows.statuses.roles'), $roles),
                );
                $this->statuses->add($status);
                $map[$typeKey][$key] = $status;
                $position++;
            }
        }

        return $map;
    }

    /**
     * @param array<string, IssueType> $issueTypes
     * @param array<string, array<string, Status>> $statuses
     * @param list<array<string, mixed>> $items
     */
    private function seedTransitions(WorkspaceId $workspaceId, Workflow $workflow, array $issueTypes, array $statuses, array $items): void
    {
        foreach (array_keys($issueTypes) as $typeKey) {
            foreach ($items as $item) {
                $fromKey = $this->text($item, 'from', 'workflows.transitions.from');
                $to = $this->statusFor($statuses, $typeKey, $this->text($item, 'to', 'workflows.transitions.to'));
                $from = $fromKey === 'any' || $fromKey === '*'
                    ? TransitionEndpoint::any()
                    : TransitionEndpoint::status($this->statusFor($statuses, $typeKey, $fromKey)->id());
                $this->transitions->add(new Transition(
                    TransitionId::generate(),
                    $workspaceId,
                    $workflow->id(),
                    $from,
                    $to->id(),
                    $this->actor($this->text($item, 'actor', 'workflows.transitions.actor')),
                ));
            }
        }
    }

    /**
     * @param array<string, array{workflow: Workflow, issueTypes: array<string, IssueType>, statuses: array<string, array<string, Status>>}> $workflows
     * @param array<string, mixed> $data
     */
    private function seedBoards(WorkspaceId $workspaceId, array $workflows, array $data): void
    {
        foreach ($this->listOfMaps($data['boards'] ?? null, 'boards') as $item) {
            $workflowKey = $this->text($item, 'workflow', 'boards.workflow');
            $workflowData = $workflows[$workflowKey] ?? throw new ValidationException('defaults', sprintf('Unknown board workflow "%s".', $workflowKey));
            $level = $this->int($item, 'issue_level', 'boards.issue_level');
            foreach ($workflowData['issueTypes'] as $issueType) {
                if ($issueType->level()->value() !== $level) {
                    throw new ValidationException('defaults', sprintf('Board "%s" issue level does not match its issue types.', $this->text($item, 'key', 'boards.key')));
                }
            }
            $backlog = $this->statusIdsFor(
                $workflowData['issueTypes'],
                $workflowData['statuses'],
                $this->listOfStrings($item['backlog_statuses'] ?? [], 'boards.backlog_statuses'),
            );
            $board = new Board(
                BoardId::generate(),
                $workspaceId,
                $workflowData['workflow']->id(),
                new Slug($this->text($item, 'key', 'boards.key')),
                $this->text($item, 'name', 'boards.name'),
                new BoardFilters(),
                backlogStatusIds: $backlog,
            );
            $this->boards->add($board);
            foreach ($this->listOfMaps($item['columns'] ?? null, 'boards.columns') as $position => $columnData) {
                $this->columns->add(new BoardColumn(
                    BoardColumnId::generate(),
                    $workspaceId,
                    $board->id(),
                    $this->text($columnData, 'name', 'boards.columns.name'),
                    new Position($position),
                    $this->optionalInt($columnData, 'wip'),
                    $this->statusIdsFor(
                        $workflowData['issueTypes'],
                        $workflowData['statuses'],
                        $this->listOfStrings($columnData['statuses'] ?? [], 'boards.columns.statuses'),
                    ),
                ));
            }
        }
    }

    /**
     * @param array<string, IssueType> $issueTypes
     * @param array<string, array<string, Status>> $statuses
     * @param list<string> $keys
     * @return StatusIds
     */
    private function statusIdsFor(array $issueTypes, array $statuses, array $keys): StatusIds
    {
        $ids = [];
        foreach (array_keys($issueTypes) as $typeKey) {
            foreach ($keys as $key) {
                $ids[] = $this->statusFor($statuses, $typeKey, $key)->id();
            }
        }

        return new StatusIds($ids);
    }

    /** @param array<string, array<string, Status>> $statuses */
    private function statusFor(array $statuses, string $typeKey, string $key): Status
    {
        return $statuses[$typeKey][$key] ?? throw new ValidationException('defaults', sprintf('Unknown status "%s" for issue type "%s".', $key, $typeKey));
    }

    /**
     * @param list<string> $keys
     * @param array<string, array{id: RoleId, model: string}> $roles
     */
    private function roleIds(array $keys, array $roles): RoleIds
    {
        $ids = [];
        foreach ($keys as $key) {
            $ids[] = ($roles[$key] ?? throw new ValidationException('defaults', sprintf('Unknown role "%s".', $key)))['id'];
        }

        return new RoleIds($ids);
    }

    private function actor(string $value): TransitionActor
    {
        return match ($value) {
            'system', 'agent' => TransitionActor::Agent,
            'user' => TransitionActor::User,
            'both' => TransitionActor::Both,
            default => throw new ValidationException('workflows.transitions.actor', sprintf('Unknown transition actor "%s".', $value)),
        };
    }

    private function model(string $value): string
    {
        $id = new ModelId($value);
        if ($this->catalog->find($id) === null) {
            throw new ValidationException('modelId', sprintf('Unknown model "%s" in the workspace defaults.', $value));
        }

        return $id->value();
    }

    private function portrait(string $relative): string
    {
        if ($relative === '') {
            return '';
        }
        $base = realpath(dirname($this->path));
        if ($base === false) {
            throw new ValidationException('avatar', 'The defaults directory is not readable.');
        }
        $file = realpath($base . '/' . $relative);
        if ($file === false) {
            throw new ValidationException('avatar', sprintf('Portrait "%s" was not found.', $relative));
        }
        if (! str_starts_with($file, $base) || ! is_file($file)) {
            throw new ValidationException('avatar', sprintf('Portrait "%s" was not found.', $relative));
        }
        $mime = mime_content_type($file);
        if ($mime === false || ! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new ValidationException('avatar', sprintf('Portrait "%s" must be png, jpeg or webp.', $relative));
        }
        $raw = file_get_contents($file);
        if ($raw === false || strlen($raw) > self::MAX_PORTRAIT_BYTES) {
            throw new ValidationException('avatar', sprintf('Portrait "%s" must decode to at most 2 MiB.', $relative));
        }

        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }

    /** @return array<string, mixed> */
    private function map(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new ValidationException($field, sprintf('The "%s" section must be a mapping.', $field));
        }
        /** @var array<string, mixed> $value */
        return $value;
    }

    /** @return list<array<string, mixed>> */
    private function listOfMaps(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new ValidationException($field, sprintf('The "%s" section must be a list.', $field));
        }
        $items = [];
        foreach (array_keys($value) as $key) {
            $items[] = $this->map($value[$key], $field);
        }

        return $items;
    }

    /** @return list<string> */
    private function listOfStrings(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new ValidationException($field, sprintf('The "%s" section must be a list.', $field));
        }
        $items = [];
        foreach (array_keys($value) as $key) {
            if (! is_string($value[$key])) {
                throw new ValidationException($field, sprintf('The "%s" section must contain strings.', $field));
            }
            $items[] = $value[$key];
        }

        return $items;
    }

    /** @param array<string, mixed> $data */
    private function text(array $data, string $key, string $field): string
    {
        if (! isset($data[$key]) || ! is_string($data[$key]) || trim($data[$key]) === '') {
            throw new ValidationException($field, sprintf('The "%s" value is required.', $field));
        }

        return trim($data[$key]);
    }

    /** @param array<string, mixed> $data */
    private function optionalText(array $data, string $key): string
    {
        if (! isset($data[$key])) {
            return '';
        }
        if (! is_string($data[$key])) {
            throw new ValidationException($key, sprintf('The "%s" value must be a string.', $key));
        }

        return trim($data[$key]);
    }

    /** @param array<string, mixed> $data */
    private function bool(array $data, string $key): bool
    {
        if (! array_key_exists($key, $data)) {
            return false;
        }
        if (! is_bool($data[$key])) {
            throw new ValidationException($key, sprintf('The "%s" value must be a boolean.', $key));
        }

        return $data[$key];
    }

    /** @param array<string, mixed> $data */
    private function int(array $data, string $key, string $field): int
    {
        if (! isset($data[$key]) || ! is_int($data[$key])) {
            throw new ValidationException($field, sprintf('The "%s" value must be an integer.', $field));
        }

        return $data[$key];
    }

    /** @param array<string, mixed> $data */
    private function optionalInt(array $data, string $key): ?int
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }
        if (! is_int($data[$key])) {
            throw new ValidationException($key, sprintf('The "%s" value must be an integer.', $key));
        }

        return $data[$key];
    }
}
