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
   - **Dívida QA conhecida (candidata a item):** os specs de browser
     `description-comment-limits` e `legacy-db-boot` falham no CI (etapa
     Browser E2E) também no `main` puro — repro de 2026-10-21 no 9418d86.
     Não é dívida da KAN-125 (PR #151).

## Active plan — productization: API-first, multi-tenant, open-warden, Cloudflare

Goal: turn the validated UX prototype into a real multi-tenant product. The
workspace is the tenant boundary; everything configurable is persisted and
served by the API; agents run on open-warden; the image runs with one
`docker run` locally and on Cloudflare Containers with an external PostgreSQL.
The console and copilot stay disconnected until their own planning round.

Order and exit criteria:

1. **P0 — tenancy and API base** — users/workspaces N:N, admin bootstrap,
   bearer sessions, tenant guard, PostgreSQL + Doctrine, dev compose,
   `bin/install`. **Done (branch `feat/p0-tenancy-api`).**
2. **P1 — workflow chain** — workspace-scoped boards, columns, statuses per
   issue type, transitions, issue types, fields per issue type, issues minimal
   create/list/move/comment; CRUD with `GET/POST/PATCH/DELETE`; frontend
   validation of those screens.
   - **P1a — workflows, issue types and fields: Done** (branch
     `feat/p1-workflow-chain`; ADR-002).
   - P1b: statuses and transitions (fixed status kind; no cross-issue-type
     transition).
   - P1c: boards and columns (board references one workflow; issues have no
     `boardId`).
   - P1d: issues (workspace prefix key sequence, move, comments, history).
3. **P2 — teams and agents** — teams, roles (unblock/interactive/filters),
   profiles, agents (identity + runtime + roles + source), sources/repositories
   (`local` for dev, `git` for cloud); full CRUD.
4. **P3 — runtime** — PHP CLI runner, `bin/kanban`, open-warden execution,
   claim/lease/reclaim, prompt composition, outcomes, triggers (spawn, voting,
   `subtasks-done`), usage/cost, logs, worktrees.
5. **P4 — frontend cutover** — login/token, workspace switcher, snapshot +
   resource writes, feature flags flipped in dependency order, fake backend out
   of production, Vite build served by PHP, real-backend E2E; console and
   copilot untouched; i18n later.
6. **P5 — Cloudflare single image** — image roles (`web`/`runner`/`all`),
   external PostgreSQL, R2 for artifacts, cron/queue-triggered runner with
   resumable leases, secrets, health, no compose in production.
7. **P6 — migration and dogfooding** — import the 161 local issues and agent
   configuration, run the project on the real board, archive `kanban.local`,
   clean historical `phrise` references.

Superseded/absorbed: the old board-driven agent-watcher plan is historical;
the open-warden runner replaces it. UX items 7–9 are folded into P4/later; the
RFC triggers are P3 and the metrics read model comes after P5.

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
   that respects the configured transitions.~~ **Done (prototype).** O tema
   escuro foi alinhado à paleta do VSCode Dark+ (KAN-8, release `v0.12.0`). A
   grade direita ganhou o painel de copilot em esqueleto, colapsável pelo menu
   superior (KAN-62, release `v0.13.0`), com a aceitação fechada pela KAN-71
   (BDD, guardas e E2E; release `v0.15.1`), e a grade inferior ganhou a fundação
   do console — container, toggle, `Ctrl+J` e CSS (KAN-65, release `v0.15.0`) —
   mais a store de mensagens, o polling idempotente e o catálogo fechado de
   comandos (KAN-64, release `v0.15.2`) e o painel de output com autoscroll
   travável, filtro por ator e pílula de não lidas (KAN-66, release `v0.16.0`)
   e o painel de input com catálogo delegado, `busy` sem reenvio e histórico
   efêmero (KAN-67, release `v0.17.0`); a KAN-18 fechou o MVE com os rótulos
   pt-BR, o `at` em ISO 8601 UTC e o poll imediato na abertura (release
   `v0.17.1`); a KAN-68 fechou a prova da grade com BDD de 16 cenários,
   integração, componentes e E2E, além do guarda de porta e do coletor de erros
   no harness (release `v0.17.2`). O chat real continua pendente.
2. ~~**Workspace tree** — the sidebar mirrors `workspaces -> boards / workflows /
   teams / sources`, with an SVG icon on every entry and menu editors.~~ **Done
   (prototype).** A sidebar ganhou um modelo único de folhas
   (`frontend/src/navigation.js`) e, colapsada em desktop, um rail de 48px só
   com folhas navegáveis (KAN-83, release `v0.18.0`); Sources continuam fora do
   rail, e a adaptação de `sidebar-collapse.cy.js` segue na sub-task de testes.
   O guia de implementação do rail (`docs/KAN-83-guide.md`, com o contrato de
   nomes e as decisões da RFC) está publicado na `main` (KAN-112, PR #139,
   release `v0.29.1`).
   O modelo ficou uniforme em `{ entity, leaves }` e o spec da fronteira de
   1024px aceita o toggle ausente do DOM em modo mobile (KAN-87 prevalece;
   KAN-91, release `v0.25.0`).
3. ~~**Workflow admin** — statuses (type, colour, roles), transitions
   (user/agent/both) and issue types (custom fields, levels), scoped to a
   workflow.~~ **Done (prototype).**
4. ~~**Team admin** — agents (name, who am I, age, marital status, roles, profile,
   3x4 portrait), roles and profiles, scoped to a team.~~ **Done (prototype).**
5. ~~**Sources** — local project directories the worker will use as `workdir`.~~
   **Done (prototype).**
6. ~~**Profile menu + session (KAN-15, MVE)** — user avatar/name in the top bar
   with a `profile`/`logout` dropdown, a profile page that edits only the display
   name, and a session gate (logout → neutral "Session ended" view).~~ **Done
   (prototype).** O backend do perfil evoluiu: `PATCH /api/user` parcial e
   idempotente com `avatar`/`avatarColor` validados antes de qualquer mutação,
   backfill no boot e lib `avatarColorFor` (KAN-129, release `v0.30.0`);
   a foto no front-end segue o guia `docs/KAN-126-guide.md`: a Fase 1 pousou —
   rascunho local de nome+foto, `updateProfile` e Save com estados
   (KAN-131, release `v0.32.0`) — e a guarda de saída one-shot com confirm
   custom, os toasts acessíveis, o avatar no topbar via `Avatar` e o E2E do
   perfil (upload, validação, remoção, save/reload, fallback e prova de
   payload) pousaram com a KAN-126 integral (release `v0.33.0`).
7. **Board polish** — open an issue on its own page; make board filters affect the
   cards; surface Experiences in the new menu; WIP hints; remove the off-canvas
   drawer from the Tab order when closed on mobile (`inert` on the `<aside>`
   while `aria-hidden`). O modal de nova task ficou em largura plena e nasceu
   validado: campos a 100%, seletor só com status `initial` e `POST /issues`
   rejeitando com 422 o que não for inicial (KAN-94, release `v0.19.0`; os
   testes do contrato seguem nas subtarefas KAN-122/123) e o default do
   status é robusto à corrida do loading (PR #114, release `v0.27.0`). O drawer da sidebar
   em tela cheia abaixo de 768px (faixa parcial de 200px em 768–1024px
   preservada) pousou com produção (KAN-95, PR #104) e suíte de testes
   BDD/integração/E2E (KAN-96, PR #116, release `v0.24.1`). A descrição e o
   comentário do protótipo agora seguem o contrato do canal dos agentes
   (≤4095/≤1023 code points): contador com aviso aos 90%, bloqueio de
   salvar/enviar acima do limite, `422 { error, field }` antes de qualquer
   mutação no fake backend e conteúdo legado exibido integralmente
   (KAN-93, PR #110, release `v0.26.0`); a suíte de testes do contrato
   (BDD/node:test/vitest) fechou com KAN-118, PR #147, release `v0.30.1`.
   O painel de issue ganhou abas
   `Comments | History` com timeline mock acessível (tablist, roving
   tabindex, setas com wrap, reset ao trocar de issue, selo `Prototype`),
   componente `IssueHistory.vue`, eventos no seed/fake backend e guia
   `docs/KAN-90-guide.md` (KAN-113, PR #129, release `v0.29.0`). A aba History
   foi verificada com o spec E2E `frontend/cypress/e2e/issue-history.cy.js`
   (KAN-114, PR #140, release `v0.29.2`). Os diálogos nativos do navegador
   saíram do código: serviço central promise-based (`store/dialog.js` +
   `DialogHost.vue`, `alertdialog` em danger, foco inicial no Cancelar,
   Escape/Enter, `inert` + scroll lock, retorno de foco) e toasts não
   bloqueantes (`store/toast.js` + `ToastHost.vue`, máx. 3, 4s/6s,
   `aria-live`); os quatro fluxos de save migram erro com campo para inline e
   erro sem campo (rede/422) para `toast.error` (KAN-127, PR #138, release
   `v0.31.0`); a guarda estática fechou em KAN-138 e o E2E dedicado em
   KAN-139 (`cypress/e2e/dialogs.cy.js`, PR #169, release `v0.34.1`). Os
   avatares ficaram circulares e os comentários ganharam autor: assignee do
   card via o `Avatar` compartilhado (fallback `?`/cor neutra sem assignee) e
   comentários com miniatura + nome + timestamp absoluto, mapeando o `author`
   pela fundação `lib/comment-author.js` — agente por id → nome, user por
   `login`/`displayName`, desconhecido com cor determinística — e o POST
   enviando `session.login` (KAN-128, PRs #150/#168, release `v0.34.0`); os
   contratos de teste fecharam a rodada: BDD (`comments.feature`, pt-BR),
   node:test sem DOM sobre a lib e vitest montando `IssuePanel`/`IssueCard`
   via `Avatar` com o POST espiado (KAN-142, PR #175, release `v0.34.3`); o
   E2E fechou a rodada no navegador real: 11 testes BDD em pt-BR cobrindo os
   quatro ramos do seed, o fallback `Unassigned`, o formato do timestamp, a
   publicação logada com ordem preservada e nenhuma imagem quebrada (KAN-143,
   PR #178, release `v0.34.4`); o contrato de testes do `Avatar` fechou o
   circuito do KAN-89: `avatar.feature` com 14 cenários em pt-BR, spec provando
   ARIA e contraste ≥ 4,5:1 calculado no teste, guardas estáticas de
   recorte/tamanhos/seed e guarda de dependências por snapshot versionado
   (KAN-110, PR #180, release `v0.34.5`).
8. **i18n** — the UI starts in English; localisation comes after the flow is set.
9. **Wire the real backend** — replace the fake JSON backend with the PHP REST API
   feature by feature, flipping the per-feature prototype flags.

## Active plan — model performance & pipeline triggers (RFC)

Goal: measure per-model quality and cost; transitions become **pipeline
triggers** (not agents). Design and decisions are **formalised** in
`docs/RFC-model-metrics.md`. Implementation order (RFC §11):

1. ~~Decisions formalised~~ **Done.**
2. ~~Runs ledger~~ **Done** (agent-watcher `watcher.run_agent` → `opencode run
   --format json` + `sessionID`; PRs #3/#4).
3. ~~Infractions + adjudication~~ **Done (core)** (multi-agent PR #30).
4. ~~Normalise lineage / spawn~~ **Done** (multi-agent PR #32).
5. Implement the triggers (spawn, voting, `subtasks-done`) — **next**.
6. Semantic layer + metrics read model; update `var/kanban.local/bin/monitor`.
