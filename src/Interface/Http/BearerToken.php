<?php

declare(strict_types=1);

namespace App\Interface\Http;

use Psr\Http\Message\ServerRequestInterface;

final class BearerToken
{
    public static function fromRequest(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }
}
