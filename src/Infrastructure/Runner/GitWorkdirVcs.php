<?php

declare(strict_types=1);

namespace App\Infrastructure\Runner;

use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Contract\Vcs\GitRepositories;
use App\Domain\Kanban\Workdir;
use App\Domain\Runner\Outcome;
use App\Domain\Runner\WorkdirVcs;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use App\Infrastructure\Security\SodiumSecretCipher;
use Override;

final readonly class GitWorkdirVcs implements WorkdirVcs
{
    public function __construct(
        private GitClient $git,
        private GitRepositories $repositories,
        private WorkspaceSecretRepository $secrets,
        private SodiumSecretCipher $cipher,
    ) {
    }

    #[Override]
    public function prepare(Workdir $workdir, string $path): Outcome
    {
        $repositoryId = $workdir->repositoryId();
        if ($repositoryId === null) {
            return Outcome::failed('the workdir has no repository');
        }

        $repository = $this->repositories->find($workdir->workspaceId(), $repositoryId);
        if ($repository === null) {
            return Outcome::failed('the referenced repository no longer exists');
        }

        return $this->git->ensureClone(
            $repository->url()->value(),
            $repository->baseBranch()->value(),
            $this->branchOr($workdir),
            $path,
            $this->token($repository->workspaceId(), $repository->secretId()),
        );
    }

    #[Override]
    public function publish(Workdir $workdir, string $path): Outcome
    {
        $repositoryId = $workdir->repositoryId();
        if ($repositoryId === null) {
            return Outcome::failed('the workdir has no repository');
        }

        $repository = $this->repositories->find($workdir->workspaceId(), $repositoryId);
        if ($repository === null) {
            return Outcome::failed('the referenced repository no longer exists');
        }

        return $this->git->commitAndPush(
            $path,
            $this->branchOr($workdir),
            $this->token($repository->workspaceId(), $repository->secretId()),
        );
    }

    private function branchOr(Workdir $workdir): string
    {
        return $workdir->branch()?->value() ?? '';
    }

    private function token(WorkspaceId $workspaceId, ?WorkspaceSecretId $secretId): ?string
    {
        if ($secretId === null) {
            return null;
        }

        $secret = $this->secrets->find($workspaceId, $secretId);
        if ($secret === null) {
            return null;
        }

        return $this->cipher->decrypt($secret->ciphertext()->value());
    }
}
