# STATE

## Done

- OTAKU Application skeleton (Domain / Application / Infrastructure / Interface).
- Kanban domain: `Item`, `Comment`, `Status`, `Role`, `ItemId`, `BoardRepository`.
- Use cases: list, find, create, update, comment, close.
- JSON file persistence with an exclusive lock.
- Single-password authentication (Bearer).
- REST API + static Jira-like board UI.
- Docker image + docker-compose.
- Unit and integration tests (PHPUnit), Psalm at errorLevel 1 (0 errors).
- Docs consolidated under `docs/` (`PHILOSOPHY.md`, `GUIDE.md`, `GITFLOW.md`,
  `ARCHITECTURE.md`, `STATE.md`, `ROADMAP.md`); `AGENTS.md` created at the root.
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

## Next

- Chat copilot (phase 3): write actions (create/move/comment) with a confirmation
  flow. See `docs/CHAT.md` ("Pending") — needs a product decision.
- Hardening: SSE/streaming for the copilot, dedicated token instead of the raw
  board password.
