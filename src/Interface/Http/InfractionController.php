<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Application\AdjudicateInfraction;
use App\Application\DeclareInfraction;
use App\Application\ListItemInfractions;
use App\Domain\Board\ItemId;
use App\Domain\Contract\Authenticator;
use App\Domain\Exception\InfractionNotFoundException;
use App\Domain\Exception\InvalidInfractionException;
use App\Domain\Exception\ItemNotFoundException;
use App\Domain\Infraction\Cause;
use App\Domain\Infraction\InfractionId;
use App\Domain\Infraction\InfractionStatus;
use App\Domain\Infraction\Severity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class InfractionController
{
    public function __construct(
        private Authenticator $authenticator,
        private DeclareInfraction $declareInfraction,
        private AdjudicateInfraction $adjudicateInfraction,
        private ListItemInfractions $listItemInfractions,
        private InfractionPresenter $presenter,
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
        } catch (ItemNotFoundException | InfractionNotFoundException $exception) {
            return $this->responder->error($exception->getMessage(), 404);
        } catch (InvalidInfractionException $exception) {
            return $this->responder->error($exception->getMessage(), 422);
        }
    }

    private function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $segments = $this->segments($request);
        $resource = $segments[1] ?? '';
        $id = (int) ($segments[2] ?? '0');
        $action = $segments[3] ?? '';

        if ($resource === 'items' && $action === 'infractions') {
            return $this->itemAction($id, $request);
        }
        if ($resource === 'infractions' && $action === 'adjudicate' && $request->getMethod() === 'POST') {
            return $this->adjudicate($id, $request);
        }

        return $this->responder->error('not found', 404);
    }

    private function itemAction(int $id, ServerRequestInterface $request): ResponseInterface
    {
        return match ($request->getMethod()) {
            'POST' => $this->declare($id, $request),
            'GET' => $this->list($id),
            default => $this->responder->error('not found', 404),
        };
    }

    private function declare(int $itemId, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $cause = Cause::tryFrom((string) ($body['cause'] ?? ''));
        $severity = Severity::tryFrom((string) ($body['severity'] ?? ''));
        if ($cause === null || $severity === null) {
            return $this->responder->error('invalid cause or severity', 422);
        }
        $infractor = $this->arrayFrom($body['infractor'] ?? null);
        $infraction = $this->declareInfraction->execute(
            new ItemId($itemId),
            (string) ($infractor['agent'] ?? ''),
            (string) ($infractor['role'] ?? ''),
            (string) ($infractor['model'] ?? ''),
            (string) ($body['detected_by'] ?? ''),
            $cause,
            $severity,
            (string) ($body['evidence'] ?? ''),
            (bool) ($body['blocked'] ?? false),
        );

        return $this->responder->respond(['infraction' => $this->presenter->present($infraction)], 201);
    }

    private function list(int $itemId): ResponseInterface
    {
        $infractions = $this->listItemInfractions->execute(new ItemId($itemId));

        return $this->responder->respond(['infractions' => $this->presenter->presentMany($infractions)]);
    }

    private function adjudicate(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->body($request);
        $status = InfractionStatus::tryFrom((string) ($body['status'] ?? ''));
        if ($status === null) {
            return $this->responder->error('invalid status', 422);
        }
        $infraction = $this->adjudicateInfraction->execute(
            new InfractionId($id),
            $status,
            (string) ($body['adjudicated_by'] ?? ''),
        );

        return $this->responder->respond(['infraction' => $this->presenter->present($infraction)]);
    }

    /** @return list<string> */
    private function segments(ServerRequestInterface $request): array
    {
        $path = (string) parse_url($request->getUri()->getPath(), PHP_URL_PATH);

        return array_values(array_filter(explode('/', $path)));
    }

    /** @return array<array-key, mixed> */
    private function body(ServerRequestInterface $request): array
    {
        /** @var mixed $data */
        $data = json_decode((string) $request->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /** @return array<array-key, mixed> */
    private function arrayFrom(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
