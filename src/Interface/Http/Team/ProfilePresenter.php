<?php

declare(strict_types=1);

namespace App\Interface\Http\Team;

use App\Domain\Team\Profile;

final class ProfilePresenter
{
    /** @return array<string, mixed> */
    public function present(Profile $profile): array
    {
        return [
            'id' => $profile->id()->value(),
            'workspaceId' => $profile->workspaceId()->value(),
            'teamId' => $profile->teamId()->value(),
            'name' => $profile->name(),
            'style' => $profile->style(),
            'tone' => $profile->tone(),
            'emojiUsage' => $profile->emojiUsage()->value,
            'advanced' => $profile->advanced(),
            'createdAt' => $profile->createdAt()->format(DATE_ATOM),
            'updatedAt' => $profile->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<Profile> $profiles
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $profiles): array
    {
        return array_map(fn(Profile $profile): array => $this->present($profile), $profiles);
    }
}
