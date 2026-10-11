# multi-agent

Orchestration project that consumes
[agent-watcher](https://github.com/phprise-foundation/agent-watcher) and provides
a self-hosted kanban board with authentication, built following
[The OTAKU Manifesto](docs/PHILOSOPHY.md) (Clean Architecture + DDD).

## Components

- **board** — the PHP application in this repository: a REST API plus a static,
  Jira-like board UI, protected by a single admin password.
- **docker-compose.yml** — brings up the board and, under the `agents` profile,
  the [agent-watcher](https://github.com/phprise-foundation/agent-watcher)
  containers (one per role, `--backend local`).

## Run

```bash
cp .env.example .env   # set BOARD_PASSWORD (and DEEPSEEK_API_KEY for agents)
docker compose up -d --build
```

Open <http://localhost:8080> and sign in with `BOARD_PASSWORD`.

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
| `GET` | `/api/items` | yes | list items |
| `POST` | `/api/items` | yes | create item `{ title, body, role }` |
| `GET` | `/api/items/{id}` | yes | show item |
| `PATCH` | `/api/items/{id}` | yes | update fields (status, role, worker, blocked, blocked_by, title, body) |
| `POST` | `/api/items/{id}/comments` | yes | add comment `{ author, body }` |
| `POST` | `/api/items/{id}/close` | yes | close item `{ comment? }` |

## Structure

```
src/Domain            entities, value objects, repository contract
src/Application       use cases
src/Infrastructure    JSON persistence, password auth
src/Interface         HTTP controllers
public/               front controller + static board UI
```

## Tests

```bash
composer install
vendor/bin/phpunit
```

## Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md). The `local` backend in agent-watcher is
done and the agents are wired into `docker-compose.yml`; the next milestone is
the embedded always-on chat agent.
