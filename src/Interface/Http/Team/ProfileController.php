<?php

declare(strict_types=1);

namespace App\Interface\Http\Team;

use App\Application\Security\ReadCurrentUser;
use App\Application\Team\CreateProfile;
use App\Application\Team\DeleteProfile;
use App\Application\Team\ListProfiles;
use App\Application\Team\ReadProfile;
use App\Application\Team\UpdateProfile;
use App\Domain\Exception\ValidationException;
use App\Domain\Team\EmojiUsage;
use App\Domain\Team\ProfileId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ProfileController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListProfiles $listProfiles,
        private CreateProfile $createProfile,
        private ReadProfile $readProfile,
        private UpdateProfile $updateProfile,
        private DeleteProfile $deleteProfile,
        private ProfilePresenter $presenter,
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
            'profiles' => $this->presenter->presentMany($this->listProfiles->execute($user, $workspaceId, $teamId)),
        ]);
    }

    public function create(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        TeamId $teamId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $profile = $this->createProfile->execute(
            $user,
            $workspaceId,
            $teamId,
            JsonBody::string($body, 'name') ?? '',
            JsonBody::string($body, 'style') ?? '',
            JsonBody::string($body, 'tone') ?? '',
            EmojiUsage::fromString(JsonBody::string($body, 'emojiUsage') ?? 'Never'),
            $this->advancedFrom($body) ?? [],
        );

        return $this->responder->respond(['profile' => $this->presenter->present($profile)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        ProfileId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'profile' => $this->presenter->present($this->readProfile->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        ProfileId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $emoji = JsonBody::string($body, 'emojiUsage');

        return $this->responder->respond([
            'profile' => $this->presenter->present($this->updateProfile->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'name'),
                JsonBody::string($body, 'style'),
                JsonBody::string($body, 'tone'),
                $emoji === null ? null : EmojiUsage::fromString($emoji),
                $this->advancedFrom($body),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        ProfileId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteProfile->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }

    /**
     * @param array<array-key, mixed> $body
     * @return array<string, mixed>|null
     */
    private function advancedFrom(array $body): ?array
    {
        if (!array_key_exists('advanced', $body)) {
            return null;
        }

        return JsonBody::object($body, 'advanced')
            ?? throw new ValidationException('advanced', 'Profile advanced attributes must be a JSON object.');
    }
}
