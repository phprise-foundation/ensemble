# Guia de Implementação — KAN-128: avatares circulares e comentários com autor

Status: RFC aprovada (KAN-128). Autora da etapa: Framework Architect (Lucy).
Este guia é a fonte única do contrato de avatares/comentários do board; produção
e testes mudam juntos. Base: RFC KAN-128 (decisões fechadas no KAN-79),
viabilidade do Jarvis (`var/kanban.local/artifacts/jarvis-kan128-feasibility.md`),
ADR de integração do David
(`var/kanban.local/artifacts/KAN-128-david-adr-integracao.md`),
`docs/PHILOSOPHY.md`, `docs/GUIDE.md`, `docs/ARCHITECTURE.md` e o guia do
Avatar (`docs/KAN-89-guide.md`, citado pela KAN-108).

Revisão r1: o refinamento de QA (`var/kanban.local/artifacts/alexya-KAN-128-refinamento.md`)
pousou os contratos de teste já na árvore, como RED/esqueleto não rastreado;
ondas 3–4 os **adotam**, não reescrevem (detalhes em §5–§6).

## 1. Objetivo e limites

Assignee do card com avatar circular (componente compartilhado) e lista de
comentários do painel com miniatura circular do autor + nome + timestamp
absoluto `19/10/2026 12:07`. Fronteira:

- KAN-108 entrega `Avatar.vue` (+ `.avatar` circular e fallback): aqui só
  consome, não altera.
- KAN-111 é dona do bloco `panel-meta` do `IssuePanel.vue` (assignee do painel);
  esta RFC toca **só** a seção Comments do mesmo arquivo.
- KAN-130 entrega `frontend/src/lib/avatar-color.js` (`avatarColorFor`); aqui só
  consome.
- KAN-131 (KAN-126 Fase 1) expõe `session.avatar`/`avatarColor` + `displayName`;
  o avatar do user nos comentários depende disso.
- KAN-80/KAN-90 (aba History) fora do escopo: os comentários ficam na aba
  existente, sem contêiner de abas novo.
- Upload de foto (KAN-75/126) e i18n do painel fora do escopo; strings novas em
  inglês; nenhum texto de UI em pt-BR nesta entrega.

## 2. Estado de partida

- `frontend/src/components/IssueCard.vue`: `<span class="avatar sm">` com a
  inicial do `agent.name` sobre `agent.avatarColor` — retangular no CSS atual,
  sem foto, sem tooltip.
- `frontend/src/components/IssuePanel.vue`: `panel-meta` usa `.portrait`
  (dona: KAN-111); Comments renderiza `entry.author` como texto e
  `format(entry.at)` = `new Date(value).toLocaleString()` (formato flutuante).
  Revisão: o arquivo ganhou a capa `FieldLimit` (limites de outra sub-task),
  sem tocar nesta seção.
- `frontend/src/store/board.js`: `addComment` envia `{ author: 'user', body }`
  fixo; `frontend/plugin/fake-backend.js` faz `comments.push({ id, author:
  body.author ?? 'user', body, at: now ISO })` — POST **inalterado** nesta RFC
  (contrato David: zero escrita nova na API).
- `frontend/src/data/seed.js`: comentário antigo `c1` com `author: 'user'`;
  agentes com `avatarColor` e sem `portrait` (o data URL 3x4 da Vivy chega pela
  KAN-108); `user: { login: 'admin', displayName: '' }`.

## 3. Resolução do autor — lib pura (fase de fundação)

`frontend/src/lib/comment-author.js` (novo, sem DOM, importável por componentes,
store e node:test):

- `resolveAuthor({ agents, user, author })` com `agents` = lista do store e
  `user` = snapshot da sessão (`{ login, displayName, avatar, avatarColor }`).
  Ordem vinculante da RCF/decisão 1:
  1. agente por **id** (`agent.id === author`);
  2. agente por **nome** (`agent.name === author`);
  3. user por **login** (cobre os `admin` antigos e os novos POSTs);
  4. user por **displayName**;
  5. desconhecido → `{ kind: 'unknown', name: author, image: null,
     color: avatarColorFor(author) }` (inicial da string original: quem calcula
     é o `Avatar`, a partir de `name` — sem duplicar lógica de inicial).
  Colisão: a ordem já faz o id do agente vencer; sem caso especial para o
  literal `'user'` (cai no passo 5, decisão 2).
  Retorno: `{ kind: 'agent'|'user'|'unknown', name, image, color }`;
  agente → `{ name, image: portrait ?? null, color: avatarColor }`; user →
  `{ name: displayName || login, image: avatar, color: avatarColor }`.
- `formatCommentTimestamp(at)` → `dd/MM/yyyy HH:mm` com pad 2, no fuso local do
  instante já carregado, **sem** `Intl`/`toLocaleString` (determinístico em
  node, jsdom e navegador; sem formato relativo, decisão 5).

## 4. Escritas de produção (aplicação)

- `store/board.js`: `addComment` passa `author: session.login` (importar
  `session`; sem ciclo — `session.js` não importa `board.js`). POST do backend
  continua tratando `author` como string.
- `IssueCard.vue`: trocar o `<span class="avatar sm">` por
  `<span class="card-avatar" :title="agent?.name ?? 'Unassigned'"><Avatar
  size="sm" :name="agent?.name ?? 'Unassigned'" :image="agent?.portrait ?? null"
  :color="agent?.avatarColor" /></span>`. Avatar não-decorative (o `aria-label`
  "Avatar de {nome}" vem do componente); tooltip = `:title` no anchor. Fallback
  `'?'`/cor neutra quando sem assignee.
- `IssuePanel.vue` (só a seção Comments): cada comentário vira linha flex —
  `Avatar size="sm"` **decorative** (o nome fica visível na legenda) com os
  dados de `resolveAuthor`, legenda com o `name` resolvido,
  `formatCommentTimestamp(entry.at)` no lugar de `format()`, corpo inalterado.
  Nomes longos não quebram a linha: `min-width: 0` + truncamento por elipse no
  nó do texto. Ordem cronológica, textarea e botão intocados.
- `main.scss`: regras aditivas `.comment-*` (avatar + coluna de legenda,
  `gap`, alinhamentos), só tokens de tema; nada de literals de cor.
- `seed.js`: 4 comentários novos em `KANB-1`, ordem cronológica crescente:
  `author: 'admin'` (user por login), `'vivy'` (agente por id), `'Kaio'`
  (agente por nome) e um antigo `'user'` (fallback) — cobre todas as ramificações
  do mapeamento. Data URL ≤ 10 KB: usar o seed da Vivy da KAN-108 se pousar
  com ≤ 10 KB; caso contrário, atribuir data URL próprio ≤ 10 KB a outro agente
  sem editar `seed-portrait.js` (arquivo em voo de outra sub-task).

## 5. Contrato de testes (o que prova o quê)

Os quatro arquivos abaixo já existem na árvore como RED/esqueleto (autoria no
refinamento de QA, §Revisão r1), apenas não rastreados pelo git; as ondas
3–4 os **adotam** — tornar verde, estreitar e materializar os corpos — sem
reescrever do zero nem duplicar autoria:

| Prova | Suíte | Arquivo (RED pousado) |
|---|---|---|
| `resolveAuthor` (id/nome/login/displayName/colisão/fallback) e `formatCommentTimestamp` (ISO `Z` e com offset) | node:test | `frontend/tests/comment-author.test.mjs` (11 RED) |
| POST leva `author: session.login`; card e painel renderizam via Avatar (nome, `:title`, fallback, formato) | vitest | `frontend/tests/comments.spec.js` (3 RED) + `frontend/tests/comments.feature` (9 cenários, BDD pt-BR) |
| Comentários de user e de agente no navegador, com data URL, formato absoluto, fallback visível | Cypress | `frontend/cypress/e2e/comments-avatars.cy.js` (11 `it` pendentes) |

Cada falha RED nomeia a onda que a torna verde. Os RED unitários falham por
design até as ondas 1–2 pousarem (nada deles vai para `main` vermelho); os
`it` vazios do Cypress são pending nativo e não envermelham suíte alguma
(provado na convivência). Quem torna um arquivo verde o comitta no branch da
própria onda. Suítes existentes que flutuarem com o DOM/timestamp novo (ex.:
smoke do board)
se adaptam sem enfraquecer contratos. Assertions de timestamp validam o formato
(`^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$`), nunca o valor absoluto — evita flake de
fuso. E2E segue o padrão da casa: `collectErrors`, zero `console.error`
(runtime/rejeição), restauração do `db.json`/entidades no `afterEach`,
`E2E_PORT` exclusiva em worktrees.

## 6. Ondas e sub-tasks

| Onda | Sub-task | Assignee | Depende de | Entrega |
|---|---|---|---|---|
| 1 | Fundação: `lib/comment-author.js` + POST com login | Coder | KAN-130 | lib nova + `store/board.js` |
| 2 | Aplicação: card circular, comentários com autor, seed | Coder | KAN-108, KAN-111, KAN-131, fundação | `IssueCard.vue`, `IssuePanel.vue`, `main.scss`, `seed.js` |
| 3 | Testes: adotar os RED existentes e torná-los verdes | Kaio | ondas 1–2 | `.feature` + `.test.mjs` + `.spec.js` |
| 4 | E2E: materializar os corpos dos `it` pendentes | Mai | onda 2–3 | `comments-avatars.cy.js` |

- Ondas 1 e 2 rebaseiam em `origin/main` antes de começar — a `IssuePanel.vue`
  já incorporou a capa `FieldLimit` de outra sub-task. Recorte da onda 1 nos
  RED: verdes ficam apenas lib (`resolveAuthor`/`formatCommentTimestamp`) +
  `addComment`/fonte do store; os testes de fonte de card/painel/seed
  permanecem RED por design até a onda 2.
- A sub-task que assumir primeiro commita este guia em `docs/KAN-128-guide.md`;
  as demais referenciam. Commits Conventional com `KAN-128` no corpo; branches
  `feat/kan-128-*` / `test/kan-128-*`.
- **Nota de quadro:** sub-tasks nascem com `worker` vazio; quem assina se
  bloqueia ao assumir com o motivo "Depende de". A ordem registrada aqui (e nos
  blocked_by) é vinculante.

## 7. Regras de pouso e riscos

- Arquivos quentes em voo: `Avatar.vue`/`main.scss`/`seed.js` (KAN-108),
  `IssuePanel.vue` (KAN-111 dona do `panel-meta`; o arquivo já carrega a capa
  `FieldLimit` — preservá-la ao tocar só a seção Comments), `avatar-color.js`
  (KAN-130), `session.js` (KAN-131). Conflito → reescrever **consumindo** os
  contratos; nunca editar o item alheio.
- Nunca `<img>` quebrada: qualquer falha de imagem cai no fallback
  inicial+cor (`@error` do `Avatar` já cobre); `image` nunca é `undefined`
  (usar `?? null`).
- `formatCommentTimestamp` é a única saída de tempo dos comentários: apagar o
  `toLocaleString` da `IssuePanel` nesta entrega (o console usa o formato
  próprio e fica de fora).
- Sem comentários no código; o "porquê" vive neste guia e no commit.
- Strings novas em inglês ("Unassigned" continua o termo do painel); sem i18n.

## 8. Barra de qualidade

- `npm ci`, `npm run build`, `node --test frontend/tests/*.test.mjs`,
  `npm run test:unit` e `npm run test:e2e` verdes, sem regressão nas suítes
  existentes (board, perfil, console, copilot). Barra **final**: durante as
  ondas vale o recorte de RED por design do §5–§6; nenhum RED de unidade pousa
  em `main` antes de verde.
- Nenhuma dependência nova; nenhum `v-html`; nenhuma lógica de avatar fora do
  `Avatar`/`avatarColorFor`/`resolveAuthor`.
- Acessibilidade: `aria-label`/`title` com o nome; contraste ≥ 4,5:1 herdado do
  contrato KAN-108; foco/teclado dos controles existentes preservados.
