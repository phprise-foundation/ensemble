<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Security\SecretCipher;
use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Exception\ConflictException;
use App\Domain\Security\SecretName;
use App\Domain\Security\SecretValue;
use App\Domain\Security\WorkspaceSecret;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class CreateSecret
{
    public function __construct(
        private WorkspaceSecretRepository $secrets,
        private SecretCipher $cipher,
        private WorkspaceGuard $guard,
    ) {
    }

    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        string $name,
        string $value,
    ): WorkspaceSecret {
        $this->guard->assertManager($actor, $workspaceId);
        $secretName = new SecretName($name);
        if ($this->secrets->findByName($workspaceId, $secretName->value()) !== null) {
            throw ConflictException::duplicateName('secret');
        }

        $secret = new WorkspaceSecret(
            WorkspaceSecretId::generate(),
            $workspaceId,
            $secretName->value(),
            $this->cipher->encrypt((new SecretValue($value))->value()),
        );
        $this->secrets->add($secret);

        return $secret;
    }
}
