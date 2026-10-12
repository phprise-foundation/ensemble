<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Application\ReadConversation;
use App\Application\SendChatMessage;
use App\Domain\Contract\Authenticator;
use App\Domain\Exception\ChatUnavailableException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ChatController
{
    public function __construct(
        private Authenticator $authenticator,
        private ReadConversation $readConversation,
        private SendChatMessage $sendChatMessage,
        private ChatMessagePresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->authorized($request)) {
            return $this->responder->error('unauthorized', 401);
        }

        return $this->guard($request);
    }

    private function guard(ServerRequestInterface $request): ResponseInterface
    {
        try {
            return $request->getMethod() === 'POST' ? $this->send($request) : $this->history();
        } catch (ChatUnavailableException $exception) {
            return $this->responder->error($exception->getMessage(), 502);
        }
    }

    private function authorized(ServerRequestInterface $request): bool
    {
        return $this->authenticator->isValid(BearerToken::fromRequest($request));
    }

    private function history(): ResponseInterface
    {
        return $this->responder->respond(['messages' => $this->presenter->presentMany($this->readConversation->execute())]);
    }

    private function send(ServerRequestInterface $request): ResponseInterface
    {
        $body = (string) ($this->body($request)['message'] ?? '');
        if ($body === '') {
            return $this->responder->error('message is required', 422);
        }
        $this->sendChatMessage->execute($body);

        return $this->responder->respond(['messages' => $this->presenter->presentMany($this->readConversation->execute())], 201);
    }

    private function body(ServerRequestInterface $request): array
    {
        /** @var mixed $data */
        $data = json_decode((string) $request->getBody(), true);

        return is_array($data) ? $data : [];
    }
}
