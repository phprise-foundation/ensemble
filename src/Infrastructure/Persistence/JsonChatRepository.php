<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Chat\Conversation;
use App\Domain\Chat\Message;
use App\Domain\Contract\ChatRepository;
use Override;

final class JsonChatRepository implements ChatRepository
{
    public function __construct(
        private JsonFile $file,
        private MessageMapper $mapper,
    ) {
    }

    #[Override]
    public function conversation(): Conversation
    {
        return new Conversation(array_map(
            fn(array $data): Message => $this->mapper->fromArray($data),
            $this->messages($this->file->read()),
        ));
    }

    #[Override]
    public function append(Message $message): void
    {
        $this->file->update(function (array &$data) use ($message): void {
            $messages = $this->messages($data);
            $messages[] = $this->mapper->toArray($message);
            $data['messages'] = $messages;
        });
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<array<array-key, mixed>>
     */
    private function messages(array $data): array
    {
        $messages = $data['messages'] ?? [];
        if (!is_array($messages)) {
            return [];
        }

        /** @var list<array<array-key, mixed>> $messages */
        return $messages;
    }
}
