# multi-agent

Orchestration project that consumes
[agent-watcher](https://github.com/phprise-foundation/agent-watcher) and provides
a self-hosted kanban board with authentication, built following
[The OTAKU Manifesto](docs/PHILOSOPHY.md) (Clean Architecture + DDD).

The board is configurable: you define the **columns** (stages) and the **agents**
in the UI, so each step of the process can be observed and improved on its own.
Every stage change is recorded, and the UI flags items that age beyond the
board's threshold — the first step towards bottleneck and failure detection.

## Components

- **board** — the PHP application in this repository: a REST API plus a static,
  Jira-like board UI, protected by a single admin password. Data lives in SQLite.
- **docker-compose.yml** — brings up the board and, under the `agents` profile,
  the [agent-watcher](https://github.com/phprise-foundation/agent-watcher)
  containers (one per role, `--backend local`).

## Run

```bash
cp .env.example .env   # set BOARD_PASSWORD (and DEEPSEEK_API_KEY for agents)
docker compose up -d --build
```

Open <http://localhost:8080> and sign in with `BOARD_PASSWORD`.

The SQLite database is created on first boot at `BOARD_DB_FILE`
(default `var/board.sqlite`) and seeded with a default board, its columns and the
agent registry.

### Agents

Agents are opt-in via the `agents` profile (the default `up` only starts the
board):

```bash
docker compose --profile agents up -d --build
```

Each service maps to a role (`product-owner`, the architects, `tech-lead`,
`quality-assurance`, `coder`) and points at the board through
`AW_KANBAN_URL=http://board` with `AW_KANBAN_TOKEN=$BOARD_PASSWORD`. The agents
edit the project mounted at `AW_WORKSPACE` (default `./workspace`). Configure
`DEEPSEEK_API_KEY`, `AW_MODEL` and `AW_FALLBACK_MODEL` in `.env`.

## API

Auth is a single shared secret: send `Authorization: Bearer <BOARD_PASSWORD>`.

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/api/login` | no | `{ "password": "..." }` → `{ "token": "..." }` |
| `GET` | `/api/health` | no | liveness |
| `GET` | `/api/board` | yes | board settings + columns |
| `PATCH` | `/api/board` | yes | update `{ name?, aging_hours? }` |
| `POST` | `/api/board/columns` | yes | create column `{ name, final?, role?, transitions? }` |
| `PATCH` | `/api/board/columns/{id}` | yes | update `{ name?, position?, final?, role?, transitions? }` |
| `DELETE` | `/api/board/columns/{id}` | yes | delete an empty, non-final column |
| `GET` | `/api/agents` | yes | list agents |
| `POST` | `/api/agents` | yes | create agent `{ name, slug?, description?, rule?, profile?, model?, fallback_model?, interval?, workspace? }` |
| `PATCH` | `/api/agents/{id}` | yes | update `{ name?, description?, active?, rule?, profile?, model?, fallback_model?, interval?, workspace? }` |
| `DELETE` | `/api/agents/{id}` | yes | delete agent (unassigns its items) |
| `GET` | `/api/chat` | yes | copilot conversation |
| `POST` | `/api/chat` | yes | send `{ message }` → updated conversation |
| `GET` | `/api/items` | yes | list items |
| `POST` | `/api/items` | yes | create item `{ title, body, status?, role? }` |
| `GET` | `/api/items/{id}` | yes | show item (with stage history) |
| `PATCH` | `/api/items/{id}` | yes | update (status, role, worker, blocked, blocked_by, title, body, actor) |
| `POST` | `/api/items/{id}/comments` | yes | add comment `{ author, body }` |
| `POST` | `/api/items/{id}/close` | yes | close item `{ comment? }` |
| `POST` | `/api/items/{id}/advance` | yes | move along a configured transition `{ to? }` |

`status` is a column slug and `role` is an agent slug, so the agent-watcher
`local` backend keeps working unchanged. Each column owns the `role` responsible
for its stage and its allowed `transitions`, so an item can be advanced along the
configured workflow with `POST /api/items/{id}/advance`.

## UI

- **Board** — the kanban; columns and cards come from the configured workflow.
- **Boards** — board settings (name, aging threshold), column CRUD, and each
  column's role and allowed transitions.
- **Agents** — the agent registry, including each agent's runtime spec (rule,
  profile, model, fallback model, interval, workspace).
- **Copilot** — a right-hand drawer backed by `GET`/`POST /api/chat`,
  read-only. By default the agent is an offline stub (`EchoChatAgent`). To use the
  real always-on chat service (opencode), set `CHAT_URL`/`CHAT_TOKEN` and start
  the `chat` profile:

  ```bash
  # .env
  CHAT_URL=http://chat:8080
  CHAT_TOKEN=change-me

  docker compose --profile chat up -d --build
  ```

  See [docs/CHAT.md](docs/CHAT.md) for the protocol.

## Structure

```
src/Domain            workflow, agents, items, chat: entities and contracts
src/Application       use cases
src/Infrastructure    SQLite persistence, JSON chat, auth, chat client
src/Interface         HTTP controllers and presenters
public/               front controller + static board UI
```

## Tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/psalm
```

## Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md). The configurable workflow is done; the
next milestones are process visibility (cycle time, rework, failures) and
workflow rules (allowed transitions, WIP limits).
