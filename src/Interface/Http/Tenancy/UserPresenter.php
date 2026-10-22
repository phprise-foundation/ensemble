<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Domain\Tenancy\User;

final class UserPresenter
{
    /** @return array<string, mixed> */
    public function present(User $user): array
    {
        return [
            'id' => $user->id()->value(),
            'login' => $user->login()->value(),
            'displayName' => $user->displayName(),
            'avatar' => $user->avatar(),
            'avatarColor' => $user->avatarColor(),
            'isAdmin' => $user->isAdmin(),
            'createdAt' => $user->createdAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<User> $users
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $users): array
    {
        return array_map(fn(User $user): array => $this->present($user), $users);
    }
}
