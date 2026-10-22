<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Domain\Exception\AccessDeniedException;
use App\Domain\Exception\AuthenticationFailedException;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Interface\Http\JsonResponder;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final readonly class TenancyErrorResponder
{
    public function __construct(private JsonResponder $responder)
    {
    }

    public function respond(Throwable $error): ResponseInterface
    {
        return match (true) {
            $error instanceof ValidationException => $this->responder->validationError($error->field(), $error->getMessage()),
            $error instanceof AuthenticationFailedException => $this->responder->error($error->getMessage(), 401),
            $error instanceof AccessDeniedException => $this->responder->error($error->getMessage(), 403),
            $error instanceof NotFoundException => $this->responder->error($error->getMessage(), 404),
            $error instanceof ConflictException => $this->responder->error($error->getMessage(), 409),
            $error instanceof InvalidArgumentException => $this->responder->error($error->getMessage(), 422),
            default => $this->internal($error),
        };
    }

    private function internal(Throwable $error): ResponseInterface
    {
        error_log($error->getMessage());

        return $this->responder->error('internal server error', 500);
    }
}
