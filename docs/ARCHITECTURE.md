# ARCHITECTURE

## Overview

`multi-agent` is an OTAKU Application repository. Its heart is a configurable
kanban board that mirrors the agent-watcher data model (`column`, `agent`,
`worker`, `blocked`, `blocked_by`, comments, stage history) so that agents can be
pointed at it.

## Frontend (Vue SPA)

`frontend/` is a Vue 3 + Vite SPA, independent from the PHP backend and backed by
a fake JSON API (`plugin/fake-backend.js`) until each screen's real endpoints
exist. `src/App.vue` is the shell: the left `.sidebar`, the central `.content`
(topbar + `.main`), and the independent panels. The right panel is
`AppCopilot.vue` (`.copilot-panel`), a desktop-only skeleton mounted as a
sibling of `.content` and always expanded on load; `.copilot-toggle` in the top
bar and `Ctrl+Alt+B` hide or restore it without touching the other panels. It
performs no network call and keeps no conversation state. The bottom grid is
`AppConsole.vue` (`#app-console.console`), rendered inside `.content` after
`.main`: `App.vue` owns `consoleOpen`/`consoleHeight`, the `.console-toggle`
button and the `Ctrl+J`/`Cmd+J` shortcut, while the panel handles pointer and
keyboard resizing and mounts the output/input panels: `ConsoleOutput.vue`
renders `consoleStore` as a `role="log"` stream, and `ConsoleInput.vue`
forwards the typed text to `store/commands.js::runCommand` (closed catalog),
disabling input and submit while `consoleStore.state.busy` is set. `ConsoleOutput.vue`
renders the log with a 24px autoscroll anchor, a render-only actor filter and a
jump pill for unread messages; message text is always interpolated; the console
labels follow the board's pt-BR contract. `store/console.js` owns
the message log and the 5s polling of the existing `GET /api/issues` (started
with an immediate tick so the grid opens filled), normalising every message
timestamp to ISO 8601 UTC, adapting
both comment payloads (camelCase fake backend and PHP presenter), deduplicating
by comment key and pausing while the tab is hidden; `store/commands.js` executes
the closed command catalog locally against `boardStore`. The grid is proven the
way the user uses it: `tests/console.feature` is the single source of acceptance
(16 scenarios, pt-BR) mapped one-to-one onto `tests/console.test.mjs` (node:test
integration), `tests/console.spec.js` and `tests/console-output.spec.js`
(vitest components) and `cypress/e2e/console.cy.js` (real browser). The E2E
harness (`run-e2e.sh`) aborts with status 1 when the requested port is busy or
the dev server dies, so the suite never measures a foreign Vite: each
invocation selects its own port (`E2E_PORT`) and its own data file
(`E2E_DATA_FILE`, written/restored atomically; the developer's
`.data/db.json` is never touched and the ephemeral file is never removed),
and with no `DISPLAY` available it raises its own Xvfb display derived from
the port — contract tests survive `node --test` parallelism with the 5173
busy (KAN-125/144/145). Também
`cypress/support/e2e.js` fails any scenario whose page logs an error, an
unhandled rejection or `console.error`. The sidebar navigation comes from
`src/navigation.js`, a pure model: `sidebarNavigation(store, workspaceId)`
builds every leaf (boards, workflow pages, team pages, Experiences) as uniform
entries `{ entity, leaves }` (boards wrap one leaf; Experiences é um entry com
entity sintético) and
`railSections(navigation)` picks the non-empty groups, so the expanded tree and
the collapsed rail (`nav.nav.rail` in `AppSidebar.vue`) render from the same
source; with no workspace the model is empty. The collapsed rail keeps only
navigable leaves (`a.leaf.rail-leaf`, 36×36, 18px icon, `title`/`aria-label`
from the unique `railTitle`); brand, group headers, badges, edit buttons and
Sources stay outside it. O contrato de implementação do rail está publicado em
`docs/KAN-83-guide.md` (PR #139, release `v0.29.1`). O modal de nova task (`BoardPage.vue`) cria issues
pela porta da frente: o seletor de status lista apenas os `initial` do
workflow (default: o primeiro; sem `initial`, o modal bloqueia com aviso em
pt-BR) e o `POST /api/issues` valida tudo **antes** de qualquer mutação —
`boardId` inválido/ausente, status não-inicial, de outro workflow, inexistente
ou sem coluna respondem 422 `{ error, field }` (`boardId`/`statusId`), a
coluna é derivada de `column.statusIds` e um `columnId` forjado é ignorado.
`src/api/client.js` anexa `status`/`field` ao `Error`; a UI nunca faz parsing
da mensagem. Um `watch` em `initialStatuses` reaplica o primeiro `initial`
quando a lista chega com o modal já aberto (corrida do loading), preservando
a escolha válida do usuário. Contrato de aceite: `docs/KAN-94-guide.md`.

O painel de issue (`IssuePanel.vue`) apresenta o conteúdo em duas abas
acessíveis — **Comments** (padrão, com contador) e **History** — com roving
tabindex, setas ←/→ com wrap, Home/End, Enter/Espaço, foco em `nextTick`,
`v-show` em ambos os painéis e reset a Comments ao trocar de issue. A aba
History exibe o componente `IssueHistory.vue`, que ordena os eventos em
cronologia inversa sem mutar a prop, resolve nomes de status/agente no store
(com fallbacks bruto/`—`/`Unassigned`), renderiza `<time :datetime>` e um
empty state `No history yet`; um selo `Prototype` depende de
`boardStore.simulated('history')`. O seed e o fake backend fornecem os
`boardStore.simulated('history')`. O seed e o fake backend fornecem os
eventos mock (`docs/KAN-90-guide.md`, KAN-113). A cobertura E2E dessa junção
(prova teclado/ARIA, empty state, reset e selo `?sim`) está em
`cypress/e2e/issue-history.cy.js` (KAN-114, PR #140, release `v0.29.2`).

Os limites do canal dos agentes vivem em `frontend/shared/limits.js`, módulo
ESM puro sem DOM nem dependência de Vue — importável pelo bundle, pelo fake
backend e por `node:test`/vitest: `DESCRIPTION_LIMIT = 4095`,
`COMMENT_LIMIT = 1023` (code points, `Array.from` ≃ `mb_strlen`) e os helpers
`countCharacters`/`warningThreshold`/`nearLimit`/`limitExceeded`/`limitError`.
O fake backend valida `POST /issues`, `POST .../comments` e o `PATCH` ( após o
404) **antes** de qualquer mutação e responde `422 { error, field }` com a
mensagem pt-BR do contrato; nos três campos (criação, edição da descrição e
comentário), `FieldLimit.vue` rende contador `x/limite`, aviso aos 90% e
erro, e o campo liga-se por `aria-describedby`/`aria-invalid`, com
`Save`/`Create`/`Comment` desabilitados acima do limite. Conteúdo legado
(fixture `KANB-7`, só em `createSeed()`) é exibido integralmente. Contrato de
aceite: `docs/KAN-93-guide.md`. A cobertura: BDD congelado em
`tests/description-comment-limits.feature`, contrato em `node:test`
(`limits.test.mjs`, `limits-backend.test.mjs`, `client-error.test.mjs`) e UI
em vitest (`field-limit.spec.js`, `issue-limits.spec.js`) — KAN-118, PR #147,
release `v0.30.1`.

O perfil do usuário é servido pelo fake backend em `GET/PATCH /api/user` com
resposta única de quatro campos `{ login, displayName, avatar, avatarColor }`
(`avatar: null` quando sem foto). O `PATCH` é **parcial e idempotente**: campo
ausente preserva, `avatar: null` remove a foto, `displayName` mantém trim ≤ 40
(422), `avatarColor` exige hex `#[0-9a-fA-F]{6}` (422) e `avatar` aceita só
`null` ou data URL `data:image/(png|jpeg|webp);base64,` com base64 ≤ 512 KiB
(422). Toda a validação (`userChanges`) precede qualquer mutação
(`applyUserChanges`) e o boot faz backfill com `??=` (`avatar: null`,
`avatarColor = avatarColorFor(login)`), lib pura em
`frontend/src/lib/avatar-color.js` (FNV-1a sobre o login, paleta fixa de 8
cores). Contrato de aceite: `docs/KAN-126-guide.md` §3 (KAN-129). No
front-end, o estado da sessão (`store/session.js`) carrega `avatar` e
`avatarColor` e expõe `updateProfile({ displayName, avatar })` — campo
ausente preserva, `avatar: null` remove — no lugar do antigo `rename()`. A
página de perfil (`ProfilePage.vue`) edita um rascunho local `{ displayName,
avatar }` e emite **um único PATCH** de nome+foto no Save, com preview
circular via `<Avatar>` e fallback na inicial sobre `avatarColor`
(Contrato de aceite: `docs/KAN-126-guide.md` §5, KAN-131). A Fase 2 removeu o
botão voltar e pôs a guarda de saída one-shot (`composables/useDirtyGuard.js`):
navegar com pendências abre o confirm custom `Discard changes?` com foco
inicial no cancelar — fundação de diálogos/toasts da KAN-127 (`store/dialog.js`
+ `DialogHost.vue`, `store/toast.js` + `ToastHost.vue`; refundação pousada via
PR #145) — e o feedback do save e das fotos rejeitadas é por toast, sem
`alert`/`confirm` nativos (KAN-134). A Fase 3 pinta a identidade do usuário no
`ProfileMenu` via o `Avatar` compartilhado, size `sm` (KAN-135). O E2E
(`cypress/e2e/profile-avatar.cy.js`) cobre upload, validação, remoção,
save/reload, fallback e a prova de payload — data URL enviado menor que o
arquivo original (KAN-133).

O drawer da sidebar tem duas faixas governadas por um único corte: abaixo de
768px ele ocupa a tela (`inset: 0; width: 100%`, sem `100vw`), trava o scroll
pela classe `body.drawer-locked` e expõe o botão dedicado
`button.sidebar-close`; entre 768px e 1024px permanece o drawer parcial de
200px com scrim; acima de 1024px a sidebar desktop não muda. O corte vive só no
token `$mobile-breakpoint: 768px` (exportado como `--breakpoint-mobile`) e é
consumido pelo SCSS como `max-width: $mobile-breakpoint - 1` e pelo JS via
`mediaQueryFor('--breakpoint-mobile', -1)`. O contrato é provado em três
nívels: BDD em pt-BR (`tests/mobile-drawer-fullscreen.feature`), contrato na
fonte e integração (`tests/mobile-drawer-fullscreen.test.mjs`/`.spec.js`) e
navegador real (`cypress/e2e/mobile-drawer-fullscreen.cy.js`).

Os diálogos da SPA são centralizados: `store/dialog.js` expõe a API
promise-based (`confirm` → bool, `alert` → void, uma caixa por vez) e
`DialogHost.vue` — montado no `App.vue` junto de `ToastHost.vue` — renderiza o
modal acessível (`role=dialog`/`alertdialog` em danger, `aria-modal`, foco
inicial no Cancelar, Escape/Enter/Tab, `inert` no `#app` + scroll lock e
retorno de foco ao gatilho). `store/toast.js` + `ToastHost.vue` entregam
notificações não bloqueantes (máx. 3 simultâneas, 4s/6s, `aria-live`,
`role=alert` no erro, tokens `--success`/`--warning`). Nenhum fluxo usa
`window.confirm`/`alert`/`prompt`: os quatro saves (CrudPage, AgentsPage,
IssueTypesPage, AppSidebar) chamam `dialog.confirm` danger e roteiam o erro
pelo `problem.field` do `client.js` — com campo, inline; sem campo (rede/422
genérico), `toast.error` (KAN-127, PR #138, release `v0.31.0`).

## Layers

```
public/index.php          composition root + top-level routing
        │
        ▼
Interface/Http            BoardController, WorkflowController, AgentController,
                          AuthController, ChatController, *Presenter
        │
        ▼
Application               use cases (items, workflow config, agents, chat)
        │
        ▼
Domain                    Workflow (Board, Column), Agent, Board (Item, ItemEvent),
                          Chat; contracts
        ▲
        │
Infrastructure            Sqlite/* (persistence), JsonChatRepository,
                          PasswordAuthenticator, HttpChatAgent
```

Dependencies point inward: `Interface → Application → Domain`, and
`Infrastructure → Domain` (implementing the contracts).

## Domain packages

- `Domain/Workflow` — `Board` (settings), `Column` (ordered stage with a `role`
  and its allowed `Transitions`), `ColumnId`, `Position`, `ColumnCollection` and
  `Transitions`. The workflow is data, not an enum. The `role` binds a stage to
  the agent responsible for it; `Transitions` is a first-class collection of the
  allowed target columns.
- `Domain/Agent` — `Agent` (registry entry: identity + runtime spec `profile`,
  `model`, `fallback_model`, `interval`, `workspace`) and `AgentId`.
- `Domain/Board` — `Item` (aggregate: column, agent, worker, blocked, comments,
  timestamps), `ItemEvent` (a stage transition) and `ItemId`. `item.role` is a
  manual override; the stage's role lives on the column.
- `Domain/Chat` — `Conversation`, `Message`, `MessageRole`.
- `Domain/Contract` — repository and gateway interfaces.

## Persistence (SQLite)

`Infrastructure/Persistence/Sqlite/SqliteDatabase` opens the database, applies
`Schema` (DDL) and `Seed` (default board, 9 columns and 7 agents). Repositories
(`SqliteItemRepository`, `SqliteColumnRepository`, `SqliteAgentRegistry`,
`SqliteBoardSettings`, `SqliteItemEventLog`) implement the domain contracts;
mappers translate rows to entities. Columns and agents are referenced by slug,
which keeps the REST contract (`status` = column, `role` = agent) stable for the
agents. Columns also carry a nullable `role_id` (the agent that owns the stage)
and their allowed `column_transitions`; `Schema` migrates existing databases by
adding `role_id` when missing. `Seed` runs agents before columns so the seeded
roles resolve, and wires the default linear transitions.

## Data flow (move an item)

1. `BoardController::update` parses the PSR-7 request body.
2. `UpdateItem` loads the `Item`, applies `ItemChanges`, saves it and, when the
   column changed, appends an `ItemEvent` (from → to, actor, timestamp).
3. `SqliteItemRepository::save` and `SqliteItemEventLog::record` persist both.

## Advancing an item

`POST /api/items/{id}/advance` goes through `AdvanceItem`: it resolves the target
from the current column's `Transitions` (the requested `to`, or the sole target),
rejects moves the workflow does not allow (`InvalidTransitionException` → `409`),
moves the item, assigns the destination column's `role` and records the
`ItemEvent`.

## Visibility

Each `Item` exposes `entered_at` (the last stage transition). The UI compares it
to the board's `aging_hours` to flag aged cards (bottleneck signal); the full
`ItemEvent` history is returned on the item detail endpoint for per-stage
analysis.

## Authentication

A single shared secret (`BOARD_PASSWORD`). The board accepts it as a Bearer
token. `PasswordAuthenticator` implements the `Authenticator` contract.
