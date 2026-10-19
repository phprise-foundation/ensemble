# Guia de Implementação — KAN-87: Topbar, ordem dos controles e grades

Status: RFC aprovada. Autor da etapa: Framework Architect (Lucy). Este guia é a
**autoridade única de posição** do grupo direito do topbar; os guias KAN-18 e
KAN-62 passam a referenciá-lo em vez de definir posição própria.

> Atualização de 2026-10-19 (decisão de Alan): o console já está na `main`.
> A KAN-97 absorve a KAN-98 e entrega a sequência completa, incluindo
> `grid-bottom`, movendo o `.console-toggle` existente. A KAN-98 fica sem
> trabalho. A KAN-99 testa a sequência completa e a KAN-100 acrescenta `Ctrl+J`
> e regressões.

## 1. Objetivo

Fixar a ordem do grupo direito do topbar, da esquerda para a direita:

```
Sim → grade esquerda → grade inferior → grade direita → tema → perfil
```

A ordem do DOM é a mesma da ordem visual. Cada grade tem um slot fixo e o slot
inferior já tem nó, porque o console (KAN-18/KAN-67) está na árvore. Não há
controle morto, flag de disponibilidade, botão desabilitado nem segundo
breakpoint.

## 2. Estado de partida (main `c562dcb`, v0.17.0)

- À esquerda do título: `.menu-toggle` (mobile), `.sidebar-toggle`,
  `.console-toggle` e `.copilot-toggle`. O sidebar é desktop-only apenas por
  CSS; console e copilot usam `v-if="!isMobile"`.
- À direita: Sim (`.button.ghost` com dot e rótulo), tema (`.icon-btn` sem
  classe própria) e `ProfileMenu`.
- Console (KAN-18/KAN-67) **já está na main**: `AppConsole.vue`,
  `.console-toggle` com `aria-controls="app-console"` e `<AppConsole>` montado
  em `App.vue`; estado em `consoleOpen` e atalho `Ctrl+J`.
- `.topbar-actions` ainda não existe. A KAN-97 o cria e move para dentro dele
  todos os controles do grupo direito, inclusive o `.console-toggle`.
- Breakpoint único: `$sidebar-breakpoint: 1024px` em `main.scss`, exposto como
  `--breakpoint-sidebar`. `≤1024` é mobile; `≥1025` é desktop.

## 3. Contrato de DOM

```html
<header class="topbar">
  <button class="icon-btn menu-toggle" ...>...</button>
  <h1>...</h1>
  <div class="spacer"></div>
  <div class="topbar-actions">
    <button data-topbar-slot="sim" class="button ghost sim-toggle" ...>...</button>
    <button data-topbar-slot="grid-left" class="icon-btn sidebar-toggle" ...>...</button>
    <button data-topbar-slot="grid-bottom" class="icon-btn console-toggle" ...>...</button>
    <button data-topbar-slot="grid-right" class="icon-btn copilot-toggle" ...>...</button>
    <button data-topbar-slot="theme" class="icon-btn theme-toggle" ...>...</button>
    <ProfileMenu data-topbar-slot="profile" />
  </div>
</header>
```

Regras:

1. `.menu-toggle` continua sendo o primeiro filho do `.topbar`, à esquerda do
   título; ele é mobile-only pela regra CSS já existente.
2. O `<h1>` e o `.spacer` ficam entre o menu e `.topbar-actions`. O spacer
   mantém `margin-left: auto`.
3. `.topbar-actions` é o **único** lugar de controles do grupo direito. Nenhum
   grid pode ficar fora dele, incluindo o `.console-toggle`.
4. `data-topbar-slot` é o contrato de teste; não usar classe interna como
   seletor de ordem. No `ProfileMenu`, o atributo cai no elemento raiz
   (`.profile-menu`), que é single-root.
5. A presença do nó `grid-bottom` é **estrutural**: ele existe porque
   `AppConsole`/`.console-toggle` estão no `App.vue`. Se o painel sair da
   árvore, o botão sai junto. Não criar `v-if="consoleEnabled"`, placeholder,
   botão oculto ou desabilitado.
6. As grades são desktop-only. Todas usam `v-if="!isMobile"`; as regras CSS
   `.sidebar-toggle` desktop-only permanecem como cinto de segurança do
   primeiro paint. Em ≤1024px não há nó `[data-topbar-slot^="grid-"]` após a
   montagem.

### 3.1 Tabela dos slots

| Slot | Elemento | Quando existe | Contrato ARIA / ação |
|---|---|---|---|
| `sim` | `button.button.ghost.sim-toggle` | sempre | `aria-label="Sim: toggle simulated data"` (contém o rótulo visível), `:aria-pressed="hasSimulation"`, título dinâmico; clique chama `setAll(!hasSimulation.value)` |
| `grid-left` | `button.icon-btn.sidebar-toggle` | desktop | `aria-label="Toggle sidebar"`, `aria-controls="app-sidebar"`, `:aria-expanded="!sidebarCollapsed"`; mantém `Ctrl+B` |
| `grid-bottom` | `button.icon-btn.console-toggle` | desktop, com o console já na árvore | preserva `aria-label`/título atuais do KAN-18, `aria-controls="app-console"`, `:aria-expanded="consoleOpen"`; mantém `Ctrl+J` |
| `grid-right` | `button.icon-btn.copilot-toggle` | desktop | contrato KAN-62 inalterado: `aria-controls="app-copilot"`, `:aria-expanded="copilotOpen"`, `Ctrl+Alt+B` |
| `theme` | `button.icon-btn.theme-toggle` | sempre | `aria-label` e `title` dinâmicos existentes (`Light theme`/`Dark theme`); clique alterna o tema |
| `profile` | raiz `.profile-menu` | sempre | contrato KAN-15 inalterado; é o último item à direita e abre `profile`/`logout` |

O botão Sim continua sendo botão de alternância com dot e rótulo visível; não
vira label inerte. O tema continua sendo o último controle de grade/tema; o
perfil, o último item.

## 4. Contrato de CSS

Adicionar, sem reescrever o restante:

```scss
.topbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.topbar .sim-toggle {
  padding: 4px 10px;
  font-size: 12px;
}

.topbar h1 {
  flex: 0 1 auto;
}

.topbar-actions button:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 1px;
}
```

Manter em `.topbar h1`: `min-width: 0`, `overflow: hidden`,
`text-overflow: ellipsis`, `white-space: nowrap`. O `.topbar-actions` não
encolhe (`flex: 0 0 auto`); o título encolhe e trunca. Não criar novo
breakpoint: usar somente `--breakpoint-sidebar`.

## 5. Contrato de testes

### 5.1 BDD — `frontend/tests/topbar-order.feature`

Cenários em português do Brasil:

1. Em desktop, a sequência exata é
   `sim → grid-left → grid-bottom → grid-right → theme → profile`;
   `grid-bottom` existe entre as duas grades e `profile` é o último nó.
2. Sim alterna a simulação, com `aria-pressed` coerente.
3. Grade esquerda alterna o colapso e expõe `aria-expanded`.
4. Grade inferior (console) alterna o painel por botão e expõe
   `aria-controls="app-console"` e `aria-expanded` coerente. O atalho `Ctrl+J`
   é coberto pela KAN-100.
5. Grade direita alterna o copilot e expõe `aria-expanded`.
6. Tema alterna; perfil é o último e abre `profile`/`logout`.
7. Em ≤1024px não há grade, o menu mobile permanece e não há overflow.
8. Em 1025px não há overflow e o título trunca antes de empurrar o grupo.
9. O slot `grid-bottom` é estrutural: existe com o console e não tem flag,
   placeholder ou botão desabilitado.
10. Todos os controles têm nome acessível, foco visível e operação por teclado.

### 5.2 Integração

- `frontend/tests/topbar-order.test.mjs` (node:test): guardas de fonte —
  `.topbar-actions` depois do spacer, sequência canônica completa, nenhum grid
  fora do container (incluindo o console), `aria-pressed` no Sim,
  `aria-controls` no sidebar, console e copilot, e CSS com
  `flex: 0 0 auto`/`min-width: 0`. A guarda deriva a sequência da presença
  estrutural do console e, com ele na árvore, exige `grid-bottom`.
- `frontend/tests/topbar-order.spec.js` (vitest): monta `App` com stubs e
  valida ordem DOM completa, presença por viewport e ação de cada controle.

### 5.3 E2E — `frontend/cypress/e2e/topbar-order.cy.js`

- Ordem por DOM e por coordenada `left` crescente dentro de `.topbar-actions`.
- Perfil como último nó e última borda direita.
- `aria-expanded` de cada grade acompanha o painel; o console abre e fecha pelo
  botão.
- 1024 e 1025 lidos de `--breakpoint-sidebar` (nunca literal); medir
  `document.documentElement.scrollWidth <= clientWidth` e o topbar.
- Sim, tema, sidebar, console, copilot e perfil acionados de verdade no
  navegador.
- Mesmo padrão de `collectErrors` das suítes existentes: zero erro de
  runtime/console e sem `vite-error-overlay`.
- A KAN-100 acrescenta `Ctrl+J` e as regressões de copilot, sidebar, perfil e
  app.

### 5.3.1 Achados do refinamento (Alexya)

1. `cy.viewport` não dispara `change` do `matchMedia` no Cypress/Electron. O
   helper lê `--breakpoint-sidebar`, aplica o viewport e dá `reload`.
2. Em 1025px, aguardar `have.length` das grades antes de comparar a ordem;
   senão a lista mobile do primeiro paint é capturada.
3. Coleções jQuery não têm `.at()`; usar `[...$slots].at(-1)`.
4. A guarda estática deriva a sequência exata da presença estrutural do
   console.

### 5.4 Sequência exata por etapa

Cada etapa declara a sequência **exata** esperada; não filtrar a expectativa
pelos slots encontrados (isso mascararia um slot ausente).

- KAN-99 (fase 1): `['sim','grid-left','grid-bottom','grid-right','theme',
  'profile']`. `grid-bottom` existe entre as duas grades; o botão do console
  alterna `#app-console` e mantém `aria-expanded` coerente. Não existe versão
  sem `grid-bottom`: o console já está na árvore.
- KAN-100 (fase 2): a mesma sequência completa. Acrescenta `Ctrl+J` e as
  regressões de copilot, sidebar, perfil e app; não cria slot novo.

Assim a ordem relativa nunca fica frouxa e a ausência de um slot obrigatório
falha o teste.

## 6. Ondas e sub-tasks

| Onda | Item | Assignee | Depende de | Entrega |
|---|---|---|---|---|
| 1 | KAN-97 — fundação completa do grupo direito e slots | Coder | — | `App.vue`, `main.scss`, este guia; absorve a KAN-98 e move o console |
| — | KAN-98 — absorvida pela KAN-97 | — | — | sem trabalho; não gera PR |
| 2 | KAN-99 — testes da ordem completa | Kaio | KAN-97 | feature + integração + E2E |
| 3 | KAN-100 — `Ctrl+J` e regressões | Kaio | KAN-99 | extensão da suíte |

A KAN-97 e a KAN-98 mexiam nos mesmos arquivos quentes (`App.vue`,
`main.scss`). Com a absorção, a KAN-97 faz as duas e a KAN-99 passa a correr
depois dela. A KAN-100 fecha a prova de aceitação. KAN-99 e KAN-100 seguem
bloqueadas até suas dependências reais (KAN-97 e KAN-99, respectivamente). A
ausência de entrega da KAN-97 não bloqueia a RFC: é a onda esperada.

As descrições de KAN-99 e KAN-100 no board foram escritas antes desta decisão e
ainda falam da fase sem console; **prevalece este guia**. A KAN-98 permanece no
board apenas para encerramento: não deve ser executada nem gerar PR.

Esqueletos: a KAN-99 copia `frontend/tests/topbar-order.feature`,
`frontend/tests/topbar-order.test.mjs`, `frontend/tests/topbar-order.spec.js` e
`frontend/cypress/e2e/topbar-order.cy.js` do worktree principal e os commita no
próprio PR. Eles foram escritos para a fase 1 sem console; a KAN-99 deve
atualizar as expectativas para a sequência completa da §5.4. Cópia de segurança
em
`/home/esdras/src/phrise/multi-agent/var/kanban.local/artifacts/kan87-skeletons/`;
não é preciso versioná-los antes.

## 7. Riscos e notas para quem codar

- **Console na árvore:** `AppConsole.vue` e `.console-toggle` já estão na
  `main`. Rebasear sobre `origin/main` antes de começar e mover o botão
  existente para o slot, sem recriá-lo nem duplicá-lo.
- **Seletores existentes:** preservar `button[title="Light theme"]`,
  `button[title="Dark theme"]`, `.sidebar-toggle`, `.console-toggle`,
  `.copilot-toggle`, `#app-sidebar`, `#app-console`, `#app-copilot`. As suítes
  `app.cy.js`, `copilot.cy.js`, `sidebar-collapse.cy.js`, `profile-menu.cy.js`
  e `console.cy.js` não podem regredir.
- **Primeiro paint:** `isMobile` nasce `false` e é corrigido no `onMounted`.
  Não escrever asserção de primeiro frame; a regra CSS do `.sidebar-toggle`
  cobre o flash. Não adicionar dependência de `localStorage`.
- **Sim:** nasce ligado (`sim.*` default `true`). No E2E, normalizar com
  `?sim=1` para não herdar `localStorage` de outra execução.
- **E2E concorrente:** o dev server usa `--strictPort`; com múltiplos
  worktrees, rodar com `E2E_PORT` exclusiva para não colidir na 5173.
- **Idioma:** os rótulos existentes são preservados como estão (inglês nos
  controles históricos, português no console); não renomear no escopo desta
  RFC. BDD, guias, comentários de board e mensagens de commit ficam em
  português do Brasil.
- **Sem comentários no código.** O porquê vive neste guia e no commit.

## 8. Barra de qualidade

- `npm run test:unit` verde.
- `node --test frontend/tests/*.test.mjs` verde.
- `npm run build` verde.
- `npm run test:e2e` verde, sem erro de runtime/console, cobrindo ordem,
  breakpoints, overflow e ações, inclusive o console.
- Nenhuma regressão nas suítes de copilot, sidebar, perfil, console, drawer e
  smoke.
- PR com o guia commitado junto; a KAN-97 copia este arquivo de
  `/home/esdras/src/phrise/multi-agent/var/kanban.local/artifacts/KAN-87-guide.md`
  para `docs/KAN-87-guide.md` no mesmo PR; branch `feat/kan-87-topbar-ordem`;
  mensagem Conventional Commits com `KAN-87` (ou `KAN-97` na sub-task).
