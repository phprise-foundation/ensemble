<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\Team;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateTeam
{
    public function __construct(
        private TeamRepository $teams,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(User $actor, WorkspaceId $workspaceId, TeamId $id, ?string $name): Team
    {
        $this->guard->assertManager($actor, $workspaceId);
        $team = $this->teams->find($workspaceId, $id) ?? throw NotFoundException::team();
        if ($name !== null) {
            $team->rename($name);
        }
        $team->touch(new DateTimeImmutable());
        $this->teams->update($team);

        return $team;
    }
}
