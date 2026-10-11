<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Domain\Contract\Authenticator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AuthController
{
    public function __construct(
        private Authenticator $authenticator,
        private JsonResponder $responder,
    ) {
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $password = (string) ($this->body($request)['password'] ?? '');
        if (!$this->authenticator->isValid($password)) {
            return $this->responder->error('invalid password', 401);
        }

        return $this->responder->respond(['token' => $password]);
    }

    private function body(ServerRequestInterface $request): array
    {
        /** @var mixed $data */
        $data = json_decode((string) $request->getBody(), true);

        return is_array($data) ? $data : [];
    }
}
