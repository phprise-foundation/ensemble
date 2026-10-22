# Changelog

Notable user-facing changes of `multi-agent`. Versions follow SemVer while the
API is in `0.y.z` (development); see `docs/GITFLOW.md` for the tagging and
release policy.

History before this file existed lives in the GitHub releases:
https://github.com/phprise-foundation/multi-agent/releases

## v0.34.3 — 2026-10-22

### Testing

- **KAN-142 — contratos de comentários (BDD, node:test, vitest):** adoção dos
  REDs da KAN-128: `frontend/tests/comments.feature` (pt-BR) mapeando os ramos
  do contrato de autoria (agente por id/nome, user por login/displayName,
  colisão com id vencendo, desconhecido com cor determinística, timestamp
  `dd/MM/yyyy HH:mm`, POST com o login da sessão, render no card e no painel);
  `frontend/tests/comment-author.test.mjs` (node:test, sem DOM) exercitando a
  lib como um chamador real, com ISO em `Z` e com offset; e
  `frontend/tests/comments.spec.js` (vitest) montando `IssuePanel` **e**
  `IssueCard` via `Avatar` (tooltip, `aria-label`, fallback `Unassigned`)
  além do contrato do POST com store real e `api` mockado. Branch rebasado na
  onda 2; nenhum teste enfraquecido, nenhuma mudança de produção, nenhuma
  dependência nova. PR #175, release `v0.34.3`.

## v0.34.2 — 2026-10-22

### Fixed

- **KAN-141 — aplicação (onda 2) fecha a revisão:** tooltips do assignee com
  fallback (`agent?.name ?? 'Unassigned'`), cor neutra do fallback derivada de
  token (`var(--text)`) em vez de literal mágico e comentário de código removido
  do `main.scss`; classe `comment-avatar` no `Avatar` da seção Comments do
  `IssuePanel.vue` (PRs #174 e #176, release `v0.34.2`).

## v0.34.1 — 2026-10-22

### Testing

- **KAN-139 — E2E dos diálogos e toasts:** novo spec
  `frontend/cypress/e2e/dialogs.cy.js` cobrindo o §8.4 do guia
  `docs/KAN-127-guide.md`: exclusão cancelada (Cancelar/Escape) e confirmada
  (Enter/clique) nas quatro superfícies migradas (CrudPage, AgentsPage,
  IssueTypesPage, AppSidebar), foco inicial no Cancelar e retorno ao gatilho,
  `alertdialog`/`aria-modal`, scrim rejeitado no confirm, toast de erro com
  `role="alert"` e overlay medido em 390×844. PR #169, release `v0.34.1`.

## v0.34.0 — 2026-10-22

### Added

- **KAN-128 — avatares circulares e comentários com autor (PR #168).** O
  assignee do card usa o `Avatar` compartilhado da KAN-108 (circular, fallback
  inicial/cor; `?` com cor neutra quando o card não tem assignee). Os
  comentários da aba `Comments` exibem miniatura circular + nome + timestamp
  absoluto `dd/mm/aaaa hh:mm`. A autoria é resolvida pela fundação
  `frontend/src/lib/comment-author.js` (PR #150, KAN-140): agente por `id`,
  depois por `name`; user por `login`/`displayName`; autor desconhecido cai na
  inicial da string original com cor determinística (FNV-1a). O POST de
  comentários envia `session.login` em vez do literal `'user'`. O seed cobre os
  quatro ramos do mapeamento (`admin`, `vivy` por id, `Kaio` por nome, `user`)
  e inclui um retrato data URL de ~1 KB. E2E/visual dos comentários nas ondas
  seguintes (KAN-142/143).

## v0.33.0 — 2026-10-21

### Added

- **KAN-126 — perfil completo (foto do usuário, save com estados, remoção do
  voltar).** A pilha pousou na ordem revisada: fundação de diálogos e toasts
  (refundação da KAN-127, PR #145), Fase 2 — guarda de saída one-shot
  (`useDirtyGuard`) com confirm custom `Discard changes?` focado no cancelar,
  botão voltar removido e feedback do save e das fotos rejeitadas por toasts
  (PR #152), Fase 3 — `ProfileMenu` do topbar via o `Avatar` compartilhado
  (PR #153), testes de contrato/BDD/backfill (KAN-132, PR #144) e o E2E do
  perfil com prova de payload (PR #159, alinhamento de contrato no PR #160).
  Verificação da revisão na árvore integrada: build verde, vitest 210/210,
  node:test 206/206, drawer-a11y 17/17, E2E 91/91.

### Fixed

- Deduplicado o `Info` do `AppIcon.vue` (import e mapa), que quebraria o build
  na integração das duas fundações de diálogos (KAN-127/KAN-136).
- O spec do perfil pousado asservia o contrato pré-Fase 2/3 (`[role="status"]`
  persistente, avatar de 38px, confirm nativo); alinhado a toast/22px/confirm
  custom no PR #160.

## v0.32.1 — 2026-10-21

### Fixed

- **KAN-125** — corrida no harness de contratos E2E (`run-e2e.sh`) quebrava o
  CI em qualquer PR: cada invocação do harness agora usa porta livre
  (`E2E_PORT`) e arquivo de dados próprio (`E2E_DATA_FILE`, gravação/restauração
  atômicas; o `.data/db.json` do desenvolvedor nunca é tocado e o arquivo
  efêmero não é removido pelo cleanup — KAN-145), o fake-backend também respeita
  `E2E_DATA_FILE` e só persiste em mutações, e sem `DISPLAY` o Cypress roda em
  Xvfb próprio derivado da porta (sem disputa do `:99`). Esqueletos de aceite
  em repositório (`run-e2e-parallel`/`isolation`/`data-file`/`fallback` e
  `writes.cy.js`); `run-e2e-port` migrado para dados efêmeros (KAN-144). PR
  #151, release `v0.32.1`.

## v0.32.0 — 2026-10-21

### Added

- **KAN-131** — perfil, fase 1: foto como rascunho, `updateProfile` e Save com
  estados no perfil (PR #146).

## v0.31.0 — 2026-10-21

### Added

- **KAN-127** — diálogos acessíveis: serviço central promise-based
  `store/dialog.js` (`confirm` → bool, `alert` → void, uma caixa por vez) com
  `DialogHost.vue` global (`alertdialog` em danger, foco inicial no Cancelar,
  Escape/Enter/Tab, `inert` no `#app` + scroll lock, retorno de foco ao
  gatilho) e toasts não bloqueantes `store/toast.js` + `ToastHost.vue` (máx.
  3, 4s/6s, `aria-live`, `role=alert` no erro). Os quatro `window.confirm`
  nativos (CrudPage, AgentsPage, IssueTypesPage, AppSidebar) migraram para
  `dialog.confirm` danger com toasts de sucesso/erro: erro com `field` segue
  inline, erro sem campo (rede/422 genérico) vai para `toast.error`. Zero
  diálogos nativos em `src/**`. PR #138, release `v0.31.0`.

## v0.30.1 — 2026-10-21

### Testing

- **KAN-118** — limites de descrição e comentário: testes BDD, node:test e
  vitest conforme o guia `docs/KAN-93-guide.md`. Cenários de 90% verificam o
  aviso a partir de 3686/921 (não 3685/920); o contrato congelado de
  `frontend/tests/description-comment-limits.feature` preservado intacto.
  PR #147, release `v0.30.1`.

## v0.29.2 — 2026-10-20

### Testing

- **KAN-114** — E2E e auditoria visual da aba `History`: novo spec
  `frontend/cypress/e2e/issue-history.cy.js` cobrindo o guia
  `docs/KAN-90-guide.md` §7.4–§7.5 (abas padrão e contador restrito a
  Comments, cinco tipos de evento com ícone/autor/`<time datetime>`/resumo,
  ordem cronológica inversa, empty state em KANB-6, reset ao trocar de issue,
  navegação por teclado com wrap/Home/End e roving tabindex, selo
  `?sim=1`/`?sim=0` via `collectErrors` e zero erros de `vite-error-overlay`).
  Suíte E2E 52/53 com a única falha em `sidebar-rail` atribuída a defeito
  pré-existente (KAN-91/PR #128); auditoria visual em 1280×800, 1024px, dark e
  light com 12 screenshots registrados em
  `var/kanban.local/artifacts/kan-114-auditoria-visual/`. PR #140, release
  `v0.29.2`.

## v0.30.0 — 2026-10-21

### Added

- **KAN-129** — perfil no fake backend: `PATCH /api/user` parcial e idempotente
  (§3 do guia `docs/KAN-126-guide.md`). Merge por campo — ausente preserva,
  `avatar: null` remove a foto, `displayName` mantém trim ≤ 40 com 422,
  `avatarColor` exige hex `#[0-9a-fA-F]{6}` (422) e `avatar` aceita só `null`
  ou data URL `data:image/(png|jpeg|webp);base64,` com base64 ≤ 512 KiB (422).
  A validação (`userChanges`) acontece **antes** de qualquer mutação
  (`applyUserChanges`); PATCH inválido loga no console do plugin. GET e PATCH
  respondem `{ login, displayName, avatar, avatarColor }`; o boot completa
  bancos legados com `avatar ??= null` e `avatarColor ??= avatarColorFor(login)`
  e o seed nasce com os campos novos. Nova lib pura
  `frontend/src/lib/avatar-color.js` (`avatarColorFor`, FNV-1a sobre o login
  com paleta fixa de 8 cores, §4.2 do guia). Cobertura em
  `frontend/tests/profile-contract.test.mjs` (parcial, remoção, três mídias,
  idempotência, 422s na borda do limite, valida-antes-de-mutar e backfill) e
  ajustes em `profile-menu`/`seed-backfill`; `npm run build` verde e as 6
  falhas restantes da suíte são pré-existentes em `main` (ambiente). Sem
  alteração em `ProfilePage.vue`, `session.js` ou KAN-108. PR #143, release
  `v0.30.0`.

## v0.29.1 — 2026-10-20

### Documentation

- **KAN-112/91** — publicação do guia de implementação da KAN-83
  (`docs/KAN-83-guide.md`): objetivo do rail colapsado, decisões que governam a
  fatia (folha = rota navegável, Experiences no rail, fonte única de folhas,
  corte desktop, tooltip `title` + `aria-label`, rota ativa via
  `router-link-active`/`aria-current`) e o contrato de nomes compartilhado por
  guia e testes (modelo, seletores, larguras e foco). A produção já estava na
  `main`; o PR #139 levou apenas o guia (diff só de documentação). PR #139,
  release `v0.29.1`.

## v0.29.0 — 2026-10-20

### Added

- **KAN-113** — abas `Comments | History` no painel da issue com timeline mock:
  feature `history` adicionada a `frontend/src/api/simulation.js` (default
  ligado); seed com eventos de `history` cobrindo os cinco tipos
  (`created`/`status`/`assignee`/`summary`/`description`), com rework em
  KANB-2/KANB-5 e `history: []` em KANB-6/KANB-7 para o empty state; fake
  backend com backfill aditivo idempotente `issue.history ??= []` em `load()`
  e `history: []` no `POST /issues` (move/PATCH/comentários não geram
  eventos); novo componente `IssueHistory.vue` com ordenação cronológica
  inversa sem mutar a prop, ícone por tipo via `AppIcon`, resolução de
  status/agente no store com fallbacks, `<time :datetime>` e empty state
  `No history yet`; abas em `IssuePanel.vue` com ARIA completo
  (tablist/tab/tabpanel, roving tabindex, setas com wrap, Home/End,
  Enter/Espaço), reset a Comments ao trocar de issue e selo `Prototype` via
  `boardStore.simulated('history')`; estilos de abas e timeline em
  `main.scss`. Guia `docs/KAN-90-guide.md` commitado. Barra de revisão:
  vitest 121/121, `node --test` 138/138, build ok, E2E 47/47. PR #129,
  release `v0.29.0`.

## v0.27.0 — 2026-10-19

### Fixed

- **KAN-94** — corrida do default do status no modal de nova task: com o
  `GET /api/workspace` em atraso, abrir o modal antes da resposta deixava o
  seletor sem valor (`selectedIndex: -1`) mesmo após a chegada dos status.
  Um `watch` em `initialStatuses` (`BoardPage.vue`) reaplica o primeiro
  `initial` quando o valor corrente deixa de ser inicial válido, sem
  sobrescrever a escolha do usuário e mantendo o bloqueio quando o workflow
  não tem `initial`; spec nova
  `frontend/tests/board-new-task-default-status.spec.js` (3/3). Barra de
  revisão: vitest 108/108, `node --test` 128/128, build ok e E2E 47/47 na
  `main` reconciliada. PR #114, release `v0.27.0`.

## v0.26.0 — 2026-10-19

### Added

- **KAN-93** — limites de descrição e comentário no protótipo (contrato do guia
  `docs/KAN-93-guide.md` §4–§7): `frontend/shared/limits.js` é a única fonte
  das constantes (4095/1023 code points, contados em `Array.from`) e dos
  helpers `countCharacters`/`warningThreshold`/`nearLimit`/
  `limitExceeded`/`limitError`, importados pelo bundle, pelo fake backend e
  pelos testes de contrato. O fake backend valida `POST /issues` e
  `POST .../comments` — e o `PATCH` depois do 404 — **antes** de qualquer
  mutação e responde `422 { error, field }` com a mensagem do contrato em
  pt-BR. A UI (criação em `BoardPage.vue`, edição e comentário em
  `IssuePanel.vue`) ganha contador `x/limite`, aviso informativo aos 90%
  (3686/921), erro acima do limite, botões desabilitados e
  `aria-describedby`/`aria-invalid` nos três campos, via o novo
  `FieldLimit.vue`; a edição da descrição no painel usa `Save`/`Cancel` sem
  histórico. Item legado acima dos limites (fixture `KANB-7` no seed,
  executável só em `createSeed()`) é exibido integralmente. Provas do revisor:
  16/16 nos testes de contrato, vitest 79/79, build ok e CI verde no head
  reconciliado com `main`. PR #110, release `v0.26.0`. Dívidas registradas:
  testes dedicados do item ficam para KAN-118/119.

## v0.25.0 — 2026-10-19

### Fixed

- **KAN-91** — modelo único de folhas uniformizado: `sidebarNavigation` passa a
  devolver **todas** as seções como entries `{ entity, leaves }` (boards com
  `leaves: [boardLeaf]` e Experiences via `experienceEntry()` com entity
  sintético `{ id: 'experiences', name: 'Experiences' }`); `sectionsOf`
  concatena `entry.leaves` de boards, workflows, teams e experiences numa
  forma só e `railSections` mantém o filtro de grupos vazios;
  `AppSidebar.vue` itera `entry.leaves` no grupo de boards (badge e
  entry-actions preservados), conforme o contrato rastreado
  `tests/sidebar-navigation.spec.js`. Na fronteira de 1024px, o spec do rail
  passa a aceitar `button.sidebar-toggle` **ausente do DOM** em modo mobile
  (`should('not.exist')`), alinhado à decisão KAN-87 (controle morto fora do
  DOM); a asserção de 1025px continua exigindo o botão presente e clicável.
  PRs #118/#119, release `v0.25.0`.


## v0.24.1 — 2026-10-19

### Added

- **KAN-96** — suíte de testes do drawer em tela cheia (onda 2 da KAN-86;
  mudança só de teste, sem código de produção): BDD em pt-BR
  (`frontend/tests/mobile-drawer-fullscreen.feature`) cobrindo as faixas
  767/768/1023/1024/1025, os quatro fechos, o lock do body, os alvos de 44px e
  a ausência de overflow; contrato na fonte (`tests/mobile-drawer-fullscreen.test.mjs`,
  token único `$mobile-breakpoint`/`--breakpoint-mobile`, media query estrita
  `max-width: $mobile-breakpoint - 1`, `inset: 0`/`width: 100%`, sem `100vw`);
  integração no `App.vue` (`tests/mobile-drawer-fullscreen.spec.js`) e E2E em
  navegador real (`cypress/e2e/mobile-drawer-fullscreen.cy.js`). PR #116,
  release `v0.24.1`.

## v0.19.0 — 2026-10-19

### Added

- **KAN-94** — modal de nova task em largura total e status inicial validado
  (produção): `input`/`textarea`/`select` do modal em `width: 100%` (CSS
  aditivo em `.new-task-modal`, largura de 520px preservada), seletor de
  status na criação restrito aos `initial` do workflow (default no primeiro;
  UI bloqueada com aviso pt-BR quando não há `initial`), foco inicial no
  summary e erros por campo acessíveis (`aria-invalid`, `aria-describedby`,
  `role="alert"`). No fake backend, `POST /api/issues` valida antes de
  qualquer mutação: `boardId` inválido → 422 `field: 'boardId'` sem fallback;
  status omitido usa o primeiro `initial`; inexistente, de outro workflow,
  não-inicial ou sem coluna → 422 `field: 'statusId'`; `columnId` forjado é
  ignorado e a coluna deriva de `column.statusIds`. `client.js` anexa
  `status`/`field` ao `Error`. Contrato em `docs/KAN-94-guide.md`; os testes
  do §8 seguem nas subtarefas KAN-122/123. PR #103, release `v0.19.0`.

## v0.18.0 — 2026-10-19

### Added

- **KAN-83** — modelo único de folhas e rail colapsado na sidebar (produção):
  `frontend/src/navigation.js` novo e puro expõe `sidebarNavigation(store,
  workspaceId)` e `railSections(navigation)`; workspace nulo ou ainda não
  carregado devolve modelo e rail vazios, sem erro. `AppSidebar.vue` passa a
  consumir o modelo na árvore expandida (`entry.entity.name`, folhas derivadas
  de `entry.leaf`/`entry.leaves`) e, com a sidebar colapsada em desktop
  (`showRail = collapsed && !isMobile`), renderiza `nav.nav.rail[aria-label="Pages"]`
  com folhas `a.leaf.rail-leaf` de 36×36, ícone de 18px, `title`/`aria-label`
  pelo `railTitle` único ("Flow B · Statuses"), divisórias entre grupos e ativo
  com fallback `var(--selection, var(--surface-3))`; brand, grupos, chevrons,
  lápis, badges, paths, rótulos e Sources ficam fora do rail. CSS aditivo em
  `main.scss` (largura 48px via `--activity-width`, foco 2px accent), sem
  backend, sem `localStorage`, sem rota nova; mobile, drawer e a regra da KAN-65
  intactos. Prova: `tests/sidebar-navigation.test.mjs` (workspace nulo,
  seções, páginas, Sources fora, `railTitle` único) e
  `cypress/e2e/sidebar-rail.cy.js`. A adaptação de `sidebar-collapse.cy.js` ao
  rail fica para a sub-task de testes. PR #96, release `v0.18.0`.

## v0.17.2 — 2026-10-19

### Fixed

- **KAN-68** — a grade inferior do console agora tem prova completa como o
  usuário a usa: BDD em português com 16 cenários mapeados um a um para as
  suítes, integração `node:test` (forma da mensagem, idempotência do
  `pollOnce`, catálogo fechado, `/clear` local, `busy` sem reenvio), vitest
  (abrir/fechar, clamp, filtro, autoscroll e pílula singular/plural) e E2E no
  navegador (`/help`, `/clear`, `role="log"`, temas escuro e claro). O harness
  de E2E recusa medir um Vite alheio: porta ocupada ou servidor morto abortam
  com status 1 (`run-e2e-port.test.mjs`), e o coletor em
  `cypress/support/e2e.js` reprova qualquer cenário com erro, rejeição solta ou
  `console.error`. Verificado por mutação: sempre-plural derruba o teste da
  pílula e remover o poll imediato do `start()` derruba a reabertura. Barra:
  `node --test` 22/22, vitest 79/79, E2E 27/27. PR #90, release `v0.17.2`.

## v0.17.1 — 2026-10-19

### Fixed

- **KAN-18** — grade inferior do console fecha o MVE em pt-BR: `ConsoleInput.vue`,
  `ConsoleOutput.vue` e o E2E passam a usar os rótulos do contrato
  (`Entrada do console`, `Digite um comando… (/help)`, `Enviar`,
  `Nenhuma mensagem`, `Filtrar por ator`, `Todas`, `Nova saída`/`Ocioso`,
  `N nova`/`N novas`), com a pílula singular medida por mutação. `append()`
  normaliza todo `at` para ISO 8601 UTC (inclusive o `+00:00` do presenter PHP)
  e preserva como texto o valor não interpretável. `start()` dispara
  `pollTick()` imediato, então a grade abre preenchida (KAN-124); o dedupe cobre
  a reabertura. Guia e `.feature` alinhados; no merge com a main `c562dcb`:
  vitest `79/79`, `console.test.mjs` `21/21`, build verde e E2E `27/27` em porta
  livre (`console.cy.js` `3/3`). PR #92, release `v0.17.1`. Ressalvas: o harness
  de E2E ainda aceita um Vite alheio na 5173 (guarda na KAN-68/PR #90) e a
  KAN-68 precisa de rebase sobre esta `main`.

## v0.17.0 — 2026-10-19

### Added

- **KAN-67** — painel de input do console: `ConsoleInput.vue` substitui o stub
  da fundação com `<form class="console-input">` (`aria-label="Console input"`,
  placeholder `Type a command… (/help)`); o submit normaliza espaços, limpa o
  campo e entrega o texto ao catálogo fechado via `runCommand(rawInput)`. Com
  `consoleStore.state.busy` ativo, input e botão ficam desabilitados e o submit
  é ignorado — sem reenvio duplicado. Comando desconhecido não é avaliado no
  componente: o erro nasce em `commands.js` como `kind: 'error'` no output.
  `/clear` continua puramente local. O histórico efêmero de ↑/↓ preserva e
  devolve o rascunho digitado. 11 testes do componente; `npm run test:unit`
  `75/75`, `npm run build` 59 módulos e CI `frontend` verde (a falha anterior
  era flake do harness em `run-e2e-exit-code`, reproduzido 3/3 local). PR #88,
  release `v0.17.0`. Os rótulos seguem o contrato do guia; a tradução pt-BR da
  grade entra na KAN-18.

## v0.16.0 — 2026-10-19

### Added

- **KAN-66** — painel de output do console: `ConsoleOutput.vue` renderiza o
  `consoleStore` como log `role="log"`/`aria-live="polite"`, com uma linha
  `.console-line.{agent,system,command,error}` por mensagem e horário local.
  `messages`/`unread` são computados (sobrevivem ao `/clear`); ao montar o painel
  ancora no fundo, e com o leitor a ≤24px do fim as mensagens novas mantêm o
  autoscroll. Rolar para cima trava o painel, a pílula `.console-jump` conta as
  novas e volta ao fundo com `clearUnread()`; o filtro por ator é só de render e
  o texto é sempre interpolado (sem `v-html`). 13 testes do componente; na árvore
  do PR vitest `64/64` em UTC, Asia/Tokyo e America/Sao_Paulo, build verde e E2E
  `27/27` (`console.cy.js` `3/3`). PR #81, release `v0.16.0`. A CI `frontend`
  segue vermelha no passo `Contracts` por Xvfb (dívida de harness).

## v0.15.2 — 2026-10-19

### Added

- **KAN-64** — store do console e catálogo de comandos aceitos: `store/console.js`
  normaliza os comentários de `GET /api/issues` em mensagens
  `{ seq, at, actor, kind, text }` (só `author !== 'user'`), com dedupe por
  `issueId:commentId` e fallback autor + instante + hash do body para o payload
  do presenter PHP sem `id`; `at` ausente recebe o ISO do append. O polling de
  5s pausa em `document.hidden` e `start()`/`stop()` acompanham a grade aberta
  (montagem por `v-if`). `store/commands.js` executa localmente o catálogo
  fechado `/status`, `/list`, `/help` e `/clear` contra o `boardStore`; `/clear`
  não gera eco nem HTTP, desconhecido vira `kind: 'error'` sugerindo `/help`, e
  `busy` bloqueia reenvio. Falha de polling vira uma única linha de erro até o
  próximo sucesso. `console.feature` preserva os 12 cenários congelados e
  acrescenta o 6º (start/stop), total 13. Verificação na árvore do PR: node
  `17/17`, vitest `53/53`, `console.cy.js` `3/3` e build verde. PR #87, release
  `v0.15.2`; o diff tem 6 arquivos (os 5 da sub-task mais `console.spec.js`,
  alinhado ao contrato de erro do polling). Acompanhamento: KAN-124 cobre o poll
  imediato ao abrir a grade.

## v0.15.1 — 2026-10-19

### Changed

- **KAN-71** — aceitação da grade direita fechada com prova executável: o BDD
  `tests/copilot.feature` cobre os cinco critérios do RFC e a acessibilidade;
  os 7 guardas de `tests/copilot.test.mjs` travam o contrato (`AppCopilot.vue`
  sem rede/store/`v-html`, `copilotOpen = ref(true)`, `altKey` antes do ramo
  `Ctrl+B`, token `--copilot-width` e ícone `copilot`); e
  `cypress/e2e/copilot.cy.js` mede a largura real em 1280×900/1920×900, prova a
  alternância por clique e `Ctrl+Alt+B` sem tocar sidebar, painel de issue ou
  colunas, o reload expandido e a ausência de painel/símbolo em 1024×900 e
  390×844. Verificação na `main` pós-merge: vitest 53/53, guardas 7/7, E2E
  27/27 (`copilot.cy.js` 8/8), sem erro de runtime/console. PR #82, release
  `v0.15.1`; a CI `frontend` segue vermelha na dívida de Xvfb.

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
