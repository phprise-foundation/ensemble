# Guia de Implementação — KAN-83: rail colapsado só com páginas folha

Status: RFC aprovada (KAN-83; contrato único adotado também para KAN-84 e
KAN-85). Autor da etapa: Framework Architect.
Base: KAN-10/KAN-22/KAN-23/KAN-24/KAN-16; ADR de integração em
`var/kanban.local/artifacts/kan-83-adr-david.md`.

> KAN-83, KAN-84 e KAN-85 descrevem a mesma entrega. A produção está na KAN-91
> e a prova de aceite na KAN-92; não abrir uma segunda frente de implementação
> ou de testes. O plano da KAN-84 está em
> `var/kanban.local/artifacts/KAN-84-plano.md`.

## Objetivo

No desktop, com o sidebar colapsado, trocar a árvore por um rail plano que
contém **somente folhas navegáveis**: um ícone por página, tooltip, nome
acessível e destaque da rota ativa. O estado expandido e o drawer mobile
permanecem como estão. A entrega é só de frontend (SPA Vue): sem endpoint,
schema, fila, migração ou `localStorage`.

## Decisões que governam a implementação

- **Folha = rota navegável.** Sem rota, sem ícone. `Sources` fica fora do rail
  porque hoje só oferece editar/excluir; a árvore expandida continua exibindo as
  sources. Rota `/sources` é follow-up, não desta fatia.
- **Experiences entra no rail** como folha única (a rota `/experiences` e a view
  existem no protótipo). A árvore expandida continua sem essa entrada, fiel ao
  escopo da RFC. Endpoint real de Experiences é follow-up. Nota de reconciliação:
  o ADR da KAN-85 recomendou Experiences fora da F1; o contrato consolidado
  posterior (KAN-83/84, já seguido pela produção da KAN-91) decidiu incluir,
  porque a rota existe no protótipo. Vale esta decisão única; o ADR fica
  registrado como divergência resolvida.
- **Fonte única de folhas.** A árvore expandida e o rail consomem o mesmo modelo
  derivado do store; a contagem do rail é a contagem do modelo. Isso evita duas
  verdades quando um workflow/team ganha uma página nova.
- **Desktop é `min-width: $sidebar-breakpoint + 1`.** Exatamente 1024px continua
  mobile, como no toggle existente; não criar um segundo breakpoint.
- **Tooltip = `title` nativo + `aria-label`**, precedente da KAN-23. O nome no
  rail precisa ser único (`Standard · Statuses`, `Default team · Agents`), mesmo que o rótulo visível da
  árvore seja só `Statuses`. Não criar tooltip flutuante nesta fatia. No hover o
  `title` mostra o nome; no foco de teclado o nome acessível vem do
  `aria-label`, acompanhado do anel de foco visível. O E2E verifica
  `title`/`aria-label` e o `outline` aplicado após Tab.
- **Rota ativa:** o `RouterLink` do Vue Router 4.5 aplica
  `router-link-active` e `aria-current="page"` no match exato. O rail herda o
  estilo existente, com fallback de fundo para o tema claro.
- **Rail só renderiza quando `collapsed && !isMobile`.** A árvore é o `v-else`;
  no mobile o estado colapsado continua suspenso e o drawer não muda.
- **A marca (`brand`) some no rail**, pois não é página clicável. No expandido
  ela permanece.
- **Sem comentários no código.** O porquê vive neste guia e nas mensagens de
  commit.

## Contrato de nomes (fonte única — guia e testes mudam juntos)

| Item | Valor acordado |
|---|---|
| Módulo do modelo | `frontend/src/navigation.js` |
| Funções exportadas | `sidebarNavigation(store, workspaceId)`, `railSections(navigation)` |
| Forma do modelo | `{ boards: [{ entity, leaf }], workflows: [{ entity, leaves }], teams: [{ entity, leaves }], experiences: [leaf] }` |
| Forma da folha | `{ key, label, title, railTitle, icon, to, feature }` |
| `label` / `title` | textos atuais da árvore (`Statuses`, `Agents`, nome do board) |
| `railTitle` | nome único para o rail (`Standard · Statuses`, `Default team · Agents`) |
| `feature` | chave de `isSimulated`; `null` em boards |
| Nav do rail | `nav.nav.rail` com `aria-label="Pages"` |
| Link do rail | `a.leaf.rail-leaf`, `:to`, `:title="leaf.railTitle"`, `:aria-label="leaf.railTitle"` |
| Ícone do rail | `AppIcon` com `:size="18"` (a árvore continua em 14) |
| Divisor | `.rail-divider` com `role="separator"` |
| Visibilidade | `showRail = computed(() => props.collapsed && !props.isMobile)` |
| Largura | `--activity-width: 48px` (já existe); link 36×36; `nav` com padding 6px |
| Layout do link | `.rail-leaf { flex: 0 0 36px; width: 36px; height: 36px; padding: 0; justify-content: center; }` declarado depois de `.leaf` (vence o `flex: 1`) |
| Foco | `.rail-leaf:focus-visible` com `outline: 2px solid var(--accent)` |
| Ativo | `.leaf.router-link-active` + `aria-current="page"`; fundo `var(--selection, var(--surface-3))` |
| Oculto no rail | brand, grupos, chevrons, lápis, badges, paths, rótulos e vazios |

## Forma do modelo (exemplo de referência)

```js
const WORKFLOW_PAGES = [
  { key: 'statuses', label: 'Statuses', icon: 'statuses', feature: 'statuses', path: (id) => `/workflows/${id}/statuses` },
  { key: 'transitions', label: 'Transitions', icon: 'transitions', feature: 'transitions', path: (id) => `/workflows/${id}/transitions` },
  { key: 'issue-types', label: 'Issue types', icon: 'issues', feature: 'issueTypes', path: (id) => `/workflows/${id}/issue-types` },
]

export function sidebarNavigation(store, workspaceId) {
  if (!workspaceId) {
    return { boards: [], workflows: [], teams: [], experiences: [] }
  }
  return {
    boards: store.boardsOf(workspaceId).map((board) => ({ entity: board, leaf: boardLeaf(board) })),
    workflows: store.workflowsOf(workspaceId).map((workflow) => ({ entity: workflow, leaves: pageLeaves(WORKFLOW_PAGES, workflow, 'workflow') })),
    teams: store.teamsOf(workspaceId).map((team) => ({ entity: team, leaves: pageLeaves(TEAM_PAGES, team, 'team') })),
    experiences: [experienceLeaf()],
  }
}
```

`boardLeaf`, `pageLeaves` e `experienceLeaf` apenas montam a forma da folha.
`railSections(navigation)` devolve, nesta ordem, uma seção por board, uma por
workflow (com as 3 folhas), uma por team (com as 3 folhas) e a seção
`experiences`. `map`/`flatMap` puros; sem efeito colateral. `Sources` não entra.

## Mapa de alterações

```
frontend/src/navigation.js                   → modelo único (novo)
frontend/src/components/AppSidebar.vue      → showRail, rail, árvore consumindo o modelo
frontend/src/styles/main.scss               → .rail, .rail-leaf, .rail-divider, foco e ativo
frontend/tests/sidebar-rail.feature         → cenários BDD (QA)
frontend/tests/sidebar-navigation.test.mjs  → modelo/seletores (QA)
frontend/cypress/e2e/sidebar-rail.cy.js     → E2E de aceite (QA)
```

A árvore expandida passa a iterar `navigation.boards`, `navigation.workflows` e
`navigation.teams` para renderizar as folhas; os `entry-actions` continuam
usando `entry.entity` para abrir os editores. `sources`, `openBoards`,
`openWorkflows`, `openTeams` e `openSources` permanecem. Não alterar
`router.js`, `store/board.js`, `api/` nem o backend.

## Sub-tasks e ondas

| onda | sub-task | depende de |
|---|---|---|
| 1 | Produção: modelo único + rail + estilos | — |
| 2 | Testes: BDD + modelo + E2E | onda 1 |

A onda 2 pode ser escrita a partir deste contrato enquanto a onda 1 roda, mas
só fecha verde depois que o rail e os seletores estão na árvore. A onda 1 é a
única que toca produção; a onda 2 é a crítica de aceitação.

## Achados do refinamento (Alexya, 2026-10-19)

O refinamento confirmou o Red (`node --test` falha por ausência de
`frontend/src/navigation.js`) e fechou o aceite. Os ajustes abaixo são de teste
e entram na KAN-92; não alteram o contrato de produção.

- **Teclado:** `cy.type('{tab}')` não move o foco. Usar
  `cy.press(Cypress.Keyboard.Keys.TAB)` e rodar o E2E com
  `--browser chrome`; no Electron headless o Tab não percorre. Focar o primeiro
  `.rail-leaf`, pressionar Tab e provar o próximo leaf com `outline` de 2px.
  `:focus-visible` aplicado após `.focus()` programático não serve como atalho.
- **Marca:** `.rail .brand` não existe por construção. A asserção correta é
  `#app-sidebar .brand` não visível no colapsado (ou `not.exist`, se a KAN-91
  remover o markup).
- **Breakpoint:** acrescentar 1024×800 (mobile, sem rail) e 1025×800 (desktop,
  rail após colapsar). Não criar um segundo breakpoint.
- **Negativos:** incluir `.sim-dot` e verificar `aria-label="Pages"` no
  `nav.nav.rail`.
- **Rota ativa:** provar também por deep link (por exemplo,
  `/#/workflows/standard/statuses`), não só por clique.
- **Navegação:** percorrer todos os `.rail-leaf` e conferir o destino; no
  mínimo board, um workflow, um team e Experiences.
- **Texto do BDD:** corrigir o espaço em "quantidade de`.leaf`" e
  "navegaveis" → "navegáveis"; o cenário de nome valida `title`/`aria-label` e
  o anel de foco, pois o Cypress não testa o tooltip nativo visualmente.

## Casos de borda

- **Vários boards/workflows/teams:** todas as folhas aparecem na ordem da
  árvore; cada seção (board, workflow, team) é separada por `.rail-divider`.
- **Workspace ainda não carregado:** modelo vazio, rail vazio, sem focáveis e
  sem exceção (ADR, decisão 4).
- **Sources:** continuam só na árvore expandida; não criar clique simulado.
- **Experiences:** só no rail; a árvore expandida não ganha a entrada nesta
  fatia.
- **Mobile:** `showRail` é falso; drawer, focus trap, Escape e `Ctrl+B` inerte
  permanecem. O colapso suspenso no mobile continua como em KAN-10.
- **Abrir/fechar:** o submenu preserva `openBoards/openWorkflows/openTeams`
  (nascem abertos); não há `localStorage`; sem overflow horizontal.
- **Tema claro:** o fundo do ativo usa `var(--selection, var(--surface-3))` para
  não sumir quando `--selection` não existe no tema claro.

## Armadilhas de compatibilidade

- `frontend/cypress/e2e/sidebar-collapse.cy.js` conta e clica `.leaf`; o rail
  mantém a classe `.leaf` e o primeiro leaf continua sendo o board `KANB`.
- `frontend/tests/sidebar-collapse.test.mjs` exige a regra
  `.sidebar.collapsed .leaf span`; não remova a regra existente.
- `frontend/cypress/e2e/source-directory.cy.js` usa a árvore expandida
  (`#app-sidebar .nav-entry .label`); sources não mudam.
- Testes que montam `AppSidebar` sem a prop `collapsed` continuam na árvore;
  `isMobile` default `false` só produz rail se `collapsed` for verdadeiro.

## Barra de qualidade

Produção:

- `cd frontend && npm run build`
- `npm run test:unit`
- `node --test tests/sidebar-collapse.test.mjs` (regressão do colapso)
- Verificação manual em 1280×800: `Ctrl+B`, contagem de `.rail .leaf`, ausência
  de `.rail .nav-entry/.chevron/.entry-actions/.badge-id/.source-path/.label`,
  largura 48px, ícone 18px, foco visível e volta ao expandido sem erro.

Testes de aceitação:

- BDD em português cobrindo contagem, ausência de não-folhas, tooltip/nome,
  navegação, rota ativa e retorno ao expandido.
- `node --test tests/sidebar-navigation.test.mjs`: modelo com 1 board, 2
  workflows, 2 teams e Experiences; ordem; Sources ausente; workspace nulo;
  `railTitle` único.
- E2E: contagem do rail = folhas da árvore expandida + 1 (Experiences); sem
  `.rail .nav-entry`, chevron, lápis, badge, path, label, `.sim-dot` e sem
  `.brand` visível em `#app-sidebar`; `title` e `aria-label` não vazios;
  `nav.nav.rail` com `aria-label="Pages"`; clique navega em todos os leaves;
  `.router-link-active` e `aria-current="page"` no leaf atual, inclusive por
  deep link; `#app-sidebar` com 48px; `svg` com 18; `scrollWidth` do rail ≤
  `clientWidth`; `.rail-divider` presente entre as seções; 1024×800 sem rail e
  1025×800 com rail após colapsar; Tab entre `.rail-leaf` com `outline` de 2px;
  alternar de volta restaura a árvore.
- `npm run test:e2e -- --browser chrome` verde com o harness de KAN-57/KAN-60
  (exit code e `no spec files found`); usar `E2E_PORT` exclusiva se houver
  worktrees concorrentes.