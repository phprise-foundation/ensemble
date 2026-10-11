# multi-agent

Orchestration project that consumes
[agent-watcher](https://github.com/phprise-foundation/agent-watcher) and provides
a self-hosted kanban board with authentication, built following
[The OTAKU Manifesto](docs/PHILOSOPHY.md) (Clean Architecture + DDD).

## Components

- **board** — the PHP application in this repository: a REST API plus a static,
  Jira-like board UI, protected by a single admin password.
- **docker-compose.yml** — brings up the board (and, later, the agent containers).

## Run

```bash
cp .env.example .env   # set BOARD_PASSWORD
docker compose up -d --build
```

Open <http://localhost:8080> and sign in with `BOARD_PASSWORD`.

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

See [docs/ROADMAP.md](docs/ROADMAP.md). The next milestone is the `local` backend
in agent-watcher so the agents read/write this board instead of GitHub Projects.
