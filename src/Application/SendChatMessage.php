<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Chat\Message;
use App\Domain\Chat\MessageRole;
use App\Domain\Contract\ChatAgent;
use App\Domain\Contract\ChatRepository;
use DateTimeImmutable;

final readonly class SendChatMessage
{
    public function __construct(
        private ChatRepository $repository,
        private ChatAgent $agent,
    ) {
    }

    public function execute(string $body): void
    {
        $this->repository->append(new Message(MessageRole::User, $body, new DateTimeImmutable()));
        $this->repository->append($this->agent->reply($this->repository->conversation()));
    }
}
