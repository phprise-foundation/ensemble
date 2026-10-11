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
- Published to GitHub.

## Next

- `local` backend in `agent-watcher` (`--backend local --kanban-url --kanban-token`)
  so agents read/write this board.
- Wire the tested agents into `docker-compose.yml`.
- Embedded always-on chat agent.
