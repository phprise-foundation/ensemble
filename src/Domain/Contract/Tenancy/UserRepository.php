<?php

declare(strict_types=1);

namespace App\Domain\Contract\Tenancy;

use App\Domain\Tenancy\Login;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\UserId;

interface UserRepository
{
    public function add(User $user): void;

    public function update(User $user): void;

    public function find(UserId $id): ?User;

    public function findByLogin(Login $login): ?User;

    /** @return list<User> */
    public function all(): array;

    public function existsAny(): bool;
}
