<?php

declare(strict_types=1);

namespace App\Application\Vcs;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Security\SecretCipher;
use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Exception\ValidationException;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\ProbeResult;
use App\Domain\Vcs\Provider;
use App\Domain\Vcs\RepositoryProbe;
use App\Domain\Vcs\RepositoryUrl;

final readonly class ProbeRepository
{
    public function __construct(
        private WorkspaceSecretRepository $secrets,
        private SecretCipher $cipher,
        private RepositoryProbe $probe,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        string $url,
        Provider $provider,
        ?WorkspaceSecretId $secretId,
    ): ProbeResult {
        $this->guard->assertManager($actor, $workspaceId);
        $repositoryUrl = new RepositoryUrl($url);

        return $this->probe->probe($repositoryUrl->value(), $provider, $this->token($workspaceId, $secretId));
    }

    private function token(WorkspaceId $workspaceId, ?WorkspaceSecretId $secretId): ?string
    {
        if ($secretId === null) {
            return null;
        }

        $secret = $this->secrets->find($workspaceId, $secretId)
            ?? throw new ValidationException('secretId', 'Secret not found in this workspace.');

        return $this->cipher->decrypt($secret->ciphertext()->value());
    }
}
