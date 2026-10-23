<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Security\SecretCipher;
use App\Domain\Contract\Security\WorkspaceSecretRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Security\Ciphertext;
use App\Domain\Security\SecretValue;
use App\Domain\Security\WorkspaceSecret;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class RotateSecret
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
        WorkspaceSecretId $id,
        string $value,
    ): WorkspaceSecret {
        $this->guard->assertManager($actor, $workspaceId);
        $secret = $this->secrets->find($workspaceId, $id) ?? throw NotFoundException::secret();
        $secret->rotate(new Ciphertext($this->cipher->encrypt((new SecretValue($value))->value())));
        $this->secrets->update($secret);

        return $secret;
    }
}
