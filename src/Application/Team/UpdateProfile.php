<?php

declare(strict_types=1);

namespace App\Application\Team;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Team\ProfileRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Team\EmojiUsage;
use App\Domain\Team\Profile;
use App\Domain\Team\ProfileId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;

final readonly class UpdateProfile
{
    public function __construct(
        private ProfileRepository $profiles,
        private WorkspaceGuard $guard,
    ) {
    }

    /**
     * @param array<string, mixed>|null $advanced
     */
    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        ProfileId $id,
        ?string $name,
        ?string $style,
        ?string $tone,
        ?EmojiUsage $emojiUsage,
        ?array $advanced,
    ): Profile {
        $this->guard->assertManager($actor, $workspaceId);
        $profile = $this->profiles->find($workspaceId, $id) ?? throw NotFoundException::profile();
        if ($name !== null) {
            $profile->rename($name);
        }
        if ($style !== null) {
            $profile->restyle($style);
        }
        if ($tone !== null) {
            $profile->retone($tone);
        }
        if ($emojiUsage !== null) {
            $profile->useEmoji($emojiUsage);
        }
        if ($advanced !== null) {
            $profile->advance($advanced);
        }
        $profile->touch(new DateTimeImmutable());
        $this->profiles->update($profile);

        return $profile;
    }
}
