<?php

declare(strict_types=1);

namespace App\Application\Tenancy;

use App\Domain\Contract\Tenancy\UserRepository;
use App\Domain\Tenancy\User;

final readonly class UpdateUser
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(User $actor, ?string $displayName, ?string $avatar, ?string $avatarColor): User
    {
        if ($displayName !== null) {
            $actor->rename($displayName);
        }
        if ($avatar !== null) {
            $actor->changeAvatar($avatar === '' ? null : $avatar);
        }
        if (is_string($avatarColor) && $avatarColor !== '') {
            $actor->recolor($avatarColor);
        }
        $this->users->update($actor);

        return $actor;
    }
}
