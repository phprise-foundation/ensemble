<?php

declare(strict_types=1);

namespace App\Application\Vcs;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\GitRepository;
use App\Domain\Vcs\GitRepositoryId;
use DateTimeImmutable;

final readonly class UpdateRepository
{
    public function __construct(
        private GitRepositories $repositories,
        private WorkspaceSecretRepository $secrets,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        GitRepositoryId $id,
        ?string $name,
        ?string $url,
        ?string $baseBranch,
        ?WorkspaceSecretId $secretId,
        bool $changeSecret,
    ): GitRepository {
        $this->guard->assertManager($actor, $workspaceId);
        $repository = $this->repositories->find($workspaceId, $id) ?? throw NotFoundException::repository();
        if ($name !== null) {
            $repository->rename($name);
            $this->guardName($workspaceId, $repository);
        }
        if ($url !== null) {
            $repository->changeUrl($url);
        }
        if ($baseBranch !== null) {
            $repository->changeBaseBranch($baseBranch);
        }
        if ($changeSecret) {
            if ($secretId !== null) {
                $this->secrets->find($workspaceId, $secretId) ?? throw NotFoundException::secret();
            }
            $repository->useSecret($secretId);
        }
        $repository->touch(new DateTimeImmutable());
        $this->repositories->update($repository);

        return $repository;
    }

    private function guardName(WorkspaceId $workspaceId, GitRepository $repository): void
    {
        $existing = $this->repositories->findByName($workspaceId, $repository->name());
        if ($existing === null || $existing->id()->equals($repository->id())) {
            return;
        }

        throw ConflictException::duplicateName('repository');
    }
}
