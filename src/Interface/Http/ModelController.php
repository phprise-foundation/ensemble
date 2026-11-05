<?php

declare(strict_types=1);

namespace App\Interface\Http;

use App\Application\Security\ReadCurrentUser;
use App\Domain\Model\ModelCatalog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ModelController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private ModelCatalog $catalog,
        private ModelPresenter $presenter,
        private JsonResponder $responder,
    ) {
    }

    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $this->currentUser->execute(BearerToken::fromRequest($request));

        return $this->responder->respond([
            'default' => $this->catalog->defaultId()->value(),
            'models' => $this->presenter->presentMany($this->catalog->all()),
        ]);
    }
}
