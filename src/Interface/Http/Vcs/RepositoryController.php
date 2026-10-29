<?php

declare(strict_types=1);

namespace App\Interface\Http\Vcs;

use App\Application\Security\ReadCurrentUser;
use App\Application\Vcs\CreateRepository;
use App\Application\Vcs\DeleteRepository;
use App\Application\Vcs\ListRepositories;
use App\Application\Vcs\ProbeRepository;
use App\Application\Vcs\ReadRepository;
use App\Application\Vcs\UpdateRepository;
use App\Domain\Security\WorkspaceSecretId;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\GitRepositoryId;
use App\Domain\Vcs\Provider;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RepositoryController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListRepositories $listRepositories,
        private CreateRepository $createRepository,
        private ReadRepository $readRepository,
        private UpdateRepository $updateRepository,
        private DeleteRepository $deleteRepository,
        private ProbeRepository $probeRepository,
        private RepositoryPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'repositories' => $this->presenter->presentMany($this->listRepositories->execute($user, $workspaceId)),
        ]);
    }

    public function create(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $repository = $this->createRepository->execute(
            $user,
            $workspaceId,
            JsonBody::string($body, 'name') ?? '',
            Provider::fromString(JsonBody::string($body, 'provider') ?? ''),
            JsonBody::string($body, 'url') ?? '',
            JsonBody::string($body, 'baseBranch') ?? 'main',
            $this->secretIdFrom($body),
        );

        return $this->responder->respond(['repository' => $this->presenter->present($repository)], 201);
    }

    public function probe(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $result = $this->probeRepository->execute(
            $user,
            $workspaceId,
            JsonBody::string($body, 'url') ?? '',
            Provider::fromString(JsonBody::string($body, 'provider') ?? ''),
            $this->secretIdFrom($body),
        );

        return $this->responder->respond([
            'ok' => $result->ok(),
            'message' => $result->message(),
        ]);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        GitRepositoryId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'repository' => $this->presenter->present($this->readRepository->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        GitRepositoryId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);

        return $this->responder->respond([
            'repository' => $this->presenter->present($this->updateRepository->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'name'),
                JsonBody::string($body, 'url'),
                JsonBody::string($body, 'baseBranch'),
                $this->secretIdFrom($body),
                array_key_exists('secretId', $body),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        GitRepositoryId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteRepository->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }

    /** @param array<array-key, mixed> $body */
    private function secretIdFrom(array $body): ?WorkspaceSecretId
    {
        $value = JsonBody::string($body, 'secretId');

        return $value === null || $value === '' ? null : new WorkspaceSecretId($value);
    }
}
