# ROADMAP

1. ~~**Board foundation** — API + auth + UI, OTAKU-compliant.~~ **Done.**
2. ~~**agent-watcher local backend** — `--backend local --kanban-url` so agents
   consume this board instead of GitHub Projects; `--repo`/`--project` optional.~~ **Done.**
3. ~~**Agent composition** — the tested agents (product-owner, architects,
   tech-lead, qa, coder) wired into `docker-compose.yml`.~~ **Done.**
4. **Chat agent** — always-on agent embedded in the board UI.
   - Phase 1 (done): conversation domain, `GET`/`POST /api/chat`, UI drawer, echo stub.
   - Phase 2 (done): real chat service (opencode) behind the `ChatAgent` contract.
   - Phase 3: copilot actions (create/move/comment items).
5. ~~**Configurable workflow (MVP)** — boards, columns and agents become data
   (SQLite); admin screens for boards and agents; stage history and aging.~~ **Done.**
6. **Process visibility** — cycle time per column, rework (backward moves) and
   failure signals; bottleneck views built on `item_events`.
7. **Workflow rules** — allowed transitions per column, WIP limits; drag & drop
   and column reordering in the UI.
8. **Scale the model** — multiple boards, relationships between agents, triggers
   and automation.
9. **Hardening** — SSE/streaming, token instead of raw password, polish.

## Active plan — board-driven agents & dynamic workflow (single container)

Goal: the board becomes the single source of truth for **both** the workflow
(columns, the role that owns each stage, and the allowed transitions) **and** the
agents (their runtime spec). Agents run as independent, parallel processes
materialized by a runner, and everything is runnable with a single `docker run`.

Decisions taken:

- Agents run as **one subprocess per agent** inside a single runner container
  (independent, parallel; no Docker socket).
- A **column owns the role** for its stage; `item.role` remains as a manual
  override.
- Transitions are **explicit per-column allowed targets** (seeded with the
  current linear flow).
- Delivered **board first, then agent-watcher**, then the standalone image.
- Standalone image is built on the **Alpine agent-watcher image** (the bundled
  opencode binary is musl-linked, so a Debian base cannot run it) and serves the
  board with the **PHP built-in server**. `docker-compose.yml` stays for dev.

### Phase A — Board (`multi-agent`), backward compatible — **Done.**

- `Column` gains `role` + explicit `transitions` (first-class `Transitions`).
- `Agent` gains its runtime spec (`rule`, `profile`, `model`, `fallback_model`,
  `interval`, `workspace`).
- SQLite persists both; the defaults are seeded with the current roles and the
  linear flow.
- `POST /api/items/{id}/advance` moves an item along a configured transition and
  assigns the destination column's role.
- Board/Agents UI edits roles, transitions and the agent spec.

### Phase B — agent-watcher (dynamic workflow + runner) — **Done.**

- `local` backend exposes `workflow()` and `advance()`; `kanban` gains `workflow`
  and `advance` commands; the loop derives its columns from the board
  (`column.role == my role`) and the prompt injects the live workflow.
- Rules stop hardcoding statuses/targets.
- `agent-runner` keeps one `agent-watcher` subprocess per active agent from the
  board registry.

### Phase C — Standalone single-container image — **Done.**

- `Dockerfile.standalone` (Alpine + PHP 8.4) and `bin/entrypoint` start the board
  and the runner together, so the whole system runs with a single `docker run`.

## UX track — reactive frontend, prototype backend

New reactive SPA under `frontend/` (Vue 3 + Vite), independent from the backend
and backed by a **fake JSON backend** (a Vite middleware) until each screen's real
endpoints exist. Features are flagged individually, with an orange banner/dots
marking what is still a prototype.

1. ~~**App shell + board** — sidebar, dark/light theme, responsive columns, cards
   (status colour, aging flag, avatar), right-hand issue panel and drag & drop
   that respects the configured transitions.~~ **Done (prototype).**
2. ~~**Workspace tree** — the sidebar mirrors `workspaces -> boards / workflows /
   teams / sources`, with an SVG icon on every entry and menu editors.~~ **Done
   (prototype).**
3. ~~**Workflow admin** — statuses (type, colour, roles), transitions
   (user/agent/both) and issue types (custom fields, levels), scoped to a
   workflow.~~ **Done (prototype).**
4. ~~**Team admin** — agents (name, who am I, age, marital status, roles, profile,
   3x4 portrait), roles and profiles, scoped to a team.~~ **Done (prototype).**
5. ~~**Sources** — local project directories the worker will use as `workdir`.~~
   **Done (prototype).**
6. **Board polish** — open an issue on its own page; make board filters affect the
   cards; surface Experiences in the new menu; WIP hints; remove the off-canvas
   drawer from the Tab order when closed on mobile (`inert` on the `<aside>`
   while `aria-hidden`).
7. **i18n** — the UI starts in English; localisation comes after the flow is set.
8. **Wire the real backend** — replace the fake JSON backend with the PHP REST API
   feature by feature, flipping the per-feature prototype flags.

## Active plan — model performance & pipeline triggers (RFC)

Goal: measure per-model quality and cost; transitions become **pipeline
triggers** (not agents). Design and decisions are **formalised** in
`docs/RFC-model-metrics.md`. Implementation order (RFC §11):

1. ~~Decisions formalised~~ **Done.**
2. ~~Runs ledger~~ **Done** (agent-watcher `watcher.run_agent` → `opencode run
   --format json` + `sessionID`; PRs #3/#4).
3. Infractions + adjudication — **next**.
4. Normalise lineage / spawn (RFC → tasks → subtasks).
5. Implement the triggers (spawn, voting, `subtasks-done`).
6. Semantic layer + metrics read model; update `var/kanban.local/bin/monitor`.
