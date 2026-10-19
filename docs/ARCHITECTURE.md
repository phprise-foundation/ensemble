# ARCHITECTURE

## Overview

`multi-agent` is an OTAKU Application repository. Its heart is a configurable
kanban board that mirrors the agent-watcher data model (`column`, `agent`,
`worker`, `blocked`, `blocked_by`, comments, stage history) so that agents can be
pointed at it.

## Frontend (Vue SPA)

`frontend/` is a Vue 3 + Vite SPA, independent from the PHP backend and backed by
a fake JSON API (`plugin/fake-backend.js`) until each screen's real endpoints
exist. `src/App.vue` is the shell: the left `.sidebar`, the central `.content`
(topbar + `.main`), and the independent panels. The right panel is
`AppCopilot.vue` (`.copilot-panel`), a desktop-only skeleton mounted as a
sibling of `.content` and always expanded on load; `.copilot-toggle` in the top
bar and `Ctrl+Alt+B` hide or restore it without touching the other panels. It
performs no network call and keeps no conversation state.

## Layers

```
public/index.php          composition root + top-level routing
        │
        ▼
Interface/Http            BoardController, WorkflowController, AgentController,
                          AuthController, ChatController, *Presenter
        │
        ▼
Application               use cases (items, workflow config, agents, chat)
        │
        ▼
Domain                    Workflow (Board, Column), Agent, Board (Item, ItemEvent),
                          Chat; contracts
        ▲
        │
Infrastructure            Sqlite/* (persistence), JsonChatRepository,
                          PasswordAuthenticator, HttpChatAgent
```

Dependencies point inward: `Interface → Application → Domain`, and
`Infrastructure → Domain` (implementing the contracts).

## Domain packages

- `Domain/Workflow` — `Board` (settings), `Column` (ordered stage with a `role`
  and its allowed `Transitions`), `ColumnId`, `Position`, `ColumnCollection` and
  `Transitions`. The workflow is data, not an enum. The `role` binds a stage to
  the agent responsible for it; `Transitions` is a first-class collection of the
  allowed target columns.
- `Domain/Agent` — `Agent` (registry entry: identity + runtime spec `profile`,
  `model`, `fallback_model`, `interval`, `workspace`) and `AgentId`.
- `Domain/Board` — `Item` (aggregate: column, agent, worker, blocked, comments,
  timestamps), `ItemEvent` (a stage transition) and `ItemId`. `item.role` is a
  manual override; the stage's role lives on the column.
- `Domain/Chat` — `Conversation`, `Message`, `MessageRole`.
- `Domain/Contract` — repository and gateway interfaces.

## Persistence (SQLite)

`Infrastructure/Persistence/Sqlite/SqliteDatabase` opens the database, applies
`Schema` (DDL) and `Seed` (default board, 9 columns and 7 agents). Repositories
(`SqliteItemRepository`, `SqliteColumnRepository`, `SqliteAgentRegistry`,
`SqliteBoardSettings`, `SqliteItemEventLog`) implement the domain contracts;
mappers translate rows to entities. Columns and agents are referenced by slug,
which keeps the REST contract (`status` = column, `role` = agent) stable for the
agents. Columns also carry a nullable `role_id` (the agent that owns the stage)
and their allowed `column_transitions`; `Schema` migrates existing databases by
adding `role_id` when missing. `Seed` runs agents before columns so the seeded
roles resolve, and wires the default linear transitions.

## Data flow (move an item)

1. `BoardController::update` parses the PSR-7 request body.
2. `UpdateItem` loads the `Item`, applies `ItemChanges`, saves it and, when the
   column changed, appends an `ItemEvent` (from → to, actor, timestamp).
3. `SqliteItemRepository::save` and `SqliteItemEventLog::record` persist both.

## Advancing an item

`POST /api/items/{id}/advance` goes through `AdvanceItem`: it resolves the target
from the current column's `Transitions` (the requested `to`, or the sole target),
rejects moves the workflow does not allow (`InvalidTransitionException` → `409`),
moves the item, assigns the destination column's `role` and records the
`ItemEvent`.

## Visibility

Each `Item` exposes `entered_at` (the last stage transition). The UI compares it
to the board's `aging_hours` to flag aged cards (bottleneck signal); the full
`ItemEvent` history is returned on the item detail endpoint for per-stage
analysis.

## Authentication

A single shared secret (`BOARD_PASSWORD`). The board accepts it as a Bearer
token. `PasswordAuthenticator` implements the `Authenticator` contract.
