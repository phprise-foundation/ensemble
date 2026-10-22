<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Application\Tenancy\MemberSummary;

final readonly class MemberPresenter
{
    public function __construct(private UserPresenter $users)
    {
    }

    /** @return array<string, mixed> */
    public function present(MemberSummary $member): array
    {
        return [
            'user' => $this->users->present($member->user()),
            'role' => $member->role()->value,
        ];
    }

    /**
     * @param list<MemberSummary> $members
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $members): array
    {
        return array_map(fn(MemberSummary $member): array => $this->present($member), $members);
    }
}
