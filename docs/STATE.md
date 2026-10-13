# STATE

## Done

- OTAKU Application skeleton (Domain / Application / Infrastructure / Interface).
- Single-password authentication (Bearer).
- REST API + static Jira-like board UI.
- Docker image + docker-compose.
- Unit and integration tests (PHPUnit), Psalm at errorLevel 1 (0 errors).
- Docs consolidated under `docs/` (`PHILOSOPHY.md`, `GUIDE.md`, `GITFLOW.md`,
  `ARCHITECTURE.md`, `STATE.md`, `ROADMAP.md`, `CHAT.md`); `AGENTS.md` created at
  the root.
- Gitflow convention aligned with `phprise/koenma-id`: `main` branch, `feat/*`/`fix/*`,
  Conventional Commits, semver tags; `bin/publish.sh` added.
- Published to GitHub.
- `agent-watcher` `local` backend: board access abstracted under
  `src/backends/` (`github` + `local`); agents consume this board via REST
  (`--backend local --kanban-url --kanban-token`) using the board password as
  bearer; unit tests + end-to-end smoke test.
- Agents wired into `docker-compose.yml` under the `agents` profile (one service
  per role, `--backend local`, board at `http://board`).
- Chat copilot (phase 1): conversation domain (`Message`, `Conversation`),
  `GET`/`POST /api/chat`, board UI drawer, `EchoChatAgent` stub; unit + HTTP tests.
- Chat copilot (phase 2): `HttpChatAgent` + `CurlHttpTransport` (board) and the
  `chat` service (`bin/chat-server`, opencode) in agent-watcher; verified
  end-to-end (board -> chat service -> opencode -> answer). Selected by `CHAT_URL`;
  the offline stub remains the default. Protocol/decisions in `docs/CHAT.md`.
- **Configurable workflow (MVP):** `Board` / `Column` / `Agent` are data, not
  enums. `Domain/Workflow` and `Domain/Agent` own the entities and contracts;
  SQLite (`Infrastructure/Persistence/Sqlite`) persists board settings, ordered
  columns, the agent registry, items, comments and the stage history. The
  defaults (9 columns, 7 agents) are seeded and keep the `status`/`role` REST
  contract the agents rely on.
- **Stage history and aging:** every column change is recorded as an `ItemEvent`
  (`from`, `to`, `actor`, `at`); items expose `entered_at`, and the UI flags
  cards whose time in stage exceeds the board's `aging_hours` (bottleneck signal).
- **Config UI:** sidebar with `Boards` (board settings + column CRUD) and
  `Agents` (registry CRUD); the kanban renders the configured columns and agent
  names. Covered by integration tests through the HTTP controllers.
- **Board-driven workflow (Phase A):** the workflow now carries routing and
  transitions, and agents carry their runtime spec.
  - `Domain/Workflow/Transitions` — first-class collection of allowed target
    columns; `Column` owns a `role` (the `AgentId` responsible for the stage) and
    its `transitions`. `item.role` stays as a manual override.
  - `Domain/Agent/Agent` — runtime spec (`rule`, `profile`, `model`,
    `fallback_model`, `interval`, `workspace`) plus `active`.
  - SQLite persists both (`columns.role_id`, `column_transitions`, and the new
    agent columns); an in-place migration adds `role_id` to existing databases.
    The seed binds each stage to its role and the current linear flow.
  - `POST /api/items/{id}/advance` moves an item along a configured transition
    (or the sole target) and assigns the destination column's role; disallowed
    moves return `409`.
  - Board/Agents UI edits the role and transitions per column and the full agent
    spec.
  - `vendor/bin/phpunit` (29 tests) and `vendor/bin/psalm` are green; verified
    end-to-end over HTTP (login -> create -> advance -> board payload).
- **Roadmap documented** in `docs/ROADMAP.md` (active plan: Phases A/B/C).
- **Board-driven runtime (Phase B, agent-watcher):** the `local` backend now
  exposes `workflow()`, `advance()` and `agents()`; `kanban workflow`/`advance`
  and the `--role` flag were added. The loop derives the columns it polls from
  the board (`column.role == my role`) and the prompt injects the live workflow;
  rules advance with `kanban advance` instead of hardcoding targets.
  `agent-runner` reads `GET /api/agents` and keeps exactly one `agent-watcher`
  subprocess per active agent (restart on spec change, stop on removal). The
  `docker-compose.yml` `agents` profile now runs this single runner. Verified
  end-to-end against the real board (workflow, advance, spawn) and unit-tested.

## Next

- **Phase C — standalone single-container image:** Alpine image
  (`agent-watcher` base + PHP 8.4) with `bin/entrypoint` starting the board and
  the runner, so the whole system runs with a single `docker run`.
- **Drag & drop / reordering polish** — move cards between configured columns in
  the UI and reorder columns by dragging.
- **Workflow transitions polish** — WIP limits and a transition editor beyond the
  multi-select (e.g. drag between columns).
- **Process visibility** — cycle time per column, rework (backward moves) and
  failure read models built on `item_events`.
- Chat copilot (phase 3): write actions (create/move/comment) with a confirmation
  flow. See `docs/CHAT.md` ("Pending") — needs a product decision.
- Hardening: SSE/streaming for the copilot, dedicated token instead of the raw
  board password.
