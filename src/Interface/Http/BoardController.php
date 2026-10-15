<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Application\AdvanceItem;
use App\Application\AddComment;
use App\Application\CloseItem;
use App\Application\CreateItem;
use App\Application\FindItem;
use App\Application\ItemChanges;
use App\Application\ListBoardItems;
use App\Application\UpdateItem;
use App\Domain\Agent\AgentId;
use App\Domain\Board\ItemId;
use App\Domain\Contract\Authenticator;
use App\Domain\Contract\ItemEventLog;
use App\Domain\Exception\ColumnNotFoundException;
use App\Domain\Exception\InvalidTransitionException;
use App\Domain\Exception\ItemNotFoundException;
use App\Domain\Workflow\ColumnId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class BoardController
{
    public function __construct(
        private Authenticator $authenticator,
        private ListBoardItems $listBoardItems,
        private FindItem $findItem,
        private CreateItem $createItem,
        private UpdateItem $updateItem,
        private AdvanceItem $advanceItem,
        private AddComment $addComment,
        private CloseItem $closeItem,
        private ItemEventLog $events,
        private ItemArrayPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->authenticator->isValid(BearerToken::fromRequest($request))) {
            return $this->responder->error('unauthorized', 401);
        }

        try {
            return $this->dispatch($request);
        } catch (ItemNotFoundException | ColumnNotFoundException $exception) {
            return $this->responder->error($exception->getMessage(), 404);
        } catch (InvalidTransitionException $exception) {
            return $this->responder->error($exception->getMessage(), 409);
        }
    }

    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $segments = $this->segments($request);
        $id = $this->idFrom($segments);
        $subresource = $segments[3] ?? '';

        return match (true) {
            $request->getMethod() === 'GET' && $id === null && $subresource === '' => $this->list(),
            $request->getMethod() === 'POST' && $id === null && $subresource === '' => $this->create($request),
            $request->getMethod() === 'GET' && $id !== null && $subresource === '' => $this->show($id),
            $request->getMethod() === 'PATCH' && $id !== null && $subresource === '' => $this->update($id, $request),
            $request->getMethod() === 'POST' && $id !== null && $subresource === 'comments' => $this->comment($id, $request),
            $request->getMethod() === 'POST' && $id !== null && $subresource === 'close' => $this->close($id, $request),
            $request->getMethod() === 'POST' && $id !== null && $subresource === 'advance' => $this->advance($id, $request),
            default => $this->responder->error('not found', 404),
        };
    }

    /** @return list<string> */
    private function segments(ServerRequestInterface $request): array
    {
        $path = (string) parse_url($request->getUri()->getPath(), PHP_URL_PATH);

        return array_values(array_filter(explode('/', $path)));
    }

    /** @param list<string> $segments */
    private function idFrom(array $segments): ?ItemId
    {
        $raw = $segments[2] ?? '';

        return ctype_digit($raw) ? new ItemId((int) $raw) : null;
    }

    private function list(): ResponseInterface
    {
        return $this->responder->respond(['items' => $this->presenter->presentMany($this->listBoardItems->execute())]);
    }

    private function show(ItemId $id): ResponseInterface
    {
        $item = $this->findItem->execute($id);

        return $this->responder->respond(['item' => $this->presenter->presentWithHistory($item, $this->events->history($id))]);
    }

    private function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $item = $this->createItem->execute(
            (string) ($body['title'] ?? ''),
            (string) ($body['body'] ?? ''),
            $this->columnFrom($body['status'] ?? null),
            $this->agentFrom($body['role'] ?? null),
            $this->actorFrom($body),
        );

        return $this->responder->respond(['item' => $this->presenter->present($item)], 201);
    }

    private function update(ItemId $id, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $item = $this->updateItem->execute($id, $this->changesFrom($body), $this->actorFrom($body));

        return $this->responder->respond(['item' => $this->presenter->present($item)]);
    }

    private function comment(ItemId $id, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $comment = $this->addComment->execute(
            $id,
            (string) ($body['author'] ?? 'user'),
            (string) ($body['body'] ?? ''),
        );

        return $this->responder->respond(['comment' => $this->presenter->presentComment($comment)], 201);
    }

    private function close(ItemId $id, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $item = $this->closeItem->execute($id, $this->stringFrom($body['comment'] ?? null), $this->actorFrom($body));

        return $this->responder->respond(['item' => $this->presenter->present($item)]);
    }

    private function advance(ItemId $id, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $item = $this->advanceItem->execute($id, $this->columnFrom($body['to'] ?? null), $this->actorFrom($body));

        return $this->responder->respond(['item' => $this->presenter->present($item)]);
    }

    /** @return array<array-key, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        /** @var mixed $data */
        $data = json_decode((string) $request->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<array-key, mixed> $body */
    private function changesFrom(array $body): ItemChanges
    {
        return new ItemChanges(
            column: $this->columnFrom($body['status'] ?? null),
            agent: $this->agentFrom($body['role'] ?? null),
            agentChanged: array_key_exists('role', $body),
            worker: $this->stringFrom($body['worker'] ?? null),
            workerChanged: array_key_exists('worker', $body),
            blocked: $this->boolFrom($body['blocked'] ?? null),
            blockedBy: $this->stringFrom($body['blocked_by'] ?? null),
            blockedByChanged: array_key_exists('blocked_by', $body),
            title: $this->stringFrom($body['title'] ?? null),
            body: $this->stringFrom($body['body'] ?? null),
        );
    }

    /** @param array<array-key, mixed> $body */
    private function actorFrom(array $body): string
    {
        return $this->stringFrom($body['actor'] ?? null) ?? 'user';
    }

    private function columnFrom(mixed $value): ?ColumnId
    {
        return is_string($value) && $value !== '' ? new ColumnId($value) : null;
    }

    private function agentFrom(mixed $value): ?AgentId
    {
        return is_string($value) && $value !== '' ? new AgentId($value) : null;
    }

    private function stringFrom(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function boolFrom(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
