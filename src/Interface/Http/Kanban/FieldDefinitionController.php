<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Application\Kanban\CreateFieldDefinition;
use App\Application\Kanban\DeleteFieldDefinition;
use App\Application\Kanban\ListFieldDefinitions;
use App\Application\Kanban\ReadFieldDefinition;
use App\Application\Kanban\UpdateFieldDefinition;
use App\Application\Security\ReadCurrentUser;
use App\Domain\Kanban\FieldDefinitionId;
use App\Domain\Kanban\FieldOptions;
use App\Domain\Kanban\FieldType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Position;
use App\Domain\Kanban\Slug;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use App\Interface\Http\Tenancy\JsonBody;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class FieldDefinitionController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ListFieldDefinitions $listFields,
        private CreateFieldDefinition $createField,
        private ReadFieldDefinition $readField,
        private UpdateFieldDefinition $updateField,
        private DeleteFieldDefinition $deleteField,
        private FieldDefinitionPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueTypeId $issueTypeId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'fields' => $this->presenter->presentMany($this->listFields->execute($user, $workspaceId, $issueTypeId)),
        ]);
    }

    public function create(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        IssueTypeId $issueTypeId,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $label = JsonBody::string($body, 'label') ?? '';
        $key = JsonBody::string($body, 'key');
        $type = FieldType::fromString(JsonBody::string($body, 'type') ?? '');
        $field = $this->createField->execute(
            $user,
            $workspaceId,
            $issueTypeId,
            $key === null || $key === '' ? Slug::fromName($label) : new Slug($key),
            $label,
            $type,
            new FieldOptions(JsonBody::stringList($body, 'options') ?? []),
            JsonBody::boolean($body, 'required') ?? false,
            new Position(JsonBody::integer($body, 'position') ?? 0),
        );

        return $this->responder->respond(['field' => $this->presenter->present($field)], 201);
    }

    public function show(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        FieldDefinitionId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'field' => $this->presenter->present($this->readField->execute($user, $workspaceId, $id)),
        ]);
    }

    public function update(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        FieldDefinitionId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $body = JsonBody::from($request);
        $type = JsonBody::string($body, 'type');
        $options = JsonBody::stringList($body, 'options');
        $position = JsonBody::integer($body, 'position');

        return $this->responder->respond([
            'field' => $this->presenter->present($this->updateField->execute(
                $user,
                $workspaceId,
                $id,
                JsonBody::string($body, 'label'),
                $type === null ? null : FieldType::fromString($type),
                $options === null ? null : new FieldOptions($options),
                JsonBody::boolean($body, 'required'),
                $position === null ? null : new Position($position),
            )),
        ]);
    }

    public function delete(
        ServerRequestInterface $request,
        WorkspaceId $workspaceId,
        FieldDefinitionId $id,
    ): ResponseInterface {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $this->deleteField->execute($user, $workspaceId, $id);

        return $this->responder->respond(['deleted' => true]);
    }
}
