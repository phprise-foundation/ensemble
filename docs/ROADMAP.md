# ROADMAP

1. ~~**Board foundation** — API + auth + UI, OTAKU-compliant.~~ **Done.**
2. ~~**agent-watcher local backend** — `--backend local --kanban-url` so agents
   consume this board instead of GitHub Projects; `--repo`/`--project` optional.~~ **Done.**
3. ~~**Agent composition** — the tested agents (product-owner, architects,
   tech-lead, qa, coder) wired into `docker-compose.yml`.~~ **Done.**
4. **Chat agent** (current) — always-on agent embedded in the board UI.
   - Phase 1 (done): conversation domain, `GET`/`POST /api/chat`, UI drawer, echo stub.
   - Phase 2: real chat service (opencode) behind the `ChatAgent` contract.
   - Phase 3: copilot actions (create/move/comment items).
5. **Hardening** — real-time updates (SSE), token instead of raw password, polish.
