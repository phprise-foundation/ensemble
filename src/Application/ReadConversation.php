<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Chat\Conversation;
use App\Domain\Contract\ChatRepository;

final readonly class ReadConversation
{
    public function __construct(private ChatRepository $repository)
    {
    }

    public function execute(): Conversation
    {
        return $this->repository->conversation();
    }
}
