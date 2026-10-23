<?php

declare(strict_types=1);

namespace App\Interface\Http\Security;

use App\Application\Security\CreateSecret;
use App\Application\Security\DeleteSecret;
use App\Application\Security\ListSecrets;
use App\Application\Security\ReadSecret;
use App\Application\Security\RotateSecret;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SecretController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListSecrets $listSecrets,
        private CreateSecret $createSecret,
        private ReadSecret $readSecret,
        private RotateSecret $rotateSecret,
        private DeleteSecret $deleteSecret,
        private SecretPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'secrets' => $this->presenter->presentMany($this->listSecrets->execute($user, $workspaceId)),
        ]);
    }

    public function create(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $secret = $this->createSecret->execute(
            $user,
            $workspaceId,
            JsonBody::string($body, 'name') ?? '',
            JsonBody::string($body, 'value') ?? '',
        );

        return $this->responder->respond(['secret' => $this->presenter->present($secret)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        WorkspaceSecretId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'secret' => $this->presenter->present($this->readSecret->execute($user, $workspaceId, $id)),
        ]);
    }

    public function rotate(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        WorkspaceSecretId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);

        return $this->responder->respond([
            'secret' => $this->presenter->present($this->rotateSecret->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'value') ?? '',
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        WorkspaceSecretId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteSecret->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }
}
