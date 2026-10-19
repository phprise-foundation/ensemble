# Guia de Implementação — KAN-62: Grade direita (painel do copilot colapsável)

Status: RFC aprovada. Escopo: **fase de desenho de UI (MVE)**. Autor da etapa:
Framework Architect.

## Objetivo

Um símbolo de grade "direita" no menu superior (padrão do VSCode para a sidebar
secundária) expande/colapsa o painel do copilot no lado direito da aplicação.
Nesta rodada o painel é um **esqueleto**: estrutura, largura e alternância. O
conteúdo de conversa não é critério de aceite.

Totalmente **client-side**: nenhum endpoint novo, nenhuma store, nenhum
`localStorage`, **nenhuma chamada de rede** (guarda do Systems Architect: nada de
`GET/POST /api/chat`, nada de `CHAT_URL`). Colapsar apenas esconde o painel
(decisão 7 — não há estado de conversa em jogo).

## Decisões que já governam a implementação

- **Montagem no nível de `.app`:** o painel é irmão de `.content` (espelho da
  barra lateral esquerda, altura total). Zero reestruturação de `.main` e
  `.content`; o painel de issue (dentro do board) mantém seu comportamento
  (decisão 5) e o board apenas comprime, custo aceito (decisão 6).
- **Breakpoint único:** a fonte continua sendo `$sidebar-breakpoint` /
  `--breakpoint-sidebar` (KAN-9/KAN-12). Desktop = `!isMobile`, a mesma media
  query do `.sidebar-toggle`. *Ambiguidade resolvida:* o RFC diz "telas ≥ 1024px"
  como atalho para "desktop"; exatamente 1024px permanece mobile, como já ocorre
  com o toggle esquerdo. Não inventar um segundo valor.
- **Largura própria:** token novo `--copilot-width: min(25vw, 400px)` no `:root`
  (ao lado de `--activity-width`). **Não** reutilizar `--panel-width`
  (`min(400px, 33vw)`, do painel de issue) — o RFC veda replicar um padrão sobre
  o outro.
- **Estado:** `copilotOpen = ref(true)` em `App.vue` — nasce expandido a cada
  carregamento (decisão 4), sem persistência. Colapsar é **remoção do DOM**
  (`v-if`); sem estado interno a perder.
- **Símbolo desktop-only:** o botão usa `v-if="!isMobile"` (decisão 3 — "não são
  renderizados"), espelhando o padrão já presente em `App.vue` para o drawer.
- **Zero backend:** `AppCopilot.vue` não faz `fetch`, não importa store, não lê
  variável de ambiente. O conteúdo é placeholder estático.
- **Atalho:** `Ctrl+Alt+B` / `Cmd+Alt+B` desktop-only, paridade com o VSCode
  ("Toggle Secondary Side Bar"), espelhando `Ctrl+B` (esquerda) e `Ctrl+J`
  (inferior). Bônus de acessibilidade — **não** é critério de aceite.
- **Idioma dos rótulos:** inglês, fiel ao resto da UI ("Session ended", "Toggle
  sidebar"…); i18n já é item do roadmap.
- **Sem comentários no código.** O "porquê" vive neste guia e nas mensagens de
  commit.

## Contrato de nomes (fonte única — guia e testes mudam juntos)

| Item | Valor acordado |
|---|---|
| Componente | `frontend/src/components/AppCopilot.vue` |
| Elemento raiz | `<section id="app-copilot" class="copilot-panel">` |
| Cabeçalho | `.copilot-header` com título `Copilot` |
| Corpo placeholder | `.copilot-body` com texto estático em inglês |
| Chave do ícone em `AppIcon` | `copilot` → `['M17 3h4v18h-4z', 'M3 3h11v18H3z']` |
| Botão de topo | `.icon-btn.copilot-toggle` (ao lado de `.sidebar-toggle`/`.console-toggle`) |
| Rótulo do botão | `aria-label="Toggle copilot"`, `:aria-expanded="copilotOpen"`, `aria-controls="app-copilot"`, `title` dinâmico |
| Estado | `copilotOpen = ref(true)` em `App.vue` |
| Token de largura | `--copilot-width: min(25vw, 400px)` no `:root` |
| Atalho | `Ctrl+Alt+B` (Linux/Windows) e `Cmd+Alt+B` (macOS) |

## Mapa de alterações

```
frontend/src/components/AppIcon.vue    → entrada copilot (onda 1)
frontend/src/components/AppCopilot.vue → painel esqueleto (onda 2)
frontend/src/App.vue                   → estado copilotOpen + botão + atalho + montagem (onda 2)
frontend/src/styles/main.scss          → --copilot-width + seções .copilot-* (onda 2)
frontend/tests/copilot.feature         → cenários BDD (onda 3)
frontend/tests/copilot.test.mjs        → guardas estáticos (onda 3)
frontend/cypress/e2e/copilot.cy.js     → E2E dos 5 critérios (onda 3)
```

## Sub-tasks e paralelismo

| onda | sub-task | depende de |
|---|---|---|
| 1 | Ícone `copilot` em `AppIcon.vue` | — |
| 2 | Fundação: `AppCopilot.vue` + `App.vue` + CSS | **KAN-65** (aterrissar antes) |
| 3 | Testes: BDD + guardas + E2E | onda 1 e onda 2 |

O `App.vue`, o `main.scss` e o `AppIcon.vue` são território compartilhado com a
grade inferior em voo (KAN-63/KAN-65). Honre as ondas: a onda 2 só aterrissa
**depois** da KAN-65 (rebase incluído); a onda 1 é linha isolada no dicionário
de ícones (rebase trivial sobre a KAN-63). A onda 3 consome todos os contratos e
é a crítica de aceitação.

## Pontos de atenção

- **Armadilha do atalho:** `Ctrl+Alt+B` também casa com o guarda atual de
  `Ctrl+B`. O ramo do copilot precisa ser checado **primeiro** (guarda com
  `event.altKey`), e o do sidebar em seguida — caso contrário um atalho dispara
  os dois toggles.
- **Coexistência:** alternar o copilot não altera sidebar (`.collapsed`), console
  (`#app-console`) nem painel de issue — cada grade tem seu `ref` independente.
- **Larguras coexistindo por definição:** copilot `min(25vw, 400px)` × issue
  `min(400px, 33vw)`. Em 1280px o copilot tem 320px; em 1920px, 400px — valores
  úteis para asserção no E2E.
- **Sem `v-html`, sem rede, sem shell.** Placeholder é texto interpolado.

## Observações para quem codar

- **Tema:** consumir só os tokens existentes (`--surface`, `--border`, `--text`,
  `--muted`); borda esquerda do painel espelha `.sidebar`/`.issue-panel`.
- **Acessibilidade:** `aria-expanded` dinâmico no botão, foco visível, painel
  com `aria-controls` correspondente ao `id`.
- **Qualidade (barra):** `npm run test:unit` verde, `node --test` das suítes
  novas verde e `npm run test:e2e` verde, sem erro de runtime/console. A feature
  só está pronta quando testada como o usuário a usa (navegador real, viewport
  desktop e mobile), não apenas "não lançou exceção".
