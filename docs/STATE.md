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
  - **Contrato de aceite estendido (KAN-10):** o par
    `frontend/tests/sidebar-collapse.feature` e `frontend/tests/sidebar-collapse.test.mjs`
    foi estendido de 9 para 11 cenários e de 4 para 8 testes (foco visível,
    rótulos das folhas ocultos no colapsado, estado determinístico sem
    `localStorage` e breakpoint desktop consumindo `$sidebar-breakpoint + 1`);
    o guia `docs/KAN-10-guide.md` ganhou endereço único. Mudança só de teste e
    documentação, sem código de produção. PR #11, release `v0.11.1`.
  - **Conserto do vazamento do colapso no breakpoint (KAN-10):** as regras
    `.sidebar.collapsed` passam a viver dentro de
    `@media (min-width: $sidebar-breakpoint + 1)` e o atalho `Ctrl+B`/`Cmd+B`
    fica inerte em origem mobile (`App.vue` retorna cedo quando `isMobile.value`),
    fiel à decisão E1 — o estado colapsado é suspenso no mobile (drawer do
    KAN-9 em largura plena, rótulos visíveis) e restaurado ao voltar ao
    desktop, sem `localStorage`. Duas cercas de regressão de fonte em
    `frontend/tests/sidebar-collapse.test.mjs`. PR #59, release `v0.11.4`.
  - **Sidebar id-derivation fix (KAN-29):** the `team` and `workflow` editors no
    longer derive the id slug at creation — the derivation now requires the
    guard `current.draft.__existing` (the same pattern `source` received in
    KAN-20), so new drafts reach `saveEntity` with an empty id and the store
    POSTs on creation and PATCHes on edit. Covered 1:1 by
    `frontend/tests/entity-creation-verb.{feature,spec.js}` (vitest, 4 cases:
    create → POST and edit → PATCH for both entities). The review validated the
    merge artifact (15/15 vitest, 23/23 `node:test`, PHPUnit 42/122,
    Psalm 0). PR #23, release `v0.10.1`.
  - **Drawer test robustness debts (KAN-30):** the generic toggle assert in
    `frontend/tests/mobile-drawer.test.mjs` was tightened to the specific
    handler `@click="sidebarOpen = true"`, aligned with the scrim assert's
    specificity; the obsolete `(Red Phase)` label was verified as already
    removed by KAN-14 (the debt was born obsolete). One-line diff, single
    atomic commit, no production changes; suite green (4/4 node tests).
    PR #26, release `v0.10.2`.
  - **Terceira dívida de robustez do teste do drawer (KAN-53):** a guarda em
    `frontend/tests/mobile-drawer.test.mjs:13` deixou de casar o valor literal
    `1024px` e passou a afirmar o contrato — existe uma declaração
    `$sidebar-breakpoint: <valor>;` — sem conhecer o valor; `:14` e `:15`
    permanecem inalterados. Diff de uma linha, commit atômico, sem alteração em
    produção; suíte verde (4/4 node tests). PR #55, release `v0.11.2`.
  - **Rastreio do spec E2E do drawer (KAN-54):** o spec
    `frontend/cypress/e2e/mobile-drawer.cy.js` — prova do critério 4 da KAN-9 no
    navegador, que vivia apenas não rastreado no worktree e morria num
    `git clean` — pousou na árvore com endereço forte (commit atômico, PR #57,
    release `v0.11.5`). Sem alteração em produção; 3/3 verde reproduzido na
    revisão contra a ponta pós-merge. Imprecisão de proveniência herdada no
    cabeçalho do artefato ("— KAN-30"; o spec nasceu na linhagem do critério 4
    da KAN-9) registrada como não bloqueante, com correção de uma linha
    sugerida.
  - **Backfill das chaves novas do seed no backend fake (KAN-56):** o `load()`
    de `frontend/plugin/fake-backend.js` passou a mesclar **somente chaves
    ausentes** do seed na leitura (`db[key] ??= seed[key]`; exportado como
    `load(filePath = dataFile)`): um `frontend/.data/db.json` criado antes da
    KAN-32 (sem a chave `user`) recebe o backfill e `GET/PATCH /api/user` voltam
    ao normal — `GET` → 200 `{ login: "admin", displayName: "" }` e `PATCH`
    persiste o nome sem mexer no login; chave presente nunca é sobrescrita
    (arrays vazios e `displayName` definido sobrevivem) e o caminho "arquivo não
    existe" continua criando o seed completo. Antes, o boot sobre o banco antigo
    estourava `500` em `/api/user` e `unhandledrejection` no carregamento da
    sessão. O contrato de teste da QA entrou commitado
    (`frontend/tests/seed-backfill.{feature,test.mjs}` — BDD em português +
    integração `node:test`, 4 testes — e
    `frontend/cypress/e2e/legacy-db-boot.cy.js`, que sobe a interface sobre o db
    legado); o harness `run-e2e.sh` agora semeia o db legado (com backup/restore
    do banco do dev) e `cypress.config.js` ganhou `screenshotOnRunFailure:
    false`; guia em `docs/KAN-56-guide.md`. Evidência: node --test 4/4 no item e
    44/44 na suíte, vitest 32/32 e `npm run test:e2e` 10/10 (`app`, o spec novo
    e `profile-menu`) com o db legado do harness. PR #61.
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

- **Rule → role unification:** the redundant `rule` field was removed from the
  agent registry (agent-watcher uses `--role` + `roles/`; the board's `agents`
  no longer carry `rule`). `role` = identity + instructions; `profile` stays
  separate.
- **User profile menu + session gate (KAN-15, MVE Phase 1):** the top bar carries
  a `ProfileMenu` (38px initial avatar + single-line truncated display name,
  dropdown `profile`/`logout`) as the last `.topbar` child, backed by the
  `session` store (`load`/`rename`/`signOut`/`signIn`) and the fake backend's
  `GET/PATCH /api/user` (login immutable; display name ≤40 chars). The `/profile`
  route edits only the display name. `App.vue` gates the whole shell on
  `session.signedIn`: logout renders a neutral `.session-ended` view, "Sign in
  again" restores it. `session.load()` is added to `onMounted` without disturbing
  the KAN-24 shortcuts; the central `h1` yields (`min-width: 0; text-overflow:
  ellipsis; …`) and the `.topbar` anchors never shrink. Covered by vitest
  (`App session gate`) and the smoke E2E (§11 selector fix). PRs #52/#53/#54,
  release `v0.11.0`.
- **BDD + integração + E2E do menu de perfil (KAN-37):** a fonte única do
  aceite fecha por inteiro a seção 11 do guia `docs/KAN-15-guide.md` — diff só
  de teste (PR #58). A integração (`profile-menu.test.mjs`) reafirma
  `GET/PATCH /api/user` (teto de 40 com `422`, `login` sempre ignorado) e
  guarda o contrato na origem (`watch` da hidratação, `readonly` do login); o
  E2E (`profile-menu.cy.js`) mede os cinco grãos no navegador: 38px via
  `getBoundingClientRect`, viewport lido de `--breakpoint-sidebar` sem o
  literal `1024`, login imutável reafirmado antes/depois do save e após o
  reload, recarga hidratando o campo e sucesso pela região viva
  `[role="status"]`. Barra refeita em worktree limpo no merge: node 40/40,
  vitest 32/32, build limpo, E2E 8/8 sem erro de runtime. Release `v0.11.3`.

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
