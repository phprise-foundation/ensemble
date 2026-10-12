<?php

declare(strict_types=1);

namespace App\Domain\Chat;

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
