<?php

declare(strict_types=1);

namespace App\Interface\Http;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    public function respond(array $payload, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], Utils::streamFor($this->encode($payload)));
    }

    public function error(string $message, int $status): ResponseInterface
    {
        return $this->respond(['error' => $message], $status);
    }

    public function validationError(string $field, string $message): ResponseInterface
    {
        return $this->respond(['error' => $message, 'field' => $field], 422);
    }

    private function encode(array $payload): string
    {
        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
