# STATE

## Done

- **P0 foundation — tenancy, PostgreSQL and Doctrine (branch
  `feat/p0-tenancy-api`):** users and workspaces are N:N through
  `workspace_members`; a global admin is bootstrapped from
  `BOOTSTRAP_ADMIN_LOGIN`/`BOOTSTRAP_ADMIN_PASSWORD` only on a fresh database
  and creates the other users via `POST /api/users`; sessions are opaque
  bearer tokens stored as SHA-256 hashes. New API routes:
  `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/me`,
  `GET/POST /api/users` (admin), `GET/POST /api/workspaces`,
  `GET/PATCH /api/workspaces/{id}` and members. Non-members receive `404`;
  `PATCH` is used for updates, never `PUT`. Persistence is PostgreSQL via
  Doctrine ORM 3/DBAL 4 behind domain contracts; the domain does not know
  Doctrine. Dev-only PostgreSQL lives in `docker-compose.dev.yml`; `bin/dev-up`
  starts it, creates `phprise_test` and runs `bin/install`. The legacy SQLite
  board, agent-watcher and static UI remain temporarily and are not used by the
  new routes. 63 PHPUnit tests and Psalm level 1 are green. Decisions in
  `docs/ADR-001-tenancy-postgres-doctrine.md`.
- **P1a — workflow chain (branch `feat/p1-workflow-chain`):** workflows,
  issue types and custom field definitions are now workspace-scoped and
  persisted with Doctrine. Decisions in
  `docs/ADR-002-workflow-model.md`: issues belong to the workspace (no
  `boardId`); a board references one workflow; statuses will belong to an issue
  type with a fixed kind (`initial`/`transitional`/`final`); transitions never
  change the issue type; RFC → task is a future user-configured trigger, not a
  transition. Fields support `text`, `textarea`, `number`, `date`, `boolean`,
  `single_select` and `multi_select`; select options live on the definition.
  Endpoints: `GET/POST/PATCH/DELETE` on
  `/api/workspaces/{ws}/workflows`, nested `/issue-types` and nested
  `/fields` (plus direct update/delete by id). Keys are unique in their scope;
  writes require workspace owner/admin, reads require membership.
  70 PHPUnit tests and Psalm level 1 are green.
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
  - **Drawer em tela cheia abaixo de 768px (KAN-95 + KAN-96):** a produção
    (PR #104) adicionou o token `$mobile-breakpoint: 768px`/`--breakpoint-mobile`
    no SCSS, a media query estrita `max-width: $mobile-breakpoint - 1`, o painel
    com `inset: 0; width: 100%` (sem `100vw`/`100vh`), o botão dedicado
    `button.icon-btn.sidebar-close` (`v-if="narrow && isMobile"`, alvos de 44px
    em `.nav-entry`/`.leaf`) e o scroll lock por classe `body.drawer-locked`
    (`overflow: hidden`), controlado no `App.vue` (`isNarrow` via
    `mediaQueryFor('--breakpoint-mobile', -1)`) e removido no unmount. A onda 2
    (KAN-96, PR #116, release `v0.24.1`) provou o contrato: BDD em pt-BR
    (`tests/mobile-drawer-fullscreen.feature`), contrato na fonte
    (`tests/mobile-drawer-fullscreen.test.mjs`), integração no `App.vue`
    (`tests/mobile-drawer-fullscreen.spec.js`) e E2E em navegador real
    (`cypress/e2e/mobile-drawer-fullscreen.cy.js`), cobrindo 767/768/1023/1024/
    1025, os quatro fechos (botão, Escape com foco de volta ao `menu-toggle`,
    navegação e scrim na faixa parcial), lock, alvos de 44px e ausência de
    overflow horizontal em 320/375/767px.
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
  - **Prova em navegador do colapso da sidebar (KAN-31):** a dívida de
    cobertura nomeada na KAN-10 fechou no nível certo: o spec
    `frontend/cypress/e2e/sidebar-collapse.cy.js` mede o critério 2 no
    navegador real, não no source — submenu de Boards asserido aberto
    (`.open`; `openBoards` nasce `true`), coleção `.leaf .label` garantida
    (`length > 0`) com o primeiro rótulo visível; colapso via UI real
    (`button.sidebar-toggle`, `.collapsed` e `aria-expanded="false"`
    asseridos); cada rótulo `not.be.visible` **com o submenu aberto**;
    `.leaf svg` visíveis e `title` não vazio em cada folha; clique na própria
    `.leaf` prova a navegação por `cy.location('hash')` = `#/boards/KANB` (o
    roteador usa `createWebHashHistory()`; KANB é o primeiro board do seed).
    Commit atômico, só o spec (56 linhas), cerca de erros fiel ao smoke. Na
    revisão, controle negativo do revisor: com `.sidebar.collapsed .leaf span`
    trocada para `display: block`, o spec falha em
    `expected '<span.label>' not to be 'visible'`; com a regra, verde 1/1 e o
    CI do PR roda E2E em navegador real. PR #62, release `v0.11.8`.
  - **Sidebar id-derivation fix (KAN-29):** the `team` and `workflow` editors no
    longer derive the id slug at creation — the derivation now requires the
    guard `current.draft.__existing` (the same pattern `source` received in
    KAN-20), so new drafts reach `saveEntity` with an empty id and the store
    POSTs on creation and PATCHes on edit. Covered 1:1 by
    `frontend/tests/entity-creation-verb.{feature,spec.js}` (vitest, 4 cases:
    create → POST and edit → PATCH for both entities). The review validated the
    merge artifact (15/15 vitest, 23/23 `node:test`, PHPUnit 42/122,
    Psalm 0). PR #23, release `v0.10.1`.
  - **Código de saída do harness de E2E (KAN-57):** `frontend/run-e2e.sh` passou
    a capturar o status do `wait` do Cypress e repassá-lo com `exit` **depois**
    do cleanup (`trap cleanup EXIT` continua derrubando dev server e runner):
    suíte vermelha sai com ≠ 0, timeout sai com ≠ 0 e a rodada verde segue com
    `0` — antes a última atribuição (`CYPRESS_PID=""`) fixava o status em 0 e
    mascarava qualquer reprova. O contrato de teste escrito pela QA no
    Refinamento entrou commitado (`frontend/tests/run-e2e-exit-code.{feature,test.mjs}`
    — BDD em português + integração `node:test`, 3 testes — mais as fixtures
    `cypress/harness/{failing,passing}.cy.js`, fora de `cypress/e2e/` e portanto
    invisíveis à rodada padrão; os `*.test.mjs` seguem fora do vitest via
    `test.exclude`). Evidência da barra de qualidade: `node --test` 3/3, vitest
    32/32 e `npm run test:e2e` verde, sem erro de runtime/console. PR #60, release
    `v0.11.7`.
  - **Portão "no spec files found" no harness de E2E (KAN-60):** `frontend/run-e2e.sh`
    agora desvia toda a saída do `cypress run` para um log (`CYPRESS_LOG`,
    limpo no `cleanup`) enquanto a mantém visível no `tee`; se a rodada sai
    com `0` **e** o log casa com `no spec files( were)? found`, o harness sai
    com `1`. Defesa em profundidade do contrato KAN-59: não depender do humor
    da versão do Cypress — `--spec` sem match nunca mais devolve sucesso. Verde
    e timeout seguem `0`/`≠ 0` como em KAN-57; sem `exit 1` fixo. O contrato de
    teste da QA entrou commitado (`frontend/tests/run-e2e-nospec.{feature,test.mjs}`
    — BDD em português + integração `node:test`, 3 testes — com o caminho do
    script ancorado por `import.meta` e invocado por `bash`, pois o script é
    `100644`). Ajuste honesto sobre o rascunho da QA: com `--spec` vazio o
    Cypress 16.2.0 aplica o `specPattern` default e sai `0` (evidência: 5 specs
    rodados), de modo que o cenário "vazio → 1" era contraditório com o próprio
    contrato de saída do item (o portão só converte `0` em `1` quando o log casa
    com "no spec files found"). O cenário 2 do BDD e do teste passou a esperar
    `0`. Evidência da barra de qualidade: `node --test` 3/3, vitest 32/32 e
    `npm run test:e2e` verde (14/14), sem erro de runtime/console — nota: nas
    provas com múltiplos worktrees usou-se `E2E_PORT` exclusiva, pois o dev
    server é `--strictPort` e colide na 5173. Na revisão do Tech Lead o verde
    foi reproduzido a partir de dentro de `frontend/`: `node --test` 3/3,
    vitest 32/32 e E2E 14/14. PR #64, release `v0.11.9`.
  - **Tema escuro alinhado ao VSCode Dark+ (KAN-8, entrega KAN-61):** os nove
    tokens base do tema escuro passaram aos valores exatos da referência
    (`--bg #1e1e1e`, `--surface #252526`, `--surface-2 #2d2d2d`,
    `--surface-3 #333333`, `--border #454545`, `--text #d4d4d4`,
    `--muted #858585`, `--accent #3794ff`, `--accent-contrast #ffffff`);
    `--selection #264f78`, `--button-bg #0e639c` e os seis `--syntax-*` nascem
    no mesmo bloco escuro, os de syntax em reserva. A `::selection` é escopada
    ao escuro, `.leaf.router-link-active` usa o token de seleção (segundo uso,
    ≈5,73:1) e `.button` usa `var(--button-bg, var(--accent))`, cujo fallback
    mantém o tema claro congelado (branco sobre `#0e639c`, ≈6,40:1). O arnés
    `frontend/tests/dark-theme.test.mjs` passou a ler `main.scss`, eliminando o
    ENOENT herdado da KAN-12. Tema claro, tokens de estado, `seed.js` e views
    intocados; commit único Conventional e fatia atômica. Barra reexecutada no
    merge — a árvore de `main` é idêntica à de `a18ebcb`: dark-theme 8/8,
    vitest 32/32, build ok. PR #65, release `v0.12.0`.
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
    e `profile-menu`) com o db legado do harness. PR #61, release `v0.11.6`.
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
- **Grade direita — painel do copilot colapsável (KAN-62):** o board novo ganha
  o espelho da grade esquerda: `AppCopilot.vue` (esqueleto `#app-copilot`
  `.copilot-panel`, irmão de `.content`) com token próprio
  `--copilot-width: min(25vw, 400px)`, `copilotOpen = ref(true)` (nasce
  expandido, sem persistência) e botão `.copilot-toggle` no menu superior com
  `aria-expanded`/`aria-controls`, mais o atalho `Ctrl+Alt+B`/`Cmd+Alt+B`
  checado antes do `Ctrl+B`. Desktop-only pelo breakpoint único: em 1024px ou
  menos painel e símbolo não entram no DOM. Zero rede, zero store, zero
  `v-html`; colapsar é `v-if` (não há conversa nesta rodada). O painel de issue,
  o sidebar e o console permanecem independentes, com a compressão do board em
  1024–1280px aceita no RFC KAN-5. Contrato em `docs/KAN-62-guide.md`; 7 guardas
  estáticos, vitest 36/36, build e E2E 6/6 (1280/1920/1024) verdes. PR #74,
  release `v0.13.0`.
- **Ícone `console` em `AppIcon.vue` (KAN-63):** entrada `console` no dicionário
  `paths` com os três caminhos do contrato KAN-18 (`M4 5h16v14H4z`,
  `M7 9l3 3-3 3`, `M13 15h4`), no estilo herdado do componente; nenhum
  consumidor novo e nenhum ícone existente alterado. O rebase sobre a ponta
  pós-`copilot` (KAN-69) preservou o glifo anterior; vitest 36/36, build e CI
  verdes. PR #66, release `v0.14.0`.
- **Grade inferior — fundação do console (KAN-65):** o board novo ganha a
  `AppConsole.vue` (`section#app-console.console`) abaixo do `.main`, com
  cabeçalho, botão fechar e handle `.console-resize` (arraste por ponteiro,
  setas ↑/↓ de ±16px e duplo clique em 240px). `App.vue` mantém
  `consoleOpen = ref(false)` e `consoleHeight = ref(240)`, aplica a altura por
  `--console-height` inline, expõe `.console-toggle` com
  `aria-expanded`/`aria-controls` ao lado do sidebar e responde a
  `Ctrl+J`/`Cmd+J` só no desktop. `main.scss` recebe `--font-mono` e todas as
  seções `.console*` com clamp 120px..60vh, consumindo só tokens de tema. A
  fatia consolidou as versões provisórias de store/output/input (KAN-64/66/67),
  a refinar nos PRs seguintes. Unit 53/53, build limpo, Cypress 25/25 com
  geometria e arraste reais. PR #77, release `v0.15.0`. CI: o passo `Contracts`
  segue vermelho por Xvfb (display :99), dívida de harness pré-existente.
- **Grade inferior — store do console e catálogo (KAN-64):** `store/console.js`
  transforma os comentários de `GET /api/issues` em mensagens
  `{ seq, at, actor, kind, text }` (só `author !== 'user'`), com dedupe por
  `issueId:commentId` e fallback autor + instante + hash do body para o
  presenter PHP sem `id`; a releitura não duplica e os idênticos do mesmo lote
  entram. O polling de 5s pausa em `document.hidden`; `start()`/`stop()` são
  chamados por `AppConsole.vue` em `onMounted`/`onUnmounted` (grade montada por
  `v-if`). `store/commands.js` executa localmente o catálogo fechado `/status`,
  `/list`, `/help` e `/clear` contra o `boardStore`; `/clear` é local e sem eco,
  desconhecido vira `error` sugerindo `/help`, e `busy` bloqueia reenvio. Falha
  de polling vira uma única linha de erro até o próximo sucesso. O BDD mantém
  os 12 cenários congelados e acrescenta o 6º (start/stop), total 13; node
  `17/17`, vitest `53/53`, `console.cy.js` `3/3`, build verde. PR #87, release
  `v0.15.2`. Pendência registrada na KAN-124: o poll imediato ao abrir a grade
  ainda não é disparado, então o log pode ficar vazio por até 5s.
- **Aceitação da grade direita — BDD, guardas e E2E (KAN-71):** a onda 3
  consumiu os contratos das ondas 1/2 e fechou os cinco critérios do RFC com
  prova executável. `tests/copilot.feature` (Gherkin pt-BR) descreve painel
  expandido em 1280/1920, alternância, independência de sidebar/painel de
  issue/colunas, ausência no breakpoint e no telefone, reload sem memória e
  ARIA/atalho. `tests/copilot.test.mjs` soma 7 guardas estáticos (zero
  rede/store/`v-html`, `copilotOpen = ref(true)`, `altKey` antes do `Ctrl+B`,
  token de largura e ícone `copilot`). `cypress/e2e/copilot.cy.js` mede a
  largura no DOM (320/400), abre card e reafirma `.issue-panel` e colunas ao
  alternar, e cobre 1024×900 e 390×844. Verificação na `main` pós-merge:
  vitest 53/53, guardas 7/7, E2E 27/27 (`copilot.cy.js` 8/8). PR #82, release
  `v0.15.1`. Ressalva: a independência do console segue sem prova dedicada (a
  KAN-66 cobriu só o painel de output); a CI segue vermelha no `Contracts` por
  Xvfb.
- **Grade inferior — painel de output (KAN-66):** `ConsoleOutput.vue` substitui
  o stub da fundação e renderiza `consoleStore.state.messages` como log
  `role="log"`/`aria-live="polite"`, uma `.console-line` por mensagem com classe
  por `kind`, horário local e ator. `messages`/`unread` são computados — o
  `/clear`, que troca o array, continua reagindo. `onMounted` ancora no fundo
  antes de medir; com o leitor a ≤24px do fim o painel permanece fixado, rolar
  para cima trava, e a pílula `.console-jump` conta `unread` e volta ao fundo
  chamando `clearUnread()`. O filtro por ator (`All` + distintos) só altera a
  renderização, o texto é interpolado (sem `v-html`) e o timer do indicador de
  atividade é cancelado no unmount. Na árvore do PR: vitest `64/64` em UTC,
  Asia/Tokyo e America/Sao_Paulo, build verde e E2E `27/27` (`console.cy.js`
  `3/3`). PR #81, release `v0.16.0`; a CI segue vermelha no `Contracts` por
  Xvfb.
- **Grade inferior — painel de input (KAN-67):** `ConsoleInput.vue` substitui o
  stub da fundação. O submit normaliza espaços, limpa o campo e entrega o texto
  ao catálogo fechado por `runCommand(rawInput)`; com `state.busy` ativo, input
  e botão ficam desabilitados e o submit é ignorado, fechando o reenvio
  duplicado por três vias (componente, guarda de submit e store). Comando
  desconhecido não é avaliado no componente: `commands.js` responde
  `kind: 'error'` no output, e `/clear` segue local. O histórico ↑/↓ é efêmero,
  preserva e devolve o rascunho digitado. O spec real ancora a saída de
  `commands.js` (`Agentes:`, `Itens por status:`, `A fazer: 1`, `Feito: 1` e
  `KAN-1 · Item ativo · A fazer`). Vitest `75/75`, build 59 módulos e CI
  `frontend` verde na reexecução — a falha era flake do harness em
  `run-e2e-exit-code`, reproduzido 3/3 local. PR #88, release `v0.17.0`; os
  rótulos seguem o contrato do guia e a tradução pt-BR entra na KAN-18.
- **Grade inferior — console em pt-BR, `at` ISO e poll imediato (KAN-18):** a
  grade fecha o MVE com os rótulos do contrato aplicados em `ConsoleInput.vue`,
  `ConsoleOutput.vue`, specs e E2E (`Entrada do console`,
  `Digite um comando… (/help)`, `Enviar`, `Nenhuma mensagem`,
  `Filtrar por ator`, `Todas`, `Nova saída`/`Ocioso`, `N nova`/`N novas`). A
  pílula singular ganhou caso próprio e falha sob a mutação `nova{{ 's' }}`;
  `append()` normaliza qualquer `at` para ISO 8601 UTC, inclusive o `+00:00` do
  presenter PHP, e `start()` dispara `pollTick()` imediato (KAN-124), com a
  reabertura medida sem `pollOnce()` manual. No merge com a main `c562dcb`:
  vitest `79/79`, `console.test.mjs` `21/21`, build verde e E2E `27/27` em porta
  livre (`console.cy.js` `3/3`). PR #92, release `v0.17.1`. Dívidas registradas:
  o harness de E2E ainda aceita um Vite alheio na 5173 — o guarda está na
  KAN-68/PR #90, que precisa de rebase — e o teste genérico de rótulos em
  `console.spec.js` anuncia pluralizar a pílula sem medi-la (coberta no spec
  dedicado).
- **Testes do console — BDD, integração e E2E da grade (KAN-68):** a grade
  inferior tem prova completa como o usuário a usa. BDD em português com 16
  cenários e mapeamento um a um para as suítes (integração 3,9,10,11; vitest
  1,2,4,5,6,8,12; fundação 7; E2E 13,14; coletor 15; harness 16). O harness de
  E2E recusa medir um Vite alheio — porta ocupada ou servidor morto abortam com
  status 1 (`run-e2e-port.test.mjs`) — e o coletor em
  `cypress/support/e2e.js` reprova qualquer cenário com erro, rejeição solta ou
  `console.error`. Revisão 3 confirmou por mutação: sempre-plural derruba o
  teste da pílula singular e remover o `pollTick()` imediato do `start()`
  derruba a reabertura. Barra local: `node --test` 22/22, vitest 79/79, E2E
  27/27 em porta livre. PR #90, release `v0.17.2`. Ressalva menor registrada na
  revisão: preferir `toHaveBeenCalledTimes(1)` em vez de
  `toBeGreaterThanOrEqual(1)` no spec de reabertura.
- **Modelo único de folhas + rail colapsado (KAN-83, produção):** a sidebar tem
  um só modelo de navegação. `frontend/src/navigation.js` é puro e expõe
  `sidebarNavigation(store, workspaceId)` (boards, workflows com
  Statuses/Transitions/Issue types, teams com Agents/Roles/Profiles e
  Experiences, cada folha com `key`, `label`, `title`, `railTitle`, `icon`,
  `to` e `feature`) e `railSections(navigation)`, que filtra grupos vazios;
  workspace nulo devolve modelo e rail vazios. `AppSidebar.vue` consome o
  modelo na árvore expandida (`entry.entity.name` para o grupo, folhas de
  `entry.leaf`/`entry.leaves`) e, colapsada em desktop, troca a árvore por
  `nav.nav.rail[aria-label="Pages"]` com folhas `a.leaf.rail-leaf` 36×36,
  ícone 18px, `title`/`aria-label` pelo `railTitle` único e ativo com fallback
  `var(--selection, var(--surface-3))`; o CSS é aditivo (`main.scss` +33/−0) e
  mobile, drawer e KAN-65 ficam intactos. O primeiro PR (#85) foi rejeitado por
  remover 239 linhas de CSS alheias e derivar o grupo de `railTitle.split`;
  reenviado limpo no PR #96. Prova: `tests/sidebar-navigation.test.mjs` (5
  testes) e `cypress/e2e/sidebar-rail.cy.js`. Ressalva: `sidebar-collapse.cy.js:41`
  ainda espera `.leaf .label`, que o rail remove — a adaptação é prova de
  aceite da sub-task de testes; o check de CI vermelho do PR era do head
  anterior ao force-push. Release `v0.18.0`.
- **Uniformização do modelo de folhas (KAN-91, fix):** o QA em `main`
  (`36c6e91`) reprovou a incoerência entre o spec rastreado
  `tests/sidebar-navigation.spec.js` (entries `{ entity, leaves }`) e a
  produção (boards com `leaf` singular e Experiences como folha solta). O PR
  #118 devolveu uma forma única: boards viram `{ entity, leaves: [boardLeaf] }`,
  Experiences viram `experienceEntry()` com entity sintético, `sectionsOf`
  concatena `entry.leaves` de todas as seções e `AppSidebar.vue` itera
  `entry.leaves` em boards; `railSections` segue filtrando grupos vazios. Na
  mesma rodada, conflito KAN-87 × KAN-84 resolvido pela Lucy: ≤1024px é mobile
  (`v-if="!isMobile"` em `App.vue` intocado) e o spec do rail usa
  `should('not.exist')` no toggle em 1024px (PR #119), com a asserção de
  1025px intocada. Ressalva de infra: o evento `change` de `matchMedia` não é
  disparado sob `cy.viewport` no Electron (harness de `run-e2e.sh`); num
  navegador real (Chrome) o app reage pelo listener (spec 9/9 e suíte 47/47
  em `--browser chrome`). Prova: `test:unit` 105/105, `node --test`
  (collapse/navigation/topbar) e build em `main` (`d7a0af9`). Release
  `v0.25.0`.
- **Modal de nova task em largura total com status inicial validado (KAN-94,
  produção):** no protótipo, `summary` e `description` do modal de criação
  passam a ocupar 100% da largura útil (CSS aditivo escopado a
  `.new-task-modal`, largura do modal preservada em 520px) e o seletor de
  status lista **somente** status `type === 'initial'` do workflow do board,
  com default no primeiro da ordem (UI bloqueada, com aviso pt-BR, quando o
  workflow não tem `initial`). O `POST /api/issues` do fake backend valida
  **antes** de qualquer mutação: `boardId` ausente/inexistente → 422
  `{ error, field: 'boardId' }` sem fallback para `db.boards[0]`; status
  omitido usa o primeiro `initial`; inexistente, de outro workflow,
  não-inicial ou sem coluna → 422 `field: 'statusId'`; `columnId` forjado é
  ignorado e a coluna é derivada de `column.statusIds`. Nenhuma issue é
  gravada nos casos rejeitados e o id seguinte não é consumido. `client.js`
  anexa `status`/`field` ao `Error` e a UI mapeia o erro por campo
  (`aria-invalid`, `aria-describedby`, `role="alert"`, mensagens em pt-BR),
  com foco inicial no summary e fechamento só no sucesso (§5–§7 do guia
  `docs/KAN-94-guide.md`). Barra: `node --test` 89/89, vitest 79/79, build ok
  e E2E 27/27 em porta livre reproduzidos na revisão em worktree limpo. PR
  #103, release `v0.19.0`. Dívidas registradas (guia §12): branch `summary`
  ausente no `applyFailure`, backend PHP real e `PATCH` ainda aceitam status
  arbitrário, `typeId` não validado contra o workflow, escrita de `db.json`
  não atômica; os testes BDD/node:test/vitest/Cypress do contrato (§8) seguem
  nas subtarefas KAN-122/123. No re-teste, a corrida do default (modal aberto
  durante o `GET /api/workspace` nascia sem status) foi corrigida no PR #114:
  `watch(initialStatuses)` em `BoardPage.vue` reaplica o primeiro `initial`
  quando o valor corrente deixa de ser inicial válido, sem sobrescrever a
  escolha do usuário e mantendo o bloqueio sem `initial`; spec nova
  `board-new-task-default-status.spec.js` (3/3). Barra final: vitest 108/108,
  `node --test` 128/128, build ok e E2E 47/47 na `main` reconciliada
  (`8978e80`); release `v0.27.0`.
- **Limites de descrição e comentário no protótipo (KAN-93, produção):** o
  fake backend passa a exigir o contrato do canal dos agentes: descrição ≤4095
  e comentário ≤1023 code points (`Array.from`, equivalente a `mb_strlen`),
  via o módulo único `frontend/shared/limits.js` (constantes e helpers
  `countCharacters`/`warningThreshold`/`nearLimit`/`limitExceeded`/`limitError`,
  sem duplicação em bundle, backend nem testes). Validar acontece **antes** de
  qualquer mutação em `POST /issues` e `POST .../comments` (o `PATCH` valida
  após o 404): acima do limite → `422 { error, field }` com a mensagem pt-BR
  do contrato (`A descrição excede o limite de 4095 caracteres (4096).`), sem
  gravar parcial e sem consumir id. A UI ganha contador `x/limite`, aviso
  informativo aos 90% (`Math.ceil(limite * 0.9)` = 3686/921), erro acima do
  limite, `Save`/`Create`/`Comment` desabilitados e `aria-describedby`/
  `aria-invalid` nos três campos (criação, edição e comentário), com o novo
  componente `FieldLimit.vue`; a edição de descrição no painel usa
  `Save`/`Cancel`, e cancelar descarta. O item legado acima dos limites
  (fixture `KANB-7`, só em `createSeed()`) é exibido integralmente, sem
  truncar. Contrato: `docs/KAN-93-guide.md` §2–§7. Provas: 16/16 nos testes
  de contrato do item, vitest 79/79, build ok e CI verde no head
  reconciliado com `main` (PR #110, release `v0.26.0`).
- **Limites: testes BDD, node:test e vitest (KAN-118):** a suíte do contrato
  de limites (§8 do guia `docs/KAN-93-guide.md`) fechada em três arquivos
  `node:test` (`limits.test.mjs`, `limits-backend.test.mjs`,
  `client-error.test.mjs`: 30/30) e dois specs vitest (`field-limit.spec.js`,
  `issue-limits.spec.js`: 15/15), com `test:unit` 18 arquivos / 200 testes
  verdes. Os cenários de 90% verificam o aviso a partir de 3686/921, o
  contrato BDD congelado de `description-comment-limits.feature` segue
  intacto (hash igual ao de `main`) e `test-kan-94.md` fora de escopo foi
  removido. PR #147, release `v0.30.1`.
- **Abas Comments | History com timeline mock no painel (KAN-90-A/KAN-113,
  produção):** o painel de issue do protótipo ganha abas **Comments** (padrão)
  e **History** conforme o guia `docs/KAN-90-guide.md` (§3–§6): feature
  `history` em `simulation.js` (seleto `Prototype` via `boardStore.simulated`),
  seed com `history` cobrindo os cinco tipos de evento (`created`, `status`,
  `assignee`, `summary`, `description`), rework em KANB-2 e `[]` em KANB-6/7,
  fake backend com backfill aditivo idempotente em `load()` e `history: []` no
  `POST /issues` (move/PATCH/comentários não geram eventos), novo componente
  `IssueHistory.vue` (ordem cronológica inversa sem mutar a prop, ícones por
  tipo via `AppIcon`, nomes resolvidos no store com fallback bruto/`—`/
  `Unassigned`, `<time :datetime>`, empty state `No history yet`, compatível
  com o contrato real sem `type`) e abas acessíveis em `IssuePanel.vue`
  (tablist/tab/tabpanel, roving tabindex, setas com wrap, Home/End,
  Enter/Espaço, foco em `nextTick`, contador apenas em Comments, `v-show`
  nos dois painéis). Guia commitado junto. Barra de revisão: vitest 121/121,
  `node --test` 138/138, build ok, E2E completo 47/47 na `main` reconciliada.
  PR #129, release `v0.29.0`.
- **Guia do rail colapsado publicado (KAN-91/KAN-112, documentação):** a
  sub-task KAN-112 existiu para liberar a produção da KAN-91, que morava sem
  commit no worktree de um agente. O primeiro PR (#128) foi rejeitado: além de
  CI vermelho (`sidebar-navigation`/`sidebar-rail-active`), o diff apagava
  produção já mesclada (`.topbar-actions`, `.console-toggle`,
  `body.drawer-locked`, `aria-label`s). Na revisão do PR #139 constatou-se que
  `navigation.js`, `AppSidebar.vue` e `main.scss` já estavam idênticos na
  `main` (entrados no PR #118 e no QA do v0.25.0); o PR levou só o guia
  `docs/KAN-83-guide.md` (+213/−0, arquivo novo), com objetivo, decisões e o
  contrato de nomes do rail. Revisão: diff só de `.md`; os 16 vitest dos specs
  que reprovaram o #128 passam (`sidebar-navigation` +
  `sidebar-rail-active`); a falha de CI de `run-e2e-exit-code.test.mjs` é
  infra (dev server morre com `JSON.parse` de JSON vazio no runner), sem
  relação com o diff. Ressalva registrada na KAN-92: o guia escreve
  `showRail = computed(() => props.collapsed && !props.isMobile)`, a produção
  tem `Boolean(...)` em volta — equivalentes, reconciliar no texto quando a
  KAN-92 tocar os testes. PR #139, release `v0.29.1`.
- Pendências do copilot seguem fora desta rodada: chat real (mock × provedor),
  persistência da conversa e comportamento ao colapsar; lembrar a escolha
  expandido/colapsado entre recarregamentos é item futuro de UI.
- **E2E e auditoria visual da aba History (KAN-114, verificação):** novo spec
  `frontend/cypress/e2e/issue-history.cy.js` com `collectErrors` e sem
  `vite-error-overlay`, cobrindo o guia `docs/KAN-90-guide.md` §7.4–§7.5:
  aba padrão Comments com contador restrito, timeline de KANB-1/KANB-2 com os
  cinco tipos de evento (ícone, autor, `<time datetime>`, resumo) em ordem
  inversa, History sem comentários, empty state em KANB-6 e reset ao trocar de
  issue, teclado completo (setas com wrap, Home/End, Enter/Espaço, roving
  tabindex, `aria-selected`/`aria-controls`, foco visível) e selo
  `Prototype` sob `?sim=1` oculto sob `?sim=0`. `icon-standard.cy.js` não
  existe nesta base (Saída 2 legalmente vazia), auditoria visual em
  1280×800/1024px dark e light com 12 screenshots em
  `var/kanban.local/artifacts/kan-114-auditoria-visual/`, e suíte E2E 52/53
  verde com a única falha (`sidebar-rail`) sendo defeito pré-existente
  reproduzido na base pura e atribuído à KAN-91/PR #128. Ressalvas não
  bloqueantes registradas: segmento `?sim=0` sem asserção de zero erros e
  quirk `{end}`/`{home}` coberto via `cy.focused`. PR #140, release `v0.29.2`.
- **PATCH `/api/user` parcial e idempotente + backfill do perfil (KAN-129):** o
  §3 de `docs/KAN-126-guide.md` pousou no fake backend. Merge por campo com
  validação integral **antes** de qualquer mutação (`userChanges` →
  `applyUserChanges`): ausente preserva, `avatar: null` remove a foto,
  `displayName` mantém trim ≤ 40 (422), `avatarColor` exige hex
  `#[0-9a-fA-F]{6}` (422) e `avatar` aceita `null` ou data URL
  `data:image/(png|jpeg|webp);base64,` ≤ 512 KiB (422); PATCH repetido produz o
  mesmo estado. GET/PATCH respondem `{ login, displayName, avatar, avatarColor }`;
  boot com backfill `??=` (banco legado ganha `avatar: null` e cor
  determinística) e seed já nasce completo. Nova lib pura
  `frontend/src/lib/avatar-color.js` (`avatarColorFor`, FNV-1a + paleta de 8
  cores, §4.2). Cobertura: `profile-contract.test.mjs` (novo) mais os ajustes
  em `profile-menu`/`seed-backfill`; 20 testes de perfil verdes, build verde,
  e as 6 falhas restantes da suíte são pré-existentes em `main` (ambiente).
  Escopo respeitado: nada em `ProfilePage.vue`, `session.js` ou KAN-108. A
  revisão registrou três imprecisões não bloqueantes (cheque inalcançável de
  `Buffer.from` em `avatarProblem`, `String(value)` antes do `typeof` e o
  `'#e5484d'` hardcode no seed). PR #143, release `v0.30.0`.
- **Diálogos acessíveis: serviço central de confirm/alert e toasts (KAN-127):**
  o §7 de `docs/KAN-127-guide.md` substituiu os diálogos nativos da SPA.
  `store/dialog.js` promise-based (`confirm` → bool, `alert` → void, uma
  caixa por vez) com `DialogHost.vue` global no `App.vue` (`alertdialog` em
  danger, foco inicial no Cancelar, Escape/Enter/Tab, `inert` no `#app` +
  scroll lock, retorno de foco ao gatilho) e toasts não bloqueantes
  `store/toast.js` + `ToastHost.vue` (máx. 3, 4s/6s, `aria-live`,
  `role=alert` no erro, tokens `--success`/`--warning`). Os quatro
  `window.confirm` (CrudPage, AgentsPage, IssueTypesPage, AppSidebar) migraram
  para `dialog.confirm` danger com toasts de sucesso/erro: erro com `field`
  segue inline, erro sem campo (rede/422 genérico) vai para `toast.error`.
  Zero diálogos nativos em `src/**`. Verificação independente da revisão:
  build, vitest 162/162, node:test e varredura PCRE2 verdes. Observações em
  dívida: falso positivo da guarda da KAN-138 nas definições de
  `store/dialog.js` e rótulo default do `alert` ambíguo no guia §3. PR #138,
  release `v0.31.0`.
- **Perfil, Fase 1: foto como rascunho, `updateProfile` e Save com estados
  (KAN-131):** o §5 de `docs/KAN-126-guide.md` pousou no front-end.
  `store/session.js` ganhou `avatar`/`avatarColor` no estado e trocou `rename()`
  por `updateProfile({ displayName, avatar })` (avatar `null` remove, ausente
  preserva), com `load()` sincronizando tudo; `ProfilePage.vue` passou a editar
  um rascunho local `{ displayName, avatar }` — seleção de arquivo valida por
  `imageFileError` (mensagem inline, sem diálogo nativo), `normalizeImageFile`
  vira draft, Remover marca `draft.avatar = null` — e o Save emite **um único
  PATCH** de nome+foto, com botão desabilitado sem alterações (`null` explícito
  conta), loading e sucesso `role="status"`; preview circular via `<Avatar>` com
  fallback na inicial sobre `session.avatarColor` (determinística pelo login);
  login readonly e os quatro `data-test` do micro-contrato. Verificação da
  revisão: node:test de perfil 29/29, vitest 185/185 e build verdes no worktree
  do head; CI vermelho (`legacy-db-boot.cy.js`) é dívida pré-existente da
  `main` (KAN-129). Ressalvas não bloqueantes: erro do PATCH aparece no hint do
  arquivo (migra para toasts na Fase 2) e a branch empilhada repetia fundações
  já na `main`. PR #146, release `v0.32.0`.
- **KAN-125** — corrida no harness de contratos E2E (`run-e2e.sh`) extinta:
  dados efêmeros por invocação (`E2E_DATA_FILE`, atômicos, nunca tocam nem
  removem o db do desenvolvedor), porta livre em todos os contratos (KAN-144) e
  Xvfb próprio por invocação. Verificação da revisão: worktree do head com
  `node --test tests/*.test.mjs` duas rodadas consecutivas, 188/188 nas duas,
  com a 5173 ocupada; repro no `main` puro provou que a vermelhidão residual do
  Browser E2E (`description-comment-limits`, `legacy-db-boot`) é dívida
  anterior, fora do escopo (candidata a item próprio). PR #151, release
  `v0.32.1`.
- **KAN-153** — dívidas de E2E da `main` fechadas: o spec de limites mira o
  composer (`.comments-body > button.button`) em vez do rótulo da aba
  `Comments`; o boot legado aceita `avatar`/`avatarColor` no PATCH; o
  `run-e2e.sh` calcula o teto por spec (`120 + 60 × specs`, 960s hoje); e
  `fake-backend.load()` refaz o seed quando `.data/db.json` está vazio ou
  truncado — o Vite não cai mais no boot (5173) depois de uma rodada
  interrompida. Verificação: Chrome 85/85, vitest 200/200, `node --test`
  197/197 e build verdes. PR #154.
- **KAN-126 — perfil completo:** a pilha pousou na ordem revisada — fundação
  de diálogos/toasts (refundação KAN-127, #145), Fase 2 (guarda one-shot +
  toasts, #152), Fase 3 (avatar no topbar, #153), contrato/BDD (KAN-132,
  #144) e E2E (KAN-133, #159, alinhamento #160). Verificação da revisão em
  worktree da árvore integrada: build verde, vitest 210/210, `node --test`
  206/206, drawer-a11y 17/17 e E2E 91/91. Entregas duplicadas fechadas sem
  merge (#141, #142, #148, #156, #157; o spec da #158 foi superseded pela
  #159). Veredicto: aprovado; release `v0.33.0`.

- **KAN-128 — avatares circulares e comentários com autor:** a fundação pousou
  via PR #150 (`lib/comment-author.js` com `resolveAuthor` — agente por id →
  nome, user por `login`/`displayName`, desconhecido com cor determinística —,
  `formatCommentTimestamp` em `dd/mm/aaaa hh:mm` e `addComment` enviando
  `session.login`); a aplicação (PR #168) consome o `Avatar` compartilhado da
  KAN-108 no assignee do card (fallback `?` com cor neutra quando sem
  assignee) e nos comentários (miniatura + nome + timestamp). Seed com os
  quatro ramos do mapeamento (`admin`, `vivy`, `Kaio`, `user`) e retrato data
  URL de 1 KB. Verificação: CI verde (vitest 210/210, `node --test` 206/206,
  build). E2E/visual dos comentários nas ondas 3–4 (KAN-142/143). Veredicto:
  aprovado (r6); release `v0.34.0`.

- **KAN-139 — E2E dos diálogos e toasts (§8.4 do guia KAN-127):** spec
  `cypress/e2e/dialogs.cy.js` prova os fluxos no navegador real: exclusão
  cancelada via Cancelar e Escape (registro permanece) e confirmada via Enter
  e clique (registro some + toast de sucesso com classe de variante correta),
  nas quatro superfícies migradas (CrudPage via roles e issue-types,
  AgentsPage, IssueTypesPage e AppSidebar com sources criadas via API). Foco
  inicial no Cancelar do danger (`:focus` asserido), retorno ao gatilho após
  fechar, `alertdialog`/`aria-modal`/`aria-labelledby` no confirm, scrim
  clicado sem fechar, toast de erro com `role="alert"` (intercept 500) e
  overlay com card menor que a viewport em 390×844. Lição registrada: a
  primeira revisão reprovou por asserção de classe inexistente
  (`toast-success`) após a base evoluir (fix #168 renomeou a classe) — asserção
  sempre contra a integração, nunca contra a base antiga. Verificação: CI
  verde (run 39686755172) e revisão independente contra a `main`, asserções
  confrontadas com `ToastHost.vue`/`DialogHost.vue`. Veredicto: aprovado;
  PR #169, release `v0.34.1`.

- **KAN-142 — testes dos contratos de comentários (§5 do guia KAN-128):** a
  adoção dos REDs pousou via PR #175: `frontend/tests/comments.feature` (BDD
  pt-BR) com o mapa completo da autoria; `frontend/tests/comment-author.test.mjs`
  (node:test, sem DOM, determinístico) exercitando
  `resolveAuthor`/`formatCommentTimestamp` como um chamador real — ISO com `Z`
  e com offset, formato com pad 2, colisão de id vencendo login, desconhecido
  com cor determinística estável; `frontend/tests/comments.spec.js` (vitest) montando
  `IssuePanel` e `IssueCard` via `Avatar` com store mockado e contrato do POST
  com store real (`vi.importActual`) e `api` espiado, sem rede. Imprecisão
  corrigida na adoção: o escopo pedia card **e** painel via `Avatar`, mas só o
  painel estava montado — dois testes do `IssueCard` (tooltip/`aria-label` do
  agente; fallback neutro `Unassigned`/`?` sem assignee) entraram no commit
  36db13d. Branch sincronizado com a onda 2 (classe `comment-avatar` do PR
  #176 fecha o último RED sem editar teste). Nenhuma mudança de produção,
  nenhuma dependência nova; asserts de timestamp validam formato, nunca valor
  absoluto (sem flake de fuso). Verificação: vitest 247/247 (25 arquivos),
  `node --test` 11/11, build verde. Veredicto: aprovado; release `v0.34.3`.

- **KAN-143 — E2E de comentários e avatares no navegador (§5 do guia KAN-128):**
  spec `frontend/cypress/e2e/comments-avatars.cy.js` (11 testes BDD em pt-BR,
  coletor `collectErrors`) prova no navegador real: avatar do card circular de
  22px com `:title`/`aria-label` do agente e fallback `Unassigned` sem
  assignee; no painel, os quatro ramos do seed — `admin` com a identidade do
  user (sem foto: inicial + cor determinística), `vivy` com a miniatura do
  data URL, `Kaio` pelo autor e o `user` antigo com inicial `U` — legenda com
  nome + `timestamp` validando apenas o formato `dd/MM/yyyy HH:mm` (sem flake
  de fuso), publicação logada interceptada (`POST` com `author: 'admin'`) com
  ordem cronológica preservada e nenhuma `<img>` quebrada (`naturalWidth >
  0`). Seed restaurado no `afterEach` e `E2E_PORT` exclusiva do harness em
  worktree. A primeira revisão reprovou o PR #178 por imprecisão de título do
  `it` (dizia 24px, o corpo media 22px — documentação executável não pode
  contradizer a própria asserção); corrigido no commit `fc36e1d` e o branch
  purgado de um relatório de trabalho staged (`ab4d1c4`). Mudança
  exclusivamente de teste, nada de produção. Verificação: bateria completa
  117/117 no head (`E2E_PORT=5197`, 19 specs, 11/11 da suíte nova). Veredicto:
  aprovado; PR #178, release `v0.34.4`.

- **KAN-110 — BDD e testes do `Avatar` e da guarda de upload (§3, §5 e §7 do
  guia KAN-89):** diff restrito a `frontend/tests/` sobre a fundação KAN-108:
  14 cenários BDD em pt-BR no `avatar.feature`; `avatar.spec.js` provando
  classes `sm`/`md`/`lg`/`ring` (anel desligado por padrão), inicial maiúscula,
  `?` para nome vazio, ARIA decorativo vs identificado, fallback em `@error` e
  troca de `image`, e contraste ≥ 4,5:1 calculado no próprio teste com
  asserção incondicional; `image-upload.spec.js` cobrindo PNG/JPEG/WebP ≤ 2 MB,
  recusas e data URL; guardas estáticas de recorte/tamanhos/seed e guarda de
  dependências por snapshot versionado (`tests/deps-baseline.json`, falha em
  adições e remoções). As rodadas 3 e 4 reprovaram o caminho por imprecisão
  evitável: guarda com lista hardcoded que não batia com o `package.json` real,
  asserção de contraste dentro de `if` com variáveis mortas, cláusulas do §7.1
  ausentes no BDD e branch baseado ~9 PRs atrás da main (o merge reverteria
  conteúdo alheio); sem PR aberto não houve o que revisar. A rodada 5 seguiu o
  caminho único: branch da main (19d1221), snapshot derivado do repositório,
  asserção incondicional e PR #180 aberto. Verificação do revisor em worktree
  limpo: vitest 247/247; `node --test` 232 com uma instabilidade pré-existente
  em `run-e2e-parallel` (infra de E2E, fora de escopo e intocada pelo PR) que
  passa isolada; prova negativa da guarda (dependência fantasma → falha).
  Veredicto: aprovado; PR #180, release `v0.34.5`.

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
