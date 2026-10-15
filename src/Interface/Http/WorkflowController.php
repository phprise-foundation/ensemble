<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Application\CreateColumn;
use App\Application\DeleteColumn;
use App\Application\ListColumns;
use App\Application\ReadBoard;
use App\Application\UpdateBoard;
use App\Application\UpdateColumn;
use App\Domain\Agent\AgentId;
use App\Domain\Contract\Authenticator;
use App\Domain\Exception\AgentNotFoundException;
use App\Domain\Exception\ColumnAlreadyExistsException;
use App\Domain\Exception\ColumnNotFoundException;
use App\Domain\Exception\ColumnNotRemovableException;
use App\Domain\Exception\InvalidTransitionException;
use App\Domain\Workflow\ColumnId;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class WorkflowController
{
    public function __construct(
        private Authenticator $authenticator,
        private ReadBoard $readBoard,
        private UpdateBoard $updateBoard,
        private ListColumns $listColumns,
        private CreateColumn $createColumn,
        private UpdateColumn $updateColumn,
        private DeleteColumn $deleteColumn,
        private BoardPresenter $presenter,
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
        } catch (ColumnNotFoundException $exception) {
            return $this->responder->error($exception->getMessage(), 404);
        } catch (ColumnAlreadyExistsException | ColumnNotRemovableException | AgentNotFoundException | InvalidTransitionException | InvalidArgumentException $exception) {
            return $this->responder->error($exception->getMessage(), 409);
        }
    }

    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $segments = $this->segments($request);
        $columnId = $this->columnIdFrom($segments);
        $isColumns = ($segments[2] ?? '') === 'columns';

        return match (true) {
            $request->getMethod() === 'GET' && !$isColumns => $this->show(),
            $request->getMethod() === 'PATCH' && !$isColumns => $this->update($request),
            $request->getMethod() === 'POST' && $isColumns && $columnId === null => $this->createColumn($request),
            $request->getMethod() === 'PATCH' && $isColumns && $columnId !== null => $this->updateColumn($columnId, $request),
            $request->getMethod() === 'DELETE' && $isColumns && $columnId !== null => $this->deleteColumn($columnId),
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
    private function columnIdFrom(array $segments): ?ColumnId
    {
        $raw = $segments[3] ?? '';

        return $raw === '' ? null : new ColumnId($raw);
    }

    private function show(): ResponseInterface
    {
        return $this->responder->respond(['board' => $this->presenter->present($this->readBoard->execute(), $this->listColumns->execute())]);
    }

    private function update(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $board = $this->updateBoard->execute($this->stringFrom($body['name'] ?? null), $this->intFrom($body['aging_hours'] ?? null));

        return $this->responder->respond(['board' => $this->presenter->present($board, $this->listColumns->execute())]);
    }

    private function createColumn(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $column = $this->createColumn->execute(
            (string) ($body['name'] ?? ''),
            (bool) ($body['final'] ?? false),
            $this->roleFrom($body),
            $this->transitionsFrom($body),
        );

        return $this->responder->respond(['column' => $this->presenter->presentColumn($column)], 201);
    }

    private function updateColumn(ColumnId $id, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $column = $this->updateColumn->execute(
            $id,
            $this->stringFrom($body['name'] ?? null),
            $this->intFrom($body['position'] ?? null),
            $this->boolFrom($body['final'] ?? null),
            $this->roleFrom($body),
            array_key_exists('role', $body),
            $this->transitionsFrom($body),
            array_key_exists('transitions', $body),
        );

        return $this->responder->respond(['column' => $this->presenter->presentColumn($column)]);
    }

    private function deleteColumn(ColumnId $id): ResponseInterface
    {
        $this->deleteColumn->execute($id);

        return $this->responder->respond(['deleted' => $id->value()]);
    }

    /** @return array<array-key, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        /** @var mixed $data */
        $data = json_decode((string) $request->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<array-key, mixed> $body */
    private function roleFrom(array $body): ?AgentId
    {
        return $this->agentIdFrom($body['role'] ?? null);
    }

    /**
     * @param array<array-key, mixed> $body
     * @return list<ColumnId>
     */
    private function transitionsFrom(array $body): array
    {
        $raw = $body['transitions'] ?? null;
        if (!is_array($raw)) {
            return [];
        }
        $targets = [];
        /** @var mixed $value */
        foreach ($raw as $value) {
            if (is_string($value) && $value !== '') {
                $targets[] = new ColumnId($value);
            }
        }

        return $targets;
    }

    private function agentIdFrom(mixed $value): ?AgentId
    {
        return is_string($value) && $value !== '' ? new AgentId($value) : null;
    }

    private function stringFrom(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function intFrom(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function boolFrom(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
