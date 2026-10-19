# Changelog

Notable user-facing changes of `multi-agent`. Versions follow SemVer while the
API is in `0.y.z` (development); see `docs/GITFLOW.md` for the tagging and
release policy.

History before this file existed lives in the GitHub releases:
https://github.com/phprise-foundation/multi-agent/releases

## v0.15.0 — 2026-10-19

### Added

- **KAN-65** — fundação da grade inferior do console no board novo: container
  `AppConsole.vue` (`section#app-console.console`) com cabeçalho, fechar e
  handle `.console-resize` (arraste por ponteiro, setas ↑/↓ de ±16px e duplo
  clique restaurando 240px); estado `consoleOpen`/`consoleHeight` em `App.vue`
  via `--console-height`, botão `.console-toggle` ao lado do sidebar com
  `aria-expanded`/`aria-controls` e atalho `Ctrl+J`/`Cmd+J` desktop-only;
  token `--font-mono` e todas as seções `.console*` em `main.scss`, com clamp
  de 120px a 60vh e apenas tokens de tema. A fatia consolidou as versões
  provisórias de store/output/input (KAN-64/66/67), refinadas pelos PRs
  seguintes. Unit 53/53, build limpo e Cypress 25/25 (geometria e arraste
  reais). PR #77, release `v0.15.0`. O passo `Contracts` do CI segue vermelho
  por corrida de Xvfb (display :99), falha de harness pré-existente alheia a
  esta fatia.

## v0.14.0 — 2026-10-19

### Added

- **KAN-63** — ícone `console` no dicionário `paths` de `AppIcon.vue` do board
  novo: terminal com moldura, prompt e linha (`M4 5h16v14H4z`,
  `M7 9l3 3-3 3`, `M13 15h4`), no estilo dos demais glifos (`fill: none`,
  `stroke: currentColor`, `stroke-width: 1.8`, viewBox 24). Diff de uma linha,
  sem consumidor novo e sem alterar ícones existentes; rebase sobre a ponta
  pós-`copilot` (KAN-69). Vitest 36/36, build e CI verdes. PR #66, release
  `v0.14.0`.

## v0.13.0 — 2026-10-19

### Added

- **KAN-62** — grade direita do copilot no board novo (`frontend/`): painel
  esqueleto `AppCopilot.vue` irmão de `.content` (`.copilot-panel`,
  `#app-copilot`) com largura própria `--copilot-width: min(25vw, 400px)`,
  montado expandido por padrão (`copilotOpen = ref(true)`) e alternado pelo
  botão `.copilot-toggle` no menu superior (`aria-expanded`/`aria-controls`) e
  pelo atalho `Ctrl+Alt+B`/`Cmd+Alt+B`, checado antes do ramo do `Ctrl+B` para
  não disparar dois toggles. Desktop-only pelo breakpoint único
  (`--breakpoint-sidebar`; 1024px permanece mobile), com painel e símbolo fora
  do DOM em telas menores; sem rede, sem store e sem persistência (recarregar
  volta expandido). O painel de issue, o sidebar e o console mantêm o
  comportamento, aceitando apenas a compressão do board em 1024–1280px
  (decisão 6 do RFC KAN-5). Contrato em `docs/KAN-62-guide.md`; provas: guardas
  estáticos 7/7, vitest 36/36, build limpo e `copilot.cy.js` 6/6 nos viewports
  1280×900, 1920×900 e 1024×900, sem erro de runtime/console. PR #74, release
  `v0.13.0`.

## v0.12.0 — 2026-10-19

### Added

- **KAN-8** — tema escuro alinhado à paleta do VSCode Dark+ no frontend:
  9 tokens base substituídos pelos valores exatos do Dark+
  (`--bg`, `--surface`, `--surface-2`, `--surface-3`, `--border`, `--text`,
  `--muted`, `--accent`, `--accent-contrast`), com tokens novos
  `--selection`/`--button-bg` declarados no mesmo bloco escuro e 6 tokens
  `--syntax-*` na reserva para futura coloração de blocos de código. A
  `::selection` é escopada ao tema escuro, o item ativo do quadro
  (`.leaf.router-link-active`) usa o token de seleção (segundo uso do token,
  ≈5,73:1) e o botão primário usa `--button-bg` com fallback para `--accent`
  (branco sobre `#0e639c`, ≈6,40:1, ADR David). Tema claro, tokens
  `--danger`/`--warn`/`--ok`, `seed.js` e views intocados. O arnés
  `frontend/tests/dark-theme.test.mjs` agora lê `main.scss`, eliminando o
  ENOENT herdado da KAN-12. Barra de qualidade verificada de forma
  independente em revisão (worktree do commit a18ebcb): `node --test` 60/60,
  vitest 32/32, build ok. Entrega em fatia atômica por PR, release `v0.12.0`.

## v0.11.9 — 2026-10-19

### Fixed

- **KAN-60** — o harness de E2E pune a própria cegueira: o
  `frontend/run-e2e.sh` desvia toda a saída do `cypress run` para um log
  (`CYPRESS_LOG`, via `mktemp`, limpo no `cleanup`) mantendo-a visível no
  `tee`; se a rodada sai com `0` **e** o log casa com
  `no spec files( were)? found`, o harness sai com `1`. Defesa em profundidade
  do contrato KAN-59 — não depender do humor da versão do Cypress: rodar com
  `--spec` que não casa com `specPattern` nunca mais devolve sucesso, mesmo
  que uma versão futura saia com `0`. Verde segue `0` (cenário 3) e timeout
  segue `≠ 0`, como no KAN-57; sem `exit 1` fixo e sem engolir o diagnóstico.
  O contrato de teste da QA entrou commitado
  (`frontend/tests/run-e2e-nospec.{feature,test.mjs}` — 3 cenários BDD em
  português + integração `node:test`), com o caminho do script ancorado por
  `import.meta` e invocado por `bash`. O ajuste documentado do cenário 2
  (`--spec` vazio → `0`, com o `specPattern` default do Cypress 16.2.0
  aplicado) está fundamentado no STATE.md. Mudança só de harness — nenhum
  arquivo de produção do backend. Na revisão do Tech Lead a barra de
  qualidade foi reproduzida a partir de dentro de `frontend/`: `node --test`
  3/3, vitest 32/32, `npm run test:e2e` 14/14 sem erro de runtime/console.
  PR #64, release `v0.11.9`.

## v0.11.8 — 2026-10-18

### Changed

- **KAN-31** — a dívida de cobertura da suíte `sidebar-collapse` fechou no
  endereço certo: o spec novo `frontend/cypress/e2e/sidebar-collapse.cy.js`
  prova no navegador (Cypress, viewport 1280×800) que, colapsada via UI real
  (`button.sidebar-toggle`, com `.collapsed` e `aria-expanded="false"`
  asseridos), cada `.leaf .label` deixa de ser visível **com o submenu de
  Boards aberto** — pré-condição asserida, não clicada: `.open` verificado e
  coleção garantida (`length > 0`), primeiro rótulo visível antes do colapso.
  Ícones `.leaf svg` e o `title` das folhas sobrevivem, e a navegação segue
  viva: clique na folha → `#/boards/KANB`, medido por `cy.location('hash')`
  (o roteador usa hash history). Mudança só de teste (56 linhas, commit
  atômico) — nenhum comportamento de produção. Na revisão do Tech Lead o
  verde foi reproduzido e o efeito **falsificado**: com
  `.sidebar.collapsed .leaf span { display: block }` o spec falha em
  `expected '<span.label>' not to be 'visible'`; com a regra de produção,
  verde de novo — assere efeito de navegador, não `assert.match` sobre o
  source. PR #62, release `v0.11.8`.

## v0.11.7 — 2026-10-18

### Fixed

- **KAN-57** — o harness de E2E (`frontend/run-e2e.sh`, exposto como
  `npm run test:e2e`) devolvia código de saída **0 mesmo com a suíte reprovada**:
  o status do `wait` era descartado e a última atribuição (`CYPRESS_PID=""`)
  virava o último comando. O script agora captura o status do Cypress e o repassa
  com `exit` **depois** do cleanup (`trap cleanup EXIT` continua derrubando dev
  server e runner): suíte vermelha sai ≠ 0, timeout sai ≠ 0 e a rodada verde
  segue com `0` (sem `exit 1` fixo). O contrato de teste escrito pela QA no
  Refinamento entrou commitado — `frontend/tests/run-e2e-exit-code.{feature,test.mjs}`
  (BDD em pt-BR + integração `node:test`, 3/3) e as fixtures
  `frontend/cypress/harness/{failing,passing}.cy.js`, fora de `cypress/e2e/` e
  invisíveis à rodada padrão — para não morrer no próximo `git clean` (lição da
  KAN-54). Barra de qualidade: `node --test` 3/3, vitest 32/32, E2E padrão 8/8.
  PR #60, release `v0.11.7`.

## v0.11.6 — 2026-10-18

### Fixed

- **KAN-56** — o fake backend do protótipo (`frontend/plugin/fake-backend.js`)
  passou a mesclar na leitura **somente as chaves ausentes** do seed
  (`db[key] ??= seed[key]`): um `frontend/.data/db.json` criado antes da
  KAN-32 (sem a chave `user`) deixou de estourar `500` em `GET`/`PATCH`
  `/api/user` e de derrubar a sessão com `unhandledrejection` no boot. Chave
  presente nunca é sobrescrita (arrays vazios e `displayName` definido
  sobrevivem — testes dedicados em `frontend/tests/seed-backfill.test.mjs`,
  4/4); o caminho "arquivo não existe" continua criando o seed completo.
  Prova no nível da interface sobre o banco antigo:
  `frontend/cypress/e2e/legacy-db-boot.cy.js` (zero erros de runtime/console,
  login visível no menu, nome persistido após recarregar) e o harness
  `run-e2e.sh` passa a semear o db legado com backup/restore do banco do dev.
  Suíte verde: node --test 44/44, vitest 32/32, E2E 10/10. PR #61, release
  `v0.11.6`.

## v0.11.5 — 2026-10-18

### Changed

- **KAN-54** — o spec E2E do drawer (`frontend/cypress/e2e/mobile-drawer.cy.js`)
  foi pousado na árvore: vivia apenas não rastreado no worktree e morria num
  `git clean` — a lição do dark-theme repetida na camada de prova: artefato bom
  em endereço fraco. O spec prova o gesto no navegador: nasce sem `.open`,
  abre pelo acionador, fecha por scrim, ESC e troca de rota, foco devolvido ao
  acionador, viewport pinada em `760x900` e `screenshotOnRunFailure` desligado;
  comentários, títulos e mensagens de falha em pt-BR, sem tocar em asserção ou
  seletor. Mudança só de teste, sem código de produção; 3/3 verde reproduzido
  na revisão contra a `main` pós-merge. PR #57, release `v0.11.5`.

## v0.11.4 — 2026-10-18

### Fixed

- **KAN-10** — o colapso do menu lateral deixou de vazar para o drawer mobile:
  as regras `.sidebar.collapsed` passaram a viver dentro de
  `@media (min-width: $sidebar-breakpoint + 1)` e o atalho `Ctrl+B`/`Cmd+B`
  ficou inerte em origem mobile (guarda `isMobile.value` em `App.vue`), fiel à
  decisão E1 — o estado colapsado é suspenso no mobile (drawer em largura
  plena, rótulos visíveis) e restaurado ao voltar ao desktop, sem
  `localStorage` (recarregar continua expandido). Duas cercas de regressão de
  fonte novas em `frontend/tests/sidebar-collapse.test.mjs` (escopo do CSS no
  bloco desktop; guarda do atalho consultando o viewport). PR #59,
  release `v0.11.4`.

## v0.11.3 — 2026-10-18

### Changed

- **KAN-37** — suíte de aceite do menu de perfil fechada por inteiro contra a
  seção 11 de `docs/KAN-15-guide.md`, num diff só de teste (três arquivos, sem
  código de produção): o par `frontend/tests/profile-menu.{feature,test.mjs}`
  ganha os cenários de recarga de `#/profile` com o nome persistido e de
  limitação de 40 caracteres na entrada; o E2E
  (`frontend/cypress/e2e/profile-menu.cy.js`) passa a operacionalizar os
  grãos — avatar de 38px medido com `getBoundingClientRect`, viewport derivado
  de `--breakpoint-sidebar` (nunca o literal `1024`), credencial de login
  imutável reafirmada antes/depois do save e após o recarregamento, e sucesso
  afirmado pela região viva `[role="status"]`, não por string. PR #58,
  release `v0.11.3`.

## v0.11.2 — 2026-10-18

### Changed

- **KAN-53** — endurecimento do artefato de teste do drawer: a guarda em
  `frontend/tests/mobile-drawer.test.mjs:13` deixou de casar o valor literal
  `1024px` e passou a afirmar o contrato — existe uma declaração
  `$sidebar-breakpoint: <valor>;` no SCSS. `:14` e `:15` permanecem inalterados.
  Mudança só de teste, sem código de produção.

## v0.11.1 — 2026-10-18

### Changed

- **Contrato de aceite estendido (KAN-10)** — o par
  `frontend/tests/sidebar-collapse.feature` e `frontend/tests/sidebar-collapse.test.mjs`
  passou de 9 para 11 cenários e de 4 para 8 testes (foco visível, rótulos das
  folhas ocultos no estado colapsado, estado determinístico sem `localStorage` e
  breakpoint desktop consumindo `$sidebar-breakpoint + 1`); o guia
  `docs/KAN-10-guide.md` passou a ter endereço único. Mudança só de teste e
  documentação — nenhum código de produção.

## v0.11.0 — 2026-10-18

### Added

- **Profile menu + session gate (KAN-15, MVE Phase 1)** — the top bar now carries
  a user profile menu: a 38px initial avatar and a single-line truncated display
  name, with a dropdown (`profile` / `logout`). Backing it: the `session` store
  (`load`/`rename`/`signOut`/`signIn`) and the fake backend's `GET/PATCH
  /api/user` (login immutable; display name ≤40 chars). A `/profile` route edits
  only the display name. `App.vue` gates the whole shell on `session.signedIn`:
  logging out ends the session and renders a neutral "Session ended" view, and
  "Sign in again" restores the shell. Covered by vitest (`App session gate`) and
  the smoke E2E (§11 selector fix).
- **Sidebar icon glyph (KAN-41)** — the `sidebar` glyph in `AppIcon.vue`.

### Changed / Fixed

- **Id immutability (KAN-43/44/46/47)** — the persistence verb now derives from
  `__existing` (not `data.id`); the server rejects divergent ids with `409` and
  sanitises `__existing`.
- **Mobile drawer (KAN-39)** — the `open` prop no longer shadows the `open`
  function, so the drawer opens correctly on mobile.
- **E2E harness (KAN-38/52)** — Cypress foundation, `test:e2e` script, and a
  deterministic clock (support file disables CSS transitions/animations).
- **CI PR fence (KAN-45)** — automated triple-green (node test + build + e2e).
- **Vitest collection (KAN-40)** — `node:test` (`*.test.mjs`) suites excluded
  from `vitest run`.

## v0.10.2 — 2026-10-18

### Changed

- **KAN-30** — test-artifact hardening: the drawer toggle assert in
  `frontend/tests/mobile-drawer.test.mjs` now matches the specific handler
  `@click="sidebarOpen = true"`, aligned with the scrim assert's specificity;
  the obsolete `(Red Phase)` label was verified as already removed (KAN-14).
  Test-only change, no production code touched.

## v0.10.1 — 2026-10-18

### Fixed

- **KAN-29** — sidebar editors: `team` and `workflow` no longer derive the id
  slug at creation (`AppSidebar.vue`). The derivation now requires the guard
  `current.draft.__existing` — the same pattern `source` received in KAN-20 —
  so a new draft reaches `saveEntity` with an empty id and the store sends
  `POST` on creation and `PATCH` on edit. Covered 1:1 by
  `frontend/tests/entity-creation-verb.{feature,spec.js}`.
