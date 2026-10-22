<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Application\Security\ReadCurrentUser;
use App\Application\Tenancy\RegisterUser;
use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Tenancy\UserRepository;
use App\Domain\Security\PlainPassword;
use App\Domain\Tenancy\Login;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UserController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private WorkspaceGuard $guard,
        private RegisterUser $register,
        private UserRepository $users,
        private UserPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $this->guard->assertAdmin($this->currentUser->execute(BearerToken::fromRequest($request)));

        return $this->responder->respond(['users' => $this->presenter->presentMany($this->users->all())]);
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $this->guard->assertAdmin($this->currentUser->execute(BearerToken::fromRequest($request)));
        $body = JsonBody::from($request);
        $user = $this->register->execute(
            new Login(JsonBody::string($body, 'login') ?? ''),
            new PlainPassword(JsonBody::string($body, 'password') ?? ''),
            JsonBody::string($body, 'displayName') ?? '',
            JsonBody::boolean($body, 'isAdmin') ?? false,
        );

        return $this->responder->respond(['user' => $this->presenter->present($user)], 201);
    }
}
