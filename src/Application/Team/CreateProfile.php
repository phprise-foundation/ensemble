<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Contract\Team\TeamRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\EmojiUsage;
use App\Domain\Team\Profile;
use App\Domain\Team\ProfileId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateProfile
{
    public function __construct(
        private TeamRepository $teams,
        private ProfileRepository $profiles,
        private WorkspaceGuard $guard,
    ) {
    }

    /**
     * @param array<string, mixed> $advanced
     */
    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        TeamId $teamId,
        string $name,
        string $style,
        string $tone,
        EmojiUsage $emojiUsage,
        array $advanced,
    ): Profile {
        $this->guard->assertManager($actor, $workspaceId);
        $this->teams->find($workspaceId, $teamId) ?? throw NotFoundException::team();

        $profile = new Profile(
            ProfileId::generate(),
            $workspaceId,
            $teamId,
            $name,
            $style,
            $tone,
            $emojiUsage,
            $advanced,
        );
        $this->profiles->add($profile);

        return $profile;
    }
}
