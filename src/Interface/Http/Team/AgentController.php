<?php

declare(strict_types=1);

namespace App\Interface\Http\Team;

use App\Application\Security\ReadCurrentUser;
use App\Application\Team\CreateAgent;
use App\Application\Team\DeleteAgent;
use App\Application\Team\ListAgents;
use App\Application\Team\ReadAgent;
use App\Application\Team\UpdateAgent;
use App\Domain\Exception\ValidationException;
use App\Domain\Team\AgentId;
use App\Domain\Team\ProfileId;
use App\Domain\Team\RoleIds;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AgentController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListAgents $listAgents,
        private CreateAgent $createAgent,
        private ReadAgent $readAgent,
        private UpdateAgent $updateAgent,
        private DeleteAgent $deleteAgent,
        private AgentPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TeamId $teamId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'agents' => $this->presenter->presentMany($this->listAgents->execute($user, $workspaceId, $teamId)),
        ]);
    }

    public function create(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TeamId $teamId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $age = JsonBody::integer($body, 'age');
        $interval = JsonBody::integer($body, 'interval');
        $agent = $this->createAgent->execute(
            $user,
            $workspaceId,
            $teamId,
            JsonBody::string($body, 'name') ?? '',
            JsonBody::string($body, 'whoAmI') ?? '',
            $age === 0 ? null : $age,
            JsonBody::string($body, 'maritalStatus'),
            $this->roleIdsFrom($body) ?? new RoleIds(),
            $this->profileIdFrom($body),
            JsonBody::string($body, 'avatarColor'),
            JsonBody::string($body, 'portrait'),
            JsonBody::string($body, 'model') ?? '',
            JsonBody::string($body, 'fallbackModel') ?? '',
            $interval,
            JsonBody::boolean($body, 'active') ?? true,
        );

        return $this->responder->respond(['agent' => $this->presenter->present($agent)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        AgentId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'agent' => $this->presenter->present($this->readAgent->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        AgentId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $age = JsonBody::integer($body, 'age');
        $interval = JsonBody::integer($body, 'interval');
        $teamId = JsonBody::string($body, 'teamId');
        $agent = $this->updateAgent->execute(
            $user,
            $workspaceId,
            $id,
            JsonBody::string($body, 'name'),
            JsonBody::string($body, 'whoAmI'),
            array_key_exists('age', $body),
            $age === 0 ? null : $age,
            JsonBody::string($body, 'maritalStatus'),
            $teamId === null ? null : new TeamId($teamId),
            $this->roleIdsFrom($body),
            array_key_exists('profileId', $body),
            $this->profileIdFrom($body),
            JsonBody::string($body, 'avatarColor'),
            JsonBody::string($body, 'portrait'),
            JsonBody::string($body, 'model'),
            JsonBody::string($body, 'fallbackModel'),
            $interval,
            JsonBody::boolean($body, 'active'),
        );

        return $this->responder->respond(['agent' => $this->presenter->present($agent)]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        AgentId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteAgent->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function roleIdsFrom(array $body): ?RoleIds
    {
        if (!array_key_exists('roleIds', $body)) {
            return null;
        }

        $values = JsonBody::stringList($body, 'roleIds');
        if ($values === null) {
            throw new ValidationException('roleIds', 'Role ids must be a list of strings.');
        }

        try {
            return RoleIds::fromStrings($values);
        } catch (InvalidArgumentException) {
            throw new ValidationException('roleIds', 'Every role id must be a 32-character hexadecimal string.');
        }
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function profileIdFrom(array $body): ?ProfileId
    {
        $value = JsonBody::string($body, 'profileId');
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new ProfileId($value);
        } catch (InvalidArgumentException) {
            throw new ValidationException('profileId', 'Profile id must be a 32-character hexadecimal string.');
        }
    }
}
