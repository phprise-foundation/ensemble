# ARCHITECTURE

## Overview

`multi-agent` is an OTAKU Application repository. Its heart is a small kanban
board that mirrors the agent-watcher data model (`Status`, `Role`, `Worker`,
`Blocked`, `BlockedBy`, comments) so that agents can be pointed at it later.

## Layers

```
public/index.php          composition root + top-level routing
        │
        ▼
Interface/Http            BoardController, AuthController, ItemArrayPresenter
        │
        ▼
Application               use cases (ListBoardItems, CreateItem, UpdateItem, ...)
        │
        ▼
Domain                    Item, Comment, Status, Role, ItemId, BoardRepository contract
        ▲
        │
Infrastructure            JsonBoardRepository, JsonFile, ItemMapper, PasswordAuthenticator
```

Dependencies point inward: `Interface → Application → Domain`, and
`Infrastructure → Domain` (implementing the contracts).

## Data flow (create an item)

1. `BoardController::create` parses the PSR-7 request body.
2. `CreateItem` builds an `Item` (immutable identity + mutable pipeline state).
3. `JsonBoardRepository::add` serializes it and persists the JSON collection.

## Authentication

A single shared secret (`BOARD_PASSWORD`). The board accepts it as a Bearer
token. `PasswordAuthenticator` implements the `Authenticator` contract.
