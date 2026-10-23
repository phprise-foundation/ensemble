# Ensemble

Agents as a real development team. Ensemble orchestrates
[agent-watcher](https://github.com/phprise-foundation/agent-watcher) on a
self-hosted kanban board with authentication, built following
[The OTAKU Manifesto](docs/PHILOSOPHY.md) (Clean Architecture + DDD). Every
agent has a role, a profile and a stage to own; the board takes the work from
the business problem to the outcome.

Formerly published as `multi-agent`; the old GitHub URLs redirect.

The board is configurable: you define the **columns** (stages) and the **agents**
in the UI, so each step of the process can be observed and improved on its own.
Every stage change is recorded, and the UI flags items that age beyond the
board's threshold — the first step towards bottleneck and failure detection.

## Development backend (P0)

The new product API uses PostgreSQL and Doctrine. Docker Compose is used
**only for the local database**; it is never a production dependency.

```bash
cp .env.example .env
bin/dev-up          # starts PostgreSQL, creates the test DB, runs bin/install
```

`bin/install` applies the schema with Doctrine and bootstraps the master admin
from `BOOTSTRAP_ADMIN_LOGIN` / `BOOTSTRAP_ADMIN_PASSWORD` (only when the
database has no users) and the first workspace from
`BOOTSTRAP_WORKSPACE_NAME` / `BOOTSTRAP_WORKSPACE_PREFIX`.

```bash
php -S 127.0.0.1:8081 -t public public/router.php
curl -X POST localhost:8081/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"login":"admin","password":"admin1234"}'
```

The first slice exposes `POST /api/auth/login`, `POST /api/auth/logout`,
`GET /api/me`, admin-only `GET/POST /api/users`, and
`GET/POST/PATCH/DELETE /api/workspaces` with members. Non-members receive
`404`; workspaces are fully isolated.

P1a adds the workflow chain under `/api/workspaces/{workspace}`:

```
GET/POST        /workflows
GET/PATCH/DELETE /workflows/{id}
GET/POST        /workflows/{id}/issue-types
GET/PATCH/DELETE /issue-types/{id}
GET/POST        /issue-types/{id}/fields
GET/PATCH/DELETE /fields/{id}
```

Issue types only change through their own workflow; transitions never change
the type (an RFC stays an RFC). Boards reference one workflow and filter issue
types/statuses; an issue belongs to the workspace, not to a board.

The issue slice adds:

```
GET/POST  /issues
GET/PATCH /issues/{key}
POST      /issues/{key}/move
POST      /issues/{key}/comments
GET       /issues?boardId={board}
```

P2a adds the team layer:

```
GET/POST         /teams
GET/PATCH/DELETE /teams/{id}
GET/POST         /teams/{id}/roles
GET/PATCH/DELETE /roles/{id}
GET/POST         /teams/{id}/profiles
GET/PATCH/DELETE /profiles/{id}
```

Statuses accept and return `roleIds`; a role from another workspace is `422`.
A repository is the user-facing execution target; the workdir stays internal
(ADR-003).

P2b-1 adds per-workspace secrets and repositories:

```
GET/POST         /secrets
GET/PATCH/DELETE /secrets/{id}
GET/POST         /repositories
GET/PATCH/DELETE /repositories/{id}
```

Secrets are encrypted with `APP_ENCRYPTION_KEY` (base64 of 32 bytes, e.g.
`php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`); the API never returns
the value. A secret referenced by a repository cannot be deleted (`409`).

Agents are team members with an identity ("who am I", age, marital status,
photo) and a runtime (model, fallback model, loop interval, active flag). Send
`POST /teams/{teamId}/agents` with `name` plus any of `whoAmI`, `age`,
`maritalStatus`, `roleIds`, `profileId`, `avatarColor`, `portrait` (a base64
data URL, png/jpeg/webp up to 2 MiB), `model`, `fallbackModel`, `interval`
(seconds) and `active`; `GET` lists agents of the team and
`GET/PATCH/DELETE /agents/{id}` reads, edits and removes one.

Issues now carry an internal execution target. On creation, send `parentKey`
(the issue inherits the parent's workdir), `repositoryId` (bound on branch
`agent/<key>`) or neither (an issue-owned scratch workdir), and the payload
returns the derived `repositoryId`:

```
POST /issues/{key}/repository
```

attaches a repository to the issue's workdir — the whole family (parent,
children and siblings) shares it. Re-attaching the same repository is
idempotent; a different one is `409` until the runner lands (P3).

Issues use the workspace prefix (`ALP-1`, `ALP-2`), start in the issue type's
initial status and carry custom field values, comments and status history.

## The runner (P3a)

`bin/runner` (after `bin/install` applied the schema) performs one deterministic
sweep: it reclaims expired run leases, then every **active** agent looks at the
statuses owned by its roles, takes the issues sitting in them and runs those
whose workdir is `bound` — executing `opencode run --model <model>` (fallback
model as second attempt) in the workdir path and recording the outcome. Each
attempt is a `Run` with claim/lease (30 minutes) and reclaim ceiling (3
attempts) in the `runs` table. The executable-stage rule (ADR-003) is enforced
with visible reasons: an issue whose workdir is not `bound` gets a `skipped`
run (`GET /issues/{key}/runs` shows the history). Repeat sweeps redrive a
skipped issue only when the reason changes, and never re-run a succeeded or
failed one. `ENSEMBLE_WORKDIR_ROOT` (default `var/workdirs`) is where the
runner materializes workdirs.

The legacy SQLite board and agent-watcher
surfaces still exist in this branch but are not used by the new routes; they are
removed in the productization phases described in `docs/ROADMAP.md` and
`docs/ADR-001-tenancy-postgres-doctrine.md`.

## Components

## Components

- **board** — the PHP application in this repository: a REST API plus a static,
  Jira-like board UI, protected by a single admin password. Data lives in SQLite.
- **docker-compose.yml** — brings up the board and, under the `agents` profile,
  the [agent-watcher](https://github.com/phprise-foundation/agent-watcher)
  `agent-runner`, which materializes the agents defined on the board.

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

Agents are defined on the board (the `Agents` view: profile, model,
fallback model, interval, workspace). They are opt-in via the `agents` profile
(the default `up` only starts the board):

```bash
docker compose --profile agents up -d --build
```

The `runner` service reads the registry from `http://board`
(`AW_KANBAN_URL`, `AW_KANBAN_TOKEN=$BOARD_PASSWORD`) and keeps one
`agent-watcher` subprocess per active agent, in parallel. Each agent polls the
columns whose role it owns and advances items along the configured transitions.
The agents edit the project mounted at `AW_WORKSPACE` (default `./workspace`).
Provide the provider keys (e.g. `DEEPSEEK_API_KEY`, `SILICONFLOW_API_KEY`,
`GOOGLE_API_KEY`) in `.env`.

## Single container (standalone image)

The board **and** the agent runner can run in one container, so you only need a
single `docker run`. The image is built on the Alpine `agent-watcher` image (the
bundled opencode binary is musl-linked, so a glibc base cannot run it) and serves
the board with PHP's built-in server. See `Dockerfile.standalone` and
`bin/entrypoint`.

Build the agent-watcher image first (it carries `agent-runner`), then the
standalone image:

```bash
docker build -t esdrasschonevald/agent-watcher:latest ../agent-watcher
docker build -f Dockerfile.standalone -t esdrasschonevald/ensemble:standalone .
```

Run it:

```bash
docker run -d --name ensemble \
  -p 8080:80 \
  -v "$PWD/workspace:/workspace" \
  -v ensemble-var:/var/www/html/var \
  -e BOARD_PASSWORD=change-me \
  -e DEEPSEEK_API_KEY=... \
  -e SILICONFLOW_API_KEY=... \
  -e GOOGLE_API_KEY=... \
  esdrasschonevald/ensemble:standalone
```

Open <http://localhost:8080>. Agents, roles and transitions are configured in the
UI; the embedded runner starts one `agent-watcher` process per **active** agent
that has a `model` set (the seeded agents ship with an empty model, so nothing
runs until you fill it in on the `Agents` screen).

Environment: `BOARD_PASSWORD` (board login and runner token), `BOARD_DB_FILE`
(default `/var/www/html/var/board.sqlite`), `BOARD_PORT` (default `80`),
`AW_RUNNER_POLL` (default `30`), `DATABASE_URL`/`APP_ENCRYPTION_KEY` for the new
API and the provider keys the agents need.

### Local test checklist (before pushing to Docker Hub)

```bash
docker build -t esdrasschonevald/agent-watcher:latest ../agent-watcher
docker build -f Dockerfile.standalone -t esdrasschonevald/ensemble:standalone .

docker run -d --name ensemble -p 8080:80 \
  -v "$PWD/workspace:/workspace" -v ensemble-var:/var/www/html/var \
  -e BOARD_PASSWORD=change-me esdrasschonevald/ensemble:standalone

curl -s localhost:8080/api/health            # {"ok":true}
curl -s -o /dev/null -w '%{http_code}\n' localhost:8080/   # 200 (board UI)
docker logs -f ensemble                   # board + runner logs
```

Then, in the UI: set a `model` on an agent (`Agents`), pick its role on a column
(`Boards`), create a task in that column and watch the runner pick it up
(`docker logs -f ensemble`).

Publish to Docker Hub once it looks good:

```bash
docker tag esdrasschonevald/agent-watcher:latest esdrasschonevald/agent-watcher:<tag>
docker push esdrasschonevald/agent-watcher:<tag>
docker tag esdrasschonevald/ensemble:standalone esdrasschonevald/ensemble:<tag>
docker push esdrasschonevald/ensemble:<tag>
```

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
| `POST` | `/api/agents` | yes | create agent `{ name, slug?, description?, profile?, model?, fallback_model?, interval?, workspace? }` |
| `PATCH` | `/api/agents/{id}` | yes | update `{ name?, description?, active?, profile?, model?, fallback_model?, interval?, workspace? }` |
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
- **Agents** — the agent registry, including each agent's runtime spec (profile,
  model, fallback model, interval, workspace).
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
