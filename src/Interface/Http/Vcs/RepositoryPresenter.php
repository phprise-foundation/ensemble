<?php

declare(strict_types=1);

namespace App\Interface\Http\Vcs;

use App\Domain\Vcs\GitRepository;

final class RepositoryPresenter
{
    /** @return array<string, mixed> */
    public function present(GitRepository $repository): array
    {
        return [
            'id' => $repository->id()->value(),
            'workspaceId' => $repository->workspaceId()->value(),
            'name' => $repository->name(),
            'provider' => $repository->provider()->value,
            'url' => $repository->url()->value(),
            'baseBranch' => $repository->baseBranch()->value(),
            'secretId' => $repository->secretId()?->value(),
            'createdAt' => $repository->createdAt()->format(DATE_ATOM),
            'updatedAt' => $repository->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<GitRepository> $repositories
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $repositories): array
    {
        return array_map(fn(GitRepository $repository): array => $this->present($repository), $repositories);
    }
}
