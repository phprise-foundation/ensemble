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
- **Standalone single-container image (Phase C):** `Dockerfile.standalone`
  (Alpine `agent-watcher` base + PHP 8.4, since opencode is musl-linked),
  `public/router.php` and `bin/entrypoint` bring up the board (PHP built-in
  server) and the runner in one container, so the whole system runs with a single
  `docker run`. Built and smoke-tested locally (UI, health, create, advance).
  README documents a local test checklist and the Docker Hub publishing steps.
- **Reactive frontend (UX prototype):** new `frontend/` SPA (Vue 3 + Vite) served
  with a single `npm run dev`. A **fake backend** (Vite middleware,
  `plugin/fake-backend.js`) draws the whole `/api` and persists to
  `frontend/.data/db.json`, so the experience is functional while agents are not
  triggered. Features carry per-feature prototype flags (orange banner/badges in
  `src/api/simulation.js`) to be flipped when the real endpoint lands.
  - **Model:** `workspaces -> boards / workflows / teams / sources`. A workflow
    owns statuses, transitions and issue types; a team owns agents, roles and
    profiles; a board references a workflow and owns its columns and filters;
    **sources are local directories** (host paths) that will become the agents'
    `workdir`.
  - **Sidebar:** compact tree with an SVG icon on every entry, tight rows and
    small indentation; Board / Workflow / Team / Workspace / Source editors open
    from the menu (board editor: ID, name, workflow, columns, filters; workflow
    creation can copy a template). Statuses/transitions/issue-types and
    agents/roles/profiles are always listed under their workflow/team. On small
    screens the sidebar becomes a drawer that closes on route navigation, on
    Escape, on the scrim and when opening an editor (the editors are teleported
    to `body` so they stay visible). While open in overlay mode the drawer traps
    keyboard focus (Tab/Shift+Tab cycle inside, the background content is
    `inert`, and focus returns to the toggle on close) and announces itself as a
    modal dialog through conditional ARIA (`aria-expanded`/`aria-controls` on the
    toggle; `role="dialog"`/`aria-modal`/`aria-hidden` only in the mobile
    overlay). The mobile breakpoint is centralized in a
    single SCSS variable — `$sidebar-breakpoint` (`1024px`) — exported as the
    `--breakpoint-sidebar` custom property for the drawer and the future icon
    bar.
  - **Collapsed sidebar (activity bar) — CSS layer:** `--activity-width: 48px`
    token in `:root`; `.sidebar.collapsed` rules (icon-only — labels, chevrons,
    badges, source paths, brand text and entry actions hidden; icons centred;
    submenu/leaf indentation zeroed); `width` added to the sidebar transition;
    and `.sidebar-toggle` hidden by default, desktop-only via
    `@media (min-width: $sidebar-breakpoint + 1)`. The toggle state/button and
    the `Ctrl+B` shortcut are wired in the follow-up subtask (KAN-24).
  - **Collapsed sidebar (activity bar) — component layer (KAN-23):**
    `AppSidebar.vue` accepts `collapsed: { type: Boolean, default: false }` and
    binds `:class="{ open, collapsed }"` on the `<aside>`; every leaf
    (`RouterLink.leaf`) and the group/entry rows carry a `title`/`:title`
    (static for fixed labels, bound for dynamic names) so the native hover
    tooltip shows the readable label when collapsed. The component only
    receives `collapsed` — the state, toggle button and `Ctrl+B` shortcut stay
    in KAN-24.
  - **Board:** responsive columns, cards (status colour, aging flag, avatar), the
    right-hand issue panel (status + assignee + comments) and drag & drop that
    respects the transitions. Dark default theme with a light option.
  - **Admin:** full CRUD for statuses, transitions, issue types, agents (name,
    who am I, age, marital status, roles, profile, 3x4 portrait), roles and
    profiles; the board is created/edited from the menu.
  - **Sources:** registering a source validates the directory in the host
    process (the simulated middleware today; the real backend later) and returns
    the true cause on failure — `422` empty or nonexistent path, `404` editing a
    nonexistent source, `409` duplicate id — instead of the generic
    "sources not found".
  - Verified headless (render, panel open, valid/rejected drops, persisted CRUD).

## Next

- **Model-performance measurement — decisions formalised** in
  `docs/RFC-model-metrics.md` (status: Formalised; not implemented).
  - Transitions become **pipeline triggers** (board rules, not agents) with
    `ready`/`steps` flags; `assignee` = attribution, `worker` = lock.
  - Status sets: `task` `Refining → Refined → Testing → Done` (Testing only
    after all subtasks `Done`, automatic); `subtask` `Coding → Reviewing → Done`.
  - RFC spawned by trigger: **Solutions sets `ready`** on the discovered idea
    (go/no-go); the PO authors only the RFC description; Solutions/Systems/
    Framework author proposal/feasibility/plan; all 4 (PO + 3 architects) vote,
    **no abstention**, majority = 3 approvals, 2 rejects is terminal.
  - Metrics: **M1** defect-attribution rate; **M2** cost per accepted task with a
    **per-model breakdown** (equal-split allocation); no maturation window /
    minimum sample; monitor stays at `var/kanban.local/bin/monitor` (CLI only).
  - Infractions: first-class entity, only `upheld` count, no self/reciprocal,
    evidence required, `infractor` resolved from the runs ledger.
  - **Done (step 2):** runs ledger in agent-watcher (`watcher.run_agent` runs
    `opencode run --format json`, captures the `sessionID`, writes one JSON line
    per run to `<workdir>/.agent-watcher/runs.jsonl`; `--runs-file` flag).
    Merged: agent-watcher PRs #3 (runner) and #4 (runs ledger).
  - **Done (step 3, core):** infractions + adjudication (multi-agent PR #30):
    `Infraction` entity + `Severity`/`Cause`/`InfractionStatus`; endpoints
    `POST /api/items/{id}/infractions`, `GET /api/items/{id}/infractions`,
    `POST /api/infractions/{id}/adjudicate`; governance invariants (no self,
    evidence required, adjudicator ∉ {infractor, detector}, single verdict).
    Deferred: weight/policy version, no-reciprocal, infractor from ledger,
    root-cause redirect.
  - **Done (step 4):** item lineage (multi-agent PR #32): `IssueType`
    (idea/rfc/task/subtask) + `parent_id` + `childrenOf`; `POST /api/items`
    reads `type`/`parent_id`; item detail exposes `children`.
  - **Next task (step 5):** implement the **triggers** (spawn, voting,
    `subtasks-done`).
- **UX track (frontend):** open an issue on its own page; surface Experiences in
  the new menu; make board filters affect the cards; i18n. Then replace the fake
  JSON backend with the real PHP API feature by feature, flipping the prototype
  flags.
- **Workflow transitions polish** — WIP limits and a transition editor beyond the
  multi-select (e.g. drag between columns).
- **Process visibility** — cycle time per column, rework (backward moves) and
  failure read models built on `item_events`.
- Chat copilot (phase 3): write actions (create/move/comment) with a confirmation
  flow. See `docs/CHAT.md` ("Pending") — needs a product decision.
- Hardening: SSE/streaming for the copilot, dedicated token instead of the raw
  board password.
