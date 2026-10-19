# Guia de Implementação — KAN-18: Grade inferior (console: output + input)

Status: RFC aprovada. Escopo: **fase de desenho de UI (MVE)**. Autor da etapa:
Framework Architect.

## Objetivo

Uma grade inferior retrátil no board, inspirada nos terminais do VSCode e dos
consoles GCP/AWS, com dois painéis:

- **output** — fluxo de mensagens dos agentes (log com autoscroll travável,
  filtro por agente e indicador de atividade);
- **input** — barra de comandos com catálogo fechado
  (`/status`, `/list`, `/help`, `/clear`).

Nada de backend nesta fase: o output deriva de **endpoints existentes**
(`GET /api/issues`) via polling, e os comandos executam **localmente contra o
`boardStore`** (que já consome os GETs). A UI trata o output como fluxo de
mensagens com cursor — trocar polling por stream na fase seguinte não toca nos
componentes.

## Decisões que já governam a implementação

- **Fonte do output (MVE):** polling de `GET /api/issues` a cada 5s; mensagens
  derivadas dos comentários cujo `author !== 'user'`, deduplicadas pela chave
  adaptadora descrita em "Adapter de comentários: dois payloads, uma chave"
  (`issueId:commentId` quando há `id`; fallback autor + instante + texto). O
  adaptador vive **só na store** — é o único ponto que muda quando a fonte real
  for decidida (acordo
  David/Jarvis: UI independente da fonte; polling e stream intercambiáveis).
- **Comandos no MVE:** execução local contra o `boardStore`; nenhum POST novo,
  nenhuma decisão de backend. `/status` e `/list` leem o estado já carregado;
  `/help` é texto estático; `/clear` é ação puramente local de UI.
- **Sem reenvio duplicado:** input desabilitado enquanto um comando executa
  (`busy`) — ponto acordado na validação do Systems Architect.
- **Sem persistência:** histórico efêmero por sessão, sem `localStorage`
  (Fase 2). A grade nasce **fechada** a cada carregamento.
- **Atalho:** `Ctrl+J`/`Cmd+J`, desktop-only — espelha o padrão `Ctrl+B` da
  KAN-24 (retorno cedo quando `isMobile`; o drawer do KAN-9 fica intocado).
- **Sem shell, sem execução arbitrária:** catálogo fechado; comando desconhecido
  vira mensagem de erro no output, nunca avaliação de input.
- **Idioma dos rótulos do console:** **pt-BR**, conforme o contrato de idioma
  do board. O console é operacional e o público é o board em pt-BR; os rótulos
  do resto da UI só mudam junto com o item de i18n do roadmap. Engloba ecos,
  respostas, `/help` e mensagens de erro da store.
- **Sem comentários no código.** O "porquê" vive neste guia e nas mensagens de
  commit.

## Contrato de nomes (fonte única — guia e testes mudam juntos)

| Item | Valor acordado |
|---|---|
| Componente container | `frontend/src/components/AppConsole.vue` |
| Painel de output | `frontend/src/components/ConsoleOutput.vue` |
| Painel de input | `frontend/src/components/ConsoleInput.vue` |
| Store do console | `frontend/src/store/console.js` → `consoleStore` |
| Catálogo de comandos | `frontend/src/store/commands.js` → `runCommand(rawInput)`, `CATALOG` |
| Chave do ícone em `AppIcon` | `console` → `['M4 5h16v14H4z', 'M7 9l3 3-3 3', 'M13 15h4']` |
| Botão de topo | `.icon-btn.console-toggle` (ao lado do `.sidebar-toggle`) |
| Rótulo do botão | `aria-label="Alternar console"`, `:aria-expanded="consoleOpen"`, `aria-controls="app-console"` |
| Estado da grade | `consoleOpen = ref(false)` em `App.vue` |
| Altura | `frontend/src/store/consoleHeight.js` → `consoleHeight` (240 inicial), `consoleStyle` com `--console-height` inline, clamp 120px..60vh; ações `growHeight`/`shrinkHeight` (±16px), `dragHeight`, `resetHeight` (duplo clique) |
| Atalho | `Ctrl+J` (Linux/Windows) e `Cmd+J` (macOS) |
| Elemento raiz | `<section id="app-console" class="console">` |
| Handle de resize | `.console-resize` (borda superior; foco + setas ↑/↓ = ±16px) |
| Log de output | `<div class="console-output" role="log" aria-live="polite">` |
| Linha de mensagem | `.console-line` + `.console-line.{agent,system,command,error}` |
| Entrada de comando | `<form class="console-input">` com `aria-label="Entrada do console"` e placeholder `Digite um comando… (/help)` |
| Botão de envio | `.console-input-send` com texto `Enviar` |
| Vazio no output | `.console-empty` com texto `Nenhuma mensagem` |
| Filtro por ator | `.console-filter` com `aria-label="Filtrar por ator"`; opção fixa `Todas` |
| Indicador de atividade | `.console-activity` com `aria-label` `Nova saída` / `Ocioso` |
| Pílula "voltar ao fim" | `.console-jump` (`N nova` / `N novas` conforme a contagem `unread`; some ao aterrissar no fundo) |
| Mensagem (forma) | `{ seq, at, actor, kind: 'agent'|'system'|'command'|'error', text }` |

## Adapter de comentários: dois payloads, uma chave

`GET /api/issues` tem duas formas reais em uso:

| Fonte | Issue | Comentário |
|---|---|---|
| fake backend do Vite (`plugin/fake-backend.js`) | `summary`, `statusId` | `{ id, author, body, at }` |
| presenter PHP (`ItemArrayPresenter`) | `title`, `status` | `{ author, body, created_at }` |

Regras da store (ponto único de adaptação):

- **Chave de dedupe:** `issueId:commentId` quando o comentário traz `id`;
  senão `issueId:author@<instante>#<hash do body>`, com instante
  `created_at ?? at`. O hash do texto blinda o segundo sem fração do
  `DATE_ATOM` do PHP. A chave é estável entre polls: o mesmo comentário nunca
  entra duas vezes, e um comentário novo sempre entra.
- **Ocorrências explícitas no lote:** no presenter PHP (sem `id`), dois
  comentários idênticos do mesmo autor no mesmo instante produzem a mesma
  chave. O adaptador trata a *k*-ésima ocorrência da chave num mesmo poll como
  nova (entra), enquanto a *primeira* é a que persiste no conjunto de
  dedupe entre polls. Assim uma releitura do mesmo payload não duplica e o
  lote não perde linhas; a distinção fina só seria exata com `id` no
  presenter — decisão de backend fora da KAN-64.
- **`at` da mensagem:** `created_at ?? at` **normalizado para ISO 8601 UTC**
  (`Date#toISOString`, ex.: `2026-10-19T10:00:00.000Z`) dentro de `append()`,
  inclusive o `+00:00` do presenter PHP; o `at` do comentário só é usado para a
  chave de dedupe, nunca cru na mensagem. Sem nenhum dos dois, o instante do
  append (ISO). Valor não interpretável é preservado como texto. Um comentário
  sem `id` e sem instante só é distinguível pelo texto, que faz parte da chave.
- Comentários de `user` não entram.

## Adapter de issues nos comandos

`/status` e `/list` leem o `boardStore` já carregado e normalizam o issue no
mesmo ponto (`commands.js`): título = `summary ?? title ?? id`, status =
`statusId ?? status`, resolvido contra `boardStore.status(id)`. O boardStore
continua sendo o formato camelCase do fake backend; o presenter PHP entra
quando o `GET /api/issues` real substituir o fake — a store não muda.

## Contrato da store (`consoleStore`)

- `state.messages` (append-only), `state.busy`, `state.unread`, `state.polling`.
- `append({ actor, kind, text, at? })` → atribui `seq` monotônico e `at`
  normalizado para ISO 8601 UTC (`Date#toISOString`): o `at` explícito (inclusive
  o `+00:00` do presenter PHP) ou, sem ele, o instante do append; incrementa
  `unread`.
- `clearUnread()`, `clear()` (zera mensagens e `unread`).
- `start()` / `stop()` → ciclo do polling (5s; pausa com `document.hidden`). O
  ciclo fica **amarrado à grade aberta/fechada pela fundação**: a grade só é
  montada quando `consoleOpen` é verdadeiro, então `App.vue` a renderiza com
  `v-if` e o `AppConsole.vue` chama `start()` em `onMounted` e `stop()` em
  `onUnmounted`. Fechar a grade desmonta o componente e para o polling; um
  `v-show` manteria o ciclo rodando com a grade fechada, e por isso não é
  usado. `pollOnce()` e `pollTick()` exportados para testes.
- **`pollOnce()` após `start()`:** `start()` marca `state.polling`, agenda o
  intervalo e dispara `pollTick()` na hora (respeitando `document.hidden`), para
  a grade não abrir vazia até o primeiro tick; o dedupe cobre reaberturas
  (KAN-124).
- **`CATALOG`**: exportado por `commands.js` (`/status`, `/list`, `/clear`,
  `/help`); a mesma lista que o `/help` imprime.
- **`pollOnce()`**: chamadas concorrentes compartilham a mesma requisição;
  nunca rejeita; falha vira **uma única** mensagem `kind: 'error'` até o
  próximo poll bem-sucedido.
- **`resetDedupe()`**: API só de teste — esvazia a chave de dedupe e o
  controle de falha reportada. Não é usada por componentes.
- Comandos (`commands.js`): `runCommand(rawInput)` grava o eco (`'command'`),
  executa e grava o resultado (`'system'`); erro (`'error'`) para desconhecido,
  com sugestão de `/help`; `busy` observável durante a execução e segundo
  `runCommand` durante `busy` não grava eco nem resposta. `/clear` zera log e
  `unread`, sem eco e sem HTTP.

## Decisão registrada: altura fora de `App.vue`

> Nota de escopo (KAN-64): esta seção descreve `frontend/src/store/consoleHeight.js`,
> que pertence à KAN-65. A emenda é compartilhada e chega a `main` com a
> fundação; este PR registra o texto acordado para que guia e feature não
> divirjam.

O guia original congelava `consoleHeight = ref(240)` em `App.vue`. A
implementação manteve em `App.vue` apenas o estado de abertura
(`consoleOpen = ref(false)` + botão + `Ctrl+J`) e **encapsulou a altura** em
`frontend/src/store/consoleHeight.js`, importada por `AppConsole.vue`.

Motivo: o estado, o clamp e a persistência de evento do resize (ponteiro,
setas, duplo clique) pertencem à grade, não ao shell do app; `App.vue` fica com
uma responsabilidade só e a altura ganha testes próprios. Os **nomes**
(`consoleHeight`, `--console-height`, 240/120/60vh/16px) e o comportamento
permanecem exatamente os congelados — só o arquivo que hospeda o estado mudou.

## Mapa de alterações

```
frontend/src/components/AppIcon.vue        → entrada console (KAN-63)
frontend/src/store/console.js              → store + adaptador de polling (KAN-64)
frontend/src/store/commands.js             → catálogo fechado de comandos (KAN-64)
frontend/src/components/AppConsole.vue     → container, resize, stubs dos painéis (KAN-65)
frontend/src/store/consoleHeight.js         → consoleHeight + ações de resize (KAN-65)
frontend/src/App.vue                        → estado consoleOpen + botão + atalho (KAN-65)
frontend/src/styles/main.scss              → --font-mono + todas as seções .console-* (KAN-65)
frontend/src/components/ConsoleOutput.vue  → log, autoscroll, filtro, atividade (KAN-66)
frontend/src/components/ConsoleInput.vue   → barra de comandos (KAN-67)
frontend/tests/console{.feature,.test.mjs,.spec.js} + cypress/e2e/console.cy.js (KAN-68)
```

## Sub-tasks e paralelismo

| onda | sub-task | depende de |
|---|---|---|
| 1 | KAN-63 — Ícone `console` em `AppIcon.vue` | — |
| 1 | KAN-64 — Store do console + catálogo de comandos | — |
| 1 | KAN-65 — Fundação da grade (container + `App.vue` + CSS) | — |
| 2 | KAN-66 — Painel de output | KAN-64, KAN-65 |
| 2 | KAN-67 — Painel de input | KAN-64, KAN-65 |
| 3 | KAN-68 — Testes (BDD + integração + E2E) | KAN-66, KAN-67 |

A onda 1 corre **em paralelo** (arquivos distintos: dicionário de ícones /
`store/` / container + `App.vue` + `main.scss`). KAN-65 cria os dois painéis como
stubs mínimos para compilar; KAN-66 e KAN-67 substituem os stubs e correm em
paralelo entre si (arquivos distintos, **sem tocar `main.scss`** — o CSS completo
fica em KAN-65 pelo contrato de nomes). A onda 3 consome todos os contratos e é
a crítica de aceitação.

> Nota: a marcação `kanban block` em KAN-66/67/68 não pôde ser aplicada (itens
> já em execução pelos assignees no momento do planejamento). Esta tabela é a
> fonte única das dependências — honre as ondas antes de puxar a sub-task.

## Pontos de backend abertos (fase seguinte — critério 3 do RFC)

1. **Fonte real do output:** eventos do board vs logs de execução do
   agent-watcher vs híbrido com abas/filtros. A store isola a decisão.
2. **Contrato de comandos no servidor:** um `POST /api/console` (ou equivalente)
   para comandos que um dia precisarem de servidor; catálogo servido ou fixo no
   cliente; autorização além da sessão do board.
3. **Streaming:** SSE fica no hardening do roadmap; a UI já trata o output como
   fluxo com cursor (`seq`), então polling→stream é trocar o adaptador.
4. **Histórico/persistência:** efêmero por sessão no MVE; persistir (auditoria)
   é decisão da fase seguinte, junto com o backend.

## Observações para quem codar

- **Tema:** consumir só os tokens existentes (`--surface`, `--surface-2`,
  `--border`, `--text`, `--muted`, `--accent`, `--danger`, `--ok`); log em fonte
  monoespaçada — novo token `--font-mono: ui-monospace, SFMono-Regular, Menlo,
  Consolas, monospace` no `:root`.
- **Autoscroll:** ancorado no fundo quando o usuário está a ≤24px dele; rolar
  para cima trava; a pílula `.console-jump` com `unread` volta ao fundo e chama
  `clearUnread()`.
- **Filtro por ator:** `<select>` com `Todas` + atores distintos presentes nas
  mensagens; filtro é só de render, nunca apaga mensagens.
- **Segurança:** renderizar `text` como texto (interpolação Vue), nunca
  `v-html`; nada de shell, `eval` ou interpolação de comando.
- **Acessibilidade:** `role="log"` + `aria-live="polite"` no output; handle
  focável com `aria-label="Redimensionar console"`; foco visível em tudo interativo.
- **Qualidade (barra):** `npm run test:unit` verde, `node --test` das suítes
  novas verde e `npm run test:e2e` verde, sem erro de runtime/console. A feature
  só está pronta quando testada como o usuário a usa (grade real, comandos
  digitados no navegador), não apenas "não lançou exceção".
