<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Application\CreateAgent;
use App\Application\DeleteAgent;
use App\Application\ListAgents;
use App\Application\UpdateAgent;
use App\Domain\Agent\AgentId;
use App\Domain\Contract\Authenticator;
use App\Domain\Exception\AgentAlreadyExistsException;
use App\Domain\Exception\AgentNotFoundException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AgentController
{
    public function __construct(
        private Authenticator $authenticator,
        private ListAgents $listAgents,
        private CreateAgent $createAgent,
        private UpdateAgent $updateAgent,
        private DeleteAgent $deleteAgent,
        private AgentArrayPresenter $presenter,
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
        } catch (AgentNotFoundException $exception) {
            return $this->responder->error($exception->getMessage(), 404);
        } catch (AgentAlreadyExistsException | InvalidArgumentException $exception) {
            return $this->responder->error($exception->getMessage(), 409);
        }
    }

    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $id = $this->idFrom($request);

        return match (true) {
            $request->getMethod() === 'GET' && $id === null => $this->list(),
            $request->getMethod() === 'POST' && $id === null => $this->create($request),
            $request->getMethod() === 'PATCH' && $id !== null => $this->update($id, $request),
            $request->getMethod() === 'DELETE' && $id !== null => $this->delete($id),
            default => $this->responder->error('not found', 404),
        };
    }

    private function idFrom(ServerRequestInterface $request): ?AgentId
    {
        $path = (string) parse_url($request->getUri()->getPath(), PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', $path)));
        $raw = $segments[2] ?? '';

        return $raw === '' ? null : new AgentId($raw);
    }

    private function list(): ResponseInterface
    {
        return $this->responder->respond(['agents' => $this->presenter->presentMany($this->listAgents->execute())]);
    }

    private function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $agent = $this->createAgent->execute(
            (string) ($body['name'] ?? ''),
            (string) ($body['slug'] ?? ''),
            (string) ($body['description'] ?? ''),
            (string) ($body['profile'] ?? ''),
            (string) ($body['model'] ?? ''),
            (string) ($body['fallback_model'] ?? ''),
            $this->intFrom($body['interval'] ?? null) ?? 300,
            (string) ($body['workspace'] ?? '/workspace'),
        );

        return $this->responder->respond(['agent' => $this->presenter->present($agent)], 201);
    }

    private function update(AgentId $id, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $agent = $this->updateAgent->execute(
            $id,
            $this->stringFrom($body['name'] ?? null),
            $this->stringFrom($body['description'] ?? null),
            $this->boolFrom($body['active'] ?? null),
            $this->stringFrom($body['profile'] ?? null),
            $this->stringFrom($body['model'] ?? null),
            $this->stringFrom($body['fallback_model'] ?? null),
            $this->intFrom($body['interval'] ?? null),
            $this->stringFrom($body['workspace'] ?? null),
        );

        return $this->responder->respond(['agent' => $this->presenter->present($agent)]);
    }

    private function delete(AgentId $id): ResponseInterface
    {
        $this->deleteAgent->execute($id);

        return $this->responder->respond(['deleted' => $id->value()]);
    }

    /** @return array<array-key, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        /** @var mixed $data */
        $data = json_decode((string) $request->getBody(), true);

        return is_array($data) ? $data : [];
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
