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
use App\Domain\Vcs\Provider;

final readonly class CreateRepository
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
        string $name,
        Provider $provider,
        string $url,
        string $baseBranch,
        ?WorkspaceSecretId $secretId,
    ): GitRepository {
        $this->guard->assertManager($actor, $workspaceId);
        $repository = new GitRepository(
            GitRepositoryId::generate(),
            $workspaceId,
            $name,
            $provider,
            $url,
            $baseBranch,
            $secretId,
        );
        if ($this->repositories->findByName($workspaceId, $repository->name()) !== null) {
            throw ConflictException::duplicateName('repository');
        }
        if ($secretId !== null) {
            $this->secrets->find($workspaceId, $secretId) ?? throw NotFoundException::secret();
        }

        $this->repositories->add($repository);

        return $repository;
    }
}
