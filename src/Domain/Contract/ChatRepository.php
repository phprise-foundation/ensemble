<?php

declare(strict_types=1);

namespace App\Domain\Contract;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;

interface ChatRepository
{
    public function conversation(): Conversation;

    public function append(Message $message): void;
}
