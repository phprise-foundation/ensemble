# Guia de Implementação — KAN-126: perfil com foto, Save com estados e guarda de saída

Status: RFC aprovada (KAN-126). Autora da etapa: Framework Architect (Lucy).
Este guia é a fonte única do contrato de perfil da SPA; produção e testes mudam
juntos. Base: RFC KAN-126, viabilidade do Jarvis, ADR de integração do David
(`var/kanban.local/artifacts/KAN-126-integracao-david.md`),
`docs/PHILOSOPHY.md`, `docs/GUIDE.md`, `docs/ARCHITECTURE.md` e o guia do
avatar/upload (`docs/KAN-89-guide.md`).

## 1. Objetivo e limites

Foto de usuário como rascunho persistida no Save, botão primário com estados
(normal/disabled/loading/sucesso), guarda de saída com confirm custom, remoção
do botão voltar e `Avatar` compartilhado nas superfícies do usuário. Feedbacks
usam diálogos/toasts da KAN-77; validação de arquivo usa a guarda da KAN-108.

Fronteira: KAN-77 entrega `dialog.confirm`/toasts (aqui só consome); KAN-108
entrega `Avatar.vue` e `lib/image-upload.js` (aqui só consome, não altera);
KAN-79/KAN-80 entregam avatares em cards/comentários e histórico (o usuário
nelas usa o avatar desta RFC); KAN-78 tem fluxo próprio de foto de agente.
Fora de escopo: upload real/backend próprio, cropper, UI de cor, i18n.

## 2. Estado de partida

- `frontend/src/views/ProfilePage.vue`: edita só o nome (`session.rename`),
  `saved`/`error` como hints inline, botão `back` com `router.back()`.
- `frontend/src/store/session.js`: `state` com `login`/`displayName`;
  `rename()` faz `PATCH /api/user` só do nome.
- `frontend/plugin/fake-backend.js` (ramo `head === 'user'`): PATCH só aceita
  `displayName` (trim, 40 chars) e o GET devolve `{ login, displayName }`.
- `frontend/src/data/seed.js`: `user: { login: 'admin', displayName: '' }` —
  sem `avatar`/`avatarColor`.
- `frontend/src/components/ProfileMenu.vue`: `<span class="profile-avatar">`
  com `session.initial` sobre fundo fixo (sem foto).
- KAN-108 (em Coding) entrega: `Avatar.vue` (props `name`, `image`, `color`,
  `size`, `ring`, `decorative`; texto por luminância), `.avatar` circular base
  22px + `.avatar.md/lg`, e `frontend/src/lib/image-upload.js`
  (`IMAGE_TYPES` PNG/JPEG/WebP, `IMAGE_MAX_BYTES` 2 MiB, `imageFileError`,
  `readImageFile`). Nesta RFC esses arquivos são **entrada, não alvo**.

## 3. Contrato do fake backend — PATCH `/api/user` parcial e idempotente

Corpo parcial `{ displayName?, avatar?, avatarColor? }`; merge por campo:

| Campo ausente | Significado |
|---|---|
| `displayName` ausente | manter o atual |
| `avatar` ausente | manter o atual |
| `avatar: null` | remover foto (fallback: inicial + cor) |
| `avatar: "data:...base64"` | definir foto |
| `avatarColor` ausente | manter; com hex `#[0-9a-fA-F]{6}` define (422 se inválido) |

- `displayName` mantém o contrato atual: trim, ≤ 40 chars, 422 caso contrário.
- `avatar` aceita `null` ou data URL prefixado `data:image/(png|jpeg|webp);base64,`
  com base64 ≤ 512 KiB (defesa em profundidade: a normalização do §4 costuma
  produzir < 80 KB). Qualquer outra coisa responde 422.
- Tudo validado **antes** de qualquer mutação (padrão KAN-94/120).
- GET e PATCH respondem `{ login, displayName, avatar, avatarColor }`, com
  `avatar: null` quando sem foto. Repetir o mesmo PATCH produz o mesmo estado.
- Backfill no boot do fake backend: `db.user.avatar ??= null` e
  `db.user.avatarColor ??= avatarColorFor(db.user.login)` (§4.2); o seed passa
  a nascer com `avatar: null` e a cor determinística.
- PATCH inválido loga no console do plugin (observabilidade leve; o toast de
  erro ao usuário vem da Fase 2 via KAN-77).

## 4. Libs puras (fase 0 da fundação)

### 4.1 `frontend/src/lib/image-normalize.js` (novo)

- Constantes exportadas: `IMAGE_MAX_SIDE = 512`, `IMAGE_QUALITY = 0.85`.
- `fitWithin(width, height, maxSide)` — função **pura**: retorna
  `{ width, height }` com o maior lado reduzido a `maxSide` e proporção
  preservada (`arredondar para cima`; retorna as próprias dimensões quando
  ambas ≤ maxSide). Testável em node (sem canvas).
- `normalizeImageFile(file)` — único lugar com DOM: decodifica o arquivo
  (`imageFileError` do chamador teria barrado antes; aqui só decodifica),
  desenha em canvas com `fitWithin`, reencode `toDataURL('image/webp')`
  (fallback JPEG quando o browser não devolver webp) em `IMAGE_QUALITY`,
  e retorna o data URL final. Se o resultado ≥ o data URL do arquivo original,
  retorna o original (nunca infla). Normalização apenas no navegador: nada
  cru > 2 MiB vai para o `db.json`.

### 4.2 `frontend/src/lib/avatar-color.js` (novo)

- `avatarColorFor(login)` — função **pura**: FNV-1a sobre o login completo,
  índice `% 8` numa paleta fixa documentada:
  `['#4c9aff','#00b8d9','#8777d9','#ff991f','#e5484d','#46a758','#c95dd8','#8a7249']`.
  A cor do texto (preto/branco, contraste ≥ 4,5:1) já é calculada pelo
  `Avatar` da KAN-108; aqui só o fundo. Determinístico: mesmo login, mesma
  cor, sempre. Importável por `seed.js` (fake backend) e componentes.
- Nota de ADR: o David posicionou a função "junto do pacote do Avatar"
  (KAN-108). Como a KAN-108 está em Coding sem essa função, ela mora nesta
  lib nova para não alterar escopo de item em execução; a KAN-108 continua
  intocada.

## 5. Fase 1 — Perfil: rascunho, Save com estados e persistência (MVE)

> Aproveita só o que existe na Fase 1: feedback de arquivo/Save é inline
> (padrão `hint` atual). Migração para toast é da Fase 2 (KAN-77).

1. `session.js`: `state` ganha `avatar` (data URL | null) e `avatarColor`;
   `load()` mantém o parse; **substituir `rename()` por
   `updateProfile({ displayName, avatar })`** (única consumidora é a
   ProfilePage) — `avatar: null` remove, campo omitido preserva; sincroniza
   o state com a resposta.
2. Rascunho no `ProfilePage`: `draft` local com `displayName` e
   `avatar` (inicial = `session.avatar`). Selecionar arquivo executa
   `imageFileError` (KAN-108) → inválido vira mensagem custom inline e o
   arquivo é descartado; válido executa `normalizeImageFile` e vira o draft.
   **Remover** marca `draft.avatar = null` (fallback). Nada grava fora do
   Save: um único PATCH carrega nome + foto juntos.
3. Preview circular imediato via `<Avatar :name :image :color size="lg">`
   com `@error`; cor de fundo sempre `session.avatarColor`
   (determinística pelo login; sem UI de cor).
4. Botão primário: `disabled` quando `!dirty` (nome ou foto mudou; `null`
   explícito conta como mudança), `loading` durante o PATCH, sucesso com
   `role="status"`; login somente leitura, `displayName` editável.
5. A11y: label no input de arquivo, foco visível, operação por teclado,
   `alt`/`aria-label` no preview; textos novos em inglês ("Profile", "Upload
   photo", "Remove photo", "Saved.", "Discard changes?").
6. O botão voltar **permanece** nesta fase; removê-lo é da Fase 2 com a
   guarda pronta (nunca sair sem escapatória).

## 6. Fase 2 — Guarda de saída one-shot e remoção do voltar

Mecanismo do ADR (independente de biblioteca), num composable
`useDirtyGuard({ router, isDirty, confirmLeave })`:

1. `onBeforeRouteLeave`/`onBeforeRouteUpdate`: quando `isDirty` **e** sem a
   flag armada, captura o destino (`pendingTo`), retorna `false` e pergunta
   `dialog.confirm({ title: 'Discard changes?', danger: true })` — a KAN-77
   garante foco inicial no cancelar, Escape e retorno de foco.
2. Confirmar saída arma `allowNext = true` (one-shot) e reexecuta
   `router.push(pendingTo)`; a guarda vê a flag, consome-a e libera — sem
   segunda caixa (evita a captura dupla). Cancelar fica na página.
3. Um único ponto de decisão para sidebar, topbar e navegação in-page;
   `beforeunload` só para reload/fechar (última linha, UI nativa imutável).
4. Remover o botão voltar e o restante de `router.back()` da página.
5. Migração de feedbacks para a KAN-77: sucesso do Save → toast success;
   falha do PATCH e arquivo inválido → toast error; hint inline fica só para
   validação de campo (nome > 40). Nenhum `window.confirm/alert` novo.

## 7. Fase 3 — `Avatar` do usuário nas superfícies existentes

1. `ProfileMenu.vue`: trocar `<span class="profile-avatar">` por
   `<Avatar :name="session.label" :image="session.avatar"
   :color="session.avatarColor" size="sm" />`, preservando o gatilho
   `aria-haspopup`/`aria-expanded` e as medidas da rail (22px circular).
2. Em qualquer outra superfície de usuário **já existente** (assignee,
   comentário), renderizar pelo `Avatar` com os dados do usuário; proibido
   criar tratamento paralelo de avatar (sem lógica espalhada de `initial` +
   fundo fora do componente/`avatarColorFor`).
3. Superfícies que ainda não existem (comentários com autor avatars,
   histórico — KAN-79/KAN-80) ficam de fora; o aceite restringe às presentes.
   Não tocar em `AgentsPage`/`IssuePanel` (fluxo do agente é KAN-89/KAN-111,
   `portrait`/color próprios).

## 8. Contrato de testes (o que prova o quê)

| Prova | Suíte | Arquivo |
|---|---|---|
| PATCH parcial: ausente preserva, `null` remove, 422s, idempotência | node:test | `frontend/tests/profile-menu.test.mjs` (extensão) |
| Backfill do user no boot/seed (`avatar`, `avatarColor`) | node:test | `frontend/tests/seed-backfill.test.mjs` (extensão) |
| `fitWithin`, `avatarColorFor` (puras, sem DOM) | node:test | `frontend/tests/profile-libs.test.mjs` (novo) |
| Rascunho, estados do Save, preview/fallback, login readonly | vitest | `frontend/tests/profile-page.spec.js` (extensão) |
| BDD dos fluxos acima (pt-BR) | feature | arquivo `.feature` do perfil (novo/estendido) |
| Data URL final < original (canvas real), upload/save/reload/fallback, guarda de descarte | Cypress | `frontend/cypress/e2e/profile-avatar.cy.js` (novo) + fixture de foto grande |

A "prova do payload" (data URL menor que o recebido) roda só no E2E: canvas
real no navegador; node/jsdom não tem canvas. Fixture commitada, sem data URL
inline; restaurar o user no `afterEach` via `cy.request PATCH /api/user`.

### 8.1 Artefatos do refinamento de QA (Mai)

- BDD (pt-BR) e esqueleto Cypress em Red:
  `var/kanban.local/artifacts/KAN-126-refinamento-qa-mai.md`.
- Seletores `data-test` do spec viram micro-contrato do Coder da Fase 1:
  `photo-input`, `save`, `avatar-preview`, `file-error`.
- O spec pousa na árvore só com a KAN-133, após a Fase 1; até lá o Red é
  intencional. Fixture de foto grande e restauração do user no `afterEach`
  (PATCH parcial do §3) já estão prontos no artefato.

## 9. Ondas e subtasks

| Onda | Sub-task | Assignee | Depende de | Entrega |
|---|---|---|---|---|
| 1 | Backend e seed: PATCH parcial + backfill | Coder | — | `fake-backend.js`, `seed.js` |
| 1 | Libs puras: `image-normalize.js` + `avatar-color.js` | Coder | — | `frontend/src/lib/` (2 arquivos novos) |
| 1 | Fase 1: rascunho, Save com estados, persistência | Coder | KAN-108, onda 1 | `ProfilePage.vue`, `session.js` |
| 2 | BDD e testes do contrato, libs e rascunho | Kaio | onda 1 completa | `.feature` + `.test.mjs` + `.spec.js` |
| 2 | E2E: upload, validação, remoção, save, reload | Mai | Fase 1 + testes | `.cy.js` + fixture |
| 3 | Fase 2: guarda one-shot, remoção do voltar, toasts | Coder | Fase 1 + KAN-77 | composable + `ProfilePage.vue` |
| 3 | Fase 3: `Avatar` do usuário nas superfícies | Coder | Fase 1 | `ProfileMenu.vue` + presentes |

- Ondas 1 (backend e libs) correm em paralelo; a Fase 1 só parte quando
  KAN-108 e a onda 1 estiverem na árvore.
- As sub-tasks das Fases 2/3 são pequenas; se ao assumir precisarem de mais
  que ~20 linhas de instrução ou creçam, o Coder sinaliza e o Tech Lead as
  fatia em sub-tasks de testes (Kaio/Mai) seguindo o trio do KAN-89.
- **Nota de quadro:** o guard do `kanban` só deixa o worker modificar o item;
  as sub-tasks nascem com `worker` vazio, então quem assina deve se bloquear
  ao assumir com o motivo "Depende de". A ordem fica registrada aqui.

## 10. Regras de pouso e riscos

- Rebasear em `origin/main` antes de começar; KAN-108 está em voo nos mesmos
  arquivos (`lib/`, `main.scss`) — esta RFC **consome** `Avatar`/`image-upload`
  e nunca os edita. Conflito → reescrever consumindo o contrato do §2.
- `db.json` de desenvolvimento carrega conteúdo do usuário: o backfill só
  completa campos ausentes; nunca sobrescrever foto salva.
- Payload: data URL cru > 512 KiB é rejeitado com 422 — a prova E2E é a
  garantia real; o limite defensivo existe para proteger o `db.json`.
- Guarda: `allowNext` é one-shot e de escopo do composable; navegação que
  falhar entre armado e consumido deixa a flag suja — resetar em cada
  decisão do usuário. Teste de dupla captura é obrigatório (Fase 2).
- Sem comentários no código; o "porquê" vive neste guia e no commit.
  Commits Conventional com `KAN-126` nos corpos; branch `feat/kan-126-...`
  por sub-task; `E2E_PORT` exclusiva em worktrees (padrão KAN-109).
- A sub-task que assumir primeiro commita este guia em `docs/KAN-126-guide.md`;
  as demais PRs o referenciam.

## 11. Barra de qualidade

- `npm ci`, `npm run build`, `node --test frontend/tests/*.test.mjs`,
  `npm run test:unit` e `npm run test:e2e` verdes, sem regressão nas suítes
  existentes (perfil, menu, smoke) e sem erro de console no E2E.
- Nenhuma dependência nova; nenhum `window.confirm/alert/prompt`.
- Acessibilidade: label no file input, foco/teclado, `alt`/`aria-label` no
  preview/avatar, contraste preservado (contrato KAN-108).
- Nenhuma foto/gravação fora do Save; nenhum PATCH parcial (nome sem foto ou
  foto sem nome); fallback inicial+cor restaurado após Remover + Save.
