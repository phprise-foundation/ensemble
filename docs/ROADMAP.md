# ROADMAP

1. **Board foundation** (current) — API + auth + UI, OTAKU-compliant.
2. **agent-watcher local backend** — add `--backend local --kanban-url` so agents
   consume this board instead of GitHub Projects; make `--repo`/`--project` optional.
3. **Agent composition** — register the tested agents (product-owner, architects,
   tech-lead, qa, coder) in `docker-compose.yml`.
4. **Chat agent** — an always-on agent embedded in the board UI.
5. **Hardening** — real-time updates (SSE), token instead of raw password, polish.
