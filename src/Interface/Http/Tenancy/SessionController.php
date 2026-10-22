<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Application\Security\AuthenticateUser;
use App\Application\Security\ReadCurrentUser;
use App\Application\Tenancy\ListWorkspaces;
use App\Domain\Contract\Tenancy\SessionRepository;
use App\Domain\Security\PlainPassword;
use App\Domain\Security\SessionTokenHash;
use App\Domain\Tenancy\Login;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SessionController
{
    public function __construct(
        private AuthenticateUser $authenticate,
        private ReadCurrentUser $currentUser,
        private SessionRepository $sessions,
        private ListWorkspaces $workspaces,
        private UserPresenter $users,
        private WorkspacePresenter $workspacePresenter,
        private JsonResponder $responder,
    ) {
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonBody::from($request);
        $session = $this->authenticate->execute(
            new Login(JsonBody::string($body, 'login') ?? ''),
            new PlainPassword(JsonBody::string($body, 'password') ?? ''),
        );

        return $this->responder->respond([
            'token' => $session->token(),
            'user' => $this->users->present($session->user()),
        ]);
    }

    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $token = BearerToken::fromRequest($request);
        if ($token !== null && $token !== '') {
            $this->sessions->delete(SessionTokenHash::fromToken($token));
        }

        return $this->responder->respond(['ok' => true]);
    }

    public function me(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'user' => $this->users->present($user),
            'workspaces' => $this->workspacePresenter->presentMany($this->workspaces->execute($user)),
        ]);
    }
}
