# Guia de Implementação — KAN-94: modal full-width e status inicial

Status: RFC aprovada (KAN-94). Autora da etapa: Framework Architect (Lucy).
Este guia é a **fonte única** do contrato de layout, seleção de status, validação
do fake backend, erro acessível e testes. Produção e testes mudam juntos.

Base: RFC KAN-94, viabilidade de Jarvis
(`var/kanban.local/artifacts/KAN-94-feasibility.md`), ADR de David
(`var/kanban.local/artifacts/KAN-94-adr-integracao-infra-david.md`),
`docs/PHILOSOPHY.md`, `docs/GUIDE.md`, `docs/ARCHITECTURE.md` e o guia
`docs/KAN-93-guide.md`.

Caminho absoluto do repositório: `/home/esdras/src/phprise/multi-agent`
(`phprise`, com dois “p”). O caminho com `phrise` está errado.

## 1. Objetivo e fronteira

O modal de nova task do protótipo (`frontend/`) deve:

1. ocupar a largura útil do modal com `summary` e `description`, sem espaço
   vazio lateral e sem overflow horizontal;
2. oferecer **somente** status `initial` do workflow do board, com default no
   primeiro `initial` da ordem do workflow;
3. bloquear a criação quando o workflow não tem `initial`, com aviso em pt-BR;
4. impedir que o fake backend grave uma task em status `transitional`, `final`,
   inexistente ou de outro workflow, mesmo que o cliente contorne a UI;
5. derivar a coluna do status e ignorar `columnId` enviado pelo cliente;
6. manter type, assignee, edição existente e acessibilidade funcionando.

Entra nesta entrega:

- `POST /api/issues` com validação completa antes de qualquer mutação;
- contrato de erro `{ error, field }` até a UI;
- `client.js` preservando `status` e `field` no `Error`;
- modal com labels explícitos, foco inicial no summary e erro acessível;
- testes BDD, `node:test`, vitest e Cypress.

Fica fora:

- `PATCH /api/issues/:id` e transições de issues existentes;
- backend PHP real (`VITE_API_URL`);
- tradução geral da SPA (labels e botões seguem em inglês; só as mensagens de
  validação/erro são pt-BR);
- limites de descrição/comentário (KAN-93), história (KAN-90), ícones
  (KAN-88) e largura do modal;
- focus trap, Escape e `role="dialog"` completo — o modal atual não os tem e
  esta entrega não os adiciona.

## 2. Decisões congeladas

| Tema | Contrato |
|---|---|
| Largura do modal | `520px` atual, `max-width: 100%`; não aumentar |
| Campos largos | `input`, `textarea` e `select` em `width: 100%` dentro de `.new-task-modal` |
| Status no create | somente `status.type === 'initial'` do `board.workflowId` |
| Ordem/default | ordem de `state.statuses`; default = primeiro `initial` |
| Sem `initial` | UI bloqueia com aviso; backend 422; sem fallback |
| Status omitido no POST | usa o primeiro `initial` do workflow |
| Status informado | precisa existir, ser do workflow do board e ter `type === 'initial'` |
| `columnId` | ignorado; coluna derivada por `column.statusIds.includes(statusId)` |
| `boardId` inválido | 422 `field: "boardId"`; não cair em `db.boards[0]` |
| Sem coluna para o status | 422 `field: "statusId"`; configuração inconsistente |
| Corpo de erro | `{ error, field }`, com `field` estável (`boardId` ou `statusId`) |
| `client.js` | anexa `status` e `field` ao `Error`, sem mudar `request` |
| Mensagens | validação e erro em pt-BR; identificadores e labels em inglês |
| Summary vazio | validado só na UI (comportamento atual); o fake backend não valida |
| Edição | `PATCH` e transições não mudam nesta entrega |

Mensagens do fake backend (contrato de teste):

```
board inválido
workflow do board não tem status inicial
status inexistente
status de outro workflow
a nova task precisa nascer em um status inicial
status não está associado a nenhuma coluna do board
```

O `field` é sempre `boardId` ou `statusId`; a mensagem nunca é usada para
parsing pela UI.

## 3. Notas de framework (verificadas no projeto)

- **Vue `^3.5.13`**: `<script setup>`, `computed`, `ref`, `watch` e `nextTick`.
  O foco inicial precisa de `await nextTick()` depois de `editing = true`, pois
  o modal só existe no DOM com `v-if`.
- **Vite `^6.0.7`**: o fake backend roda como middleware do dev/preview server;
  os testes importam `handle` diretamente e usam `createSeed()` em memória.
  `save()` só é chamado pelo middleware; `handle` não persiste em disco.
- **Node 24 (CI)**: `node --test`, `assert/strict`, `fetch`/`Response` globais.
  Os arquivos `*.test.mjs` ficam fora do vitest pelo `exclude` existente.
- **Vitest `^5.0.5` + jsdom**: `mount`, `flushPromises`, `vi.mock`; jsdom não
  mede layout real — largura e overflow são provados no Cypress.
- **Cypress `^16.2.0`**: `cy.viewport`, `cy.intercept`, `cy.request`,
  `getBoundingClientRect`, `cy.focused` e `cy.document`. O harness
  `run-e2e.sh` regenera o banco e desliga transições CSS.
- Nenhuma dependência nova é necessária.

## 4. Estado de partida e frentes em voo

Arquivos desta entrega:

- `frontend/src/views/BoardPage.vue`: modal de criação; o select de status lista
  todos os status; `save()` não tem `try/catch`; summary e description não têm
  `id`/`for` explícitos nem largura total.
- `frontend/plugin/fake-backend.js`: `POST /issues` cai em `db.boards[0]`,
  aceita qualquer `statusId`, usa `workflowStatuses[0]` como fallback e faz
  `push` antes de derivar a coluna; `columnId` do cliente é confiado.
- `frontend/src/api/client.js`: lança `Error(message)` e perde `status`/`field`.
- `frontend/src/styles/main.scss`: `.field select { width: 100%; }`; não há
  regra para `input`/`textarea` no modal.
- `frontend/src/data/seed.js`: `to-develop` é o único `initial`; não mudar.

Frentes que disputam os mesmos arquivos:

- **KAN-93** (limites): altera `fake-backend.js`, `client.js`,
  `BoardPage.vue` (textarea/descriptionError) e `main.scss`.
- **KAN-104** (ícones/ARIA): altera `BoardPage.vue` e `IssuePanel.vue`.
- **KAN-88** (ícones): pode alterar botões e `AppIcon.vue`.
- **KAN-113** (History): altera `IssuePanel.vue`; não tocar.
- **KAN-102** (CI): adiciona `test:unit` ao workflow; não duplicar o passo.

Regra de convivência: rebase em `origin/main` antes de codar; preserve o que já
estiver na árvore; adicione apenas os caminhos da própria subtarefa (nunca
`git add .`); não reescreva este contrato. Em especial:

1. Se `frontend/shared/limits.js` e `limitProblem` já existirem, mantenha a
   validação de descrição **como primeira** checagem do `POST /issues`; a
   validação de status entra depois dela.
2. Se o middleware já devolver `field`, não duplique a mudança.
3. Se `client.js` já anexar `status`/`field` (KAN-93), apenas confirme; não
   reescreva.
4. Em `BoardPage.vue`, preserve `FieldLimit`, `descriptionError`,
   `descriptionOver` e o `aria-describedby` da descrição. O branch
   `field === 'description'` do `catch` pertence à KAN-93; os branches
   `summary`/`statusId` e o erro geral entram ao redor dele.
5. O `textarea` da descrição pode estar dentro de `<div class="field">` com
   `<label for="create-description">` (KAN-93) ou no `<label>` atual; mantenha
   o `id="create-description"` e não crie um segundo campo.
6. Preserve os ícones e ARIA que a KAN-104 já tiver aplicado.

## 5. Fake backend — `POST /api/issues`

Em `frontend/plugin/fake-backend.js`, validar **antes** de `nextIssueId`,
`db.issues.push`, reordenação de `order` e `save`. Nenhuma tentativa rejeitada
pode alterar o banco em memória.

### 5.1 Tabela de decisão

| Entrada | Resultado |
|---|---|
| `body.boardId` ausente ou inexistente | `422 { error: 'board inválido', field: 'boardId' }` |
| `body.statusId === undefined` e existe `initial` | usa o primeiro `initial` do workflow |
| `body.statusId === undefined` e não existe `initial` | `422`, `field: 'statusId'`, mensagem `workflow do board não tem status inicial` |
| `body.statusId` informado, inexistente em `db.statuses` | `422`, `field: 'statusId'`, `status inexistente` |
| `body.statusId` de outro `workflowId` | `422`, `field: 'statusId'`, `status de outro workflow` |
| `body.statusId` com `type !== 'initial'` | `422`, `field: 'statusId'`, `a nova task precisa nascer em um status inicial` |
| status válido, mas nenhuma coluna do board o contém | `422`, `field: 'statusId'`, `status não está associado a nenhuma coluna do board` |
| status válido e coluna encontrada | `201`; issue gravada na coluna derivada |

Definição exata: **omitido é `undefined`**. `null`, `''` ou qualquer outro
valor são “informado” e passam pela validação normal (e falham com 422).

### 5.2 Forma sugerida

Manter funções pequenas e privadas do plugin:

```js
function boardProblem(db, boardId) {
  const board = boardOf(db, boardId)
  if (!board) return { error: 'board inválido', field: 'boardId', status: 422 }
  return { board }
}

function createStatusProblem(db, board, statusId) {
  const statuses = db.statuses.filter((status) => status.workflowId === board.workflowId)
  if (statusId === undefined) {
    const initial = statuses.find((status) => status.type === 'initial')
    if (initial) return { status: initial }
    return { error: 'workflow do board não tem status inicial', field: 'statusId', status: 422 }
  }
  const status = db.statuses.find((candidate) => candidate.id === statusId)
  if (!status) return { error: 'status inexistente', field: 'statusId', status: 422 }
  if (status.workflowId !== board.workflowId) {
    return { error: 'status de outro workflow', field: 'statusId', status: 422 }
  }
  if (status.type !== 'initial') {
    return { error: 'a nova task precisa nascer em um status inicial', field: 'statusId', status: 422 }
  }
  return { status }
}

function createColumnProblem(board, status) {
  const column = board.columns.find((candidate) => candidate.statusIds.includes(status.id))
  if (!column) {
    return { error: 'status não está associado a nenhuma coluna do board', field: 'statusId', status: 422 }
  }
  return { column }
}
```

No handler:

```js
const boardResult = boardProblem(db, body.boardId)
if (boardResult.error) return boardResult
const board = boardResult.board

const statusResult = createStatusProblem(db, board, body.statusId)
if (statusResult.error) return statusResult
const status = statusResult.status

const columnResult = createColumnProblem(board, status)
if (columnResult.error) return columnResult
const column = columnResult.column
```

Só depois montar a issue, com `statusId: status.id`, e **não** copiar
`body.columnId` para o objeto. O `order` continua sendo reindexado com
`issuesInColumn(db, board.id, column.id)`, como hoje.

### 5.3 Middleware

O middleware passa a devolver o campo:

```js
res.end(JSON.stringify(result.error ? { error: result.error, field: result.field } : result.data))
```

`JSON.stringify` omite `field` quando ele é `undefined`; os erros 404/409/500
mantêm o corpo antigo. Se a KAN-93 já tiver feito essa mudança, não duplicar.

## 6. Cliente — `frontend/src/api/client.js`

Preservar a assinatura de `request` e anexar os dados do erro:

```js
if (!response.ok) {
  const error = new Error((data && data.error) || `${response.status} ${response.statusText}`)
  error.status = response.status
  error.field = (data && data.field) || null
  throw error
}
```

- `status` e `field` são propriedades do `Error`, não um novo formato.
- A UI nunca faz parsing de `error.message`.
- Erro de rede continua sendo o `TypeError` do `fetch`, sem `status`/`field`.
- Se a KAN-93 já tiver aplicado exatamente esse bloco, não reescrever.

## 7. Interface — `frontend/src/views/BoardPage.vue` e CSS

### 7.1 Estado e derivados

Manter `error` como erro geral (nome já usado pela KAN-93) e acrescentar:

```js
const initialStatuses = computed(() => statuses.value.filter((status) => status.type === 'initial'))
const summaryInput = ref(null)
const summaryError = ref('')
const statusError = ref('')
```

`openCreate()` vira `async` e:

1. zera `error`, `summaryError` e `statusError` (e `descriptionError`, se a
   KAN-93 já existir);
2. monta o draft com `statusId: initialStatuses.value[0]?.id || ''` (lógica já
   existente; não regredir);
3. liga `editing`;
4. `await nextTick()` e chama `summaryInput.value?.focus()`.

`watch` em `draft.summary` e `draft.statusId` limpa o erro correspondente ao
digitar. Não usar `trim` no summary; manter a validação atual
(`if (!draft.value.summary)`).

`save()`:

1. zera os três erros (e `descriptionError`, se a KAN-93 já existir);
2. se o summary estiver vazio, preenche `summaryError` com
   `O resumo é obrigatório.`, foca o summary e retorna;
3. se não houver `initial`, retorna (a UI já está bloqueada);
4. se `draft.statusId` estiver vazio, preenche `statusError` com
   `Selecione um status inicial.` e retorna;
5. chama `boardStore.createIssue(draft.value)` em `try/catch`;
6. só fecha o modal no sucesso.

Não calcular nem enviar `columnId`: o backend deriva a coluna do status. Se a
KAN-93 já tiver mexido no `save()`, preserve a validação de descrição antes do
`createIssue`.

Mapeamento do erro no `catch`:

```js
function applyFailure(failure) {
  if (failure.field === 'summary') {
    summaryError.value = failure.message
    return
  }
  if (failure.field === 'statusId') {
    statusError.value = failure.message
    return
  }
  error.value = failure.message || 'Não foi possível criar a task.'
}
```

Se `descriptionError` já existir (KAN-93), o branch
`failure.field === 'description'` vem antes e preenche `descriptionError`; não
remover nem duplicar.

### 7.2 Contrato de DOM

Adicionar a classe `new-task-modal` ao `.modal-card` do create (não ao
`EntityModal` nem ao `BoardEditor`). Ids explícitos:

| Campo | `id` | Label |
|---|---|---|
| Summary | `create-summary` | `label for="create-summary"` |
| Description | `create-description` | `label for="create-description"` |
| Type | `create-type` | `label for="create-type"` |
| Status | `create-status` | `label for="create-status"` |
| Assignee | `create-assignee` | `label for="create-assignee"` |

Manter a estrutura atual de `<label class="field"><span>…</span><input …>` e
acrescentar `for`/`id`; isso associa explicitamente sem mudar o CSS dos labels.
Se a KAN-93 já tiver trocado a descrição para `<div class="field"><label
for="create-description">`, preserve essa estrutura.

Summary:

```html
<label class="field" for="create-summary">
  <span>Summary</span>
  <input
    id="create-summary"
    ref="summaryInput"
    type="text"
    v-model="draft.summary"
    :aria-invalid="Boolean(summaryError)"
    :aria-describedby="summaryError ? 'create-summary-error' : undefined"
  />
</label>
<p v-if="summaryError" id="create-summary-error" class="error field-error" role="alert">
  {{ summaryError }}
</p>
```

Status:

```html
<label class="field" for="create-status">
  <span>Status</span>
  <select
    id="create-status"
    v-model="draft.statusId"
    :disabled="!initialStatuses.length"
    :aria-invalid="Boolean(statusError)"
    :aria-describedby="statusError ? 'create-status-error' : undefined"
  >
    <option v-for="status in initialStatuses" :key="status.id" :value="status.id">
      {{ status.name }}
    </option>
  </select>
</label>
<p v-if="statusError" id="create-status-error" class="error field-error" role="alert">
  {{ statusError }}
</p>
```

Sem `initial`, antes dos campos:

```html
<p v-if="!initialStatuses.length" id="create-no-initial" class="error field-error" role="alert">
  Este workflow não tem status inicial. A criação de task está bloqueada.
</p>
```

O botão `Create` recebe `:disabled="!initialStatuses.length"`. O select também
fica desabilitado. O aviso é persistente e visível; não usar `alert()` nativo.

O erro geral continua:

```html
<p v-if="error" id="create-form-error" class="error" role="alert">{{ error }}</p>
```

Regras de acessibilidade:

- labels associados por `for`/`id` (não remover o texto visível);
- foco inicial no summary ao abrir;
- erro de summary no summary (`aria-describedby` + `aria-invalid`);
- erro de status no status (`aria-describedby` + `aria-invalid`);
- erro de rede/servidor sem campo no `#create-form-error`, com `role="alert"`;
- mensagens em pt-BR; labels e botões seguem em inglês nesta rodada;
- não adicionar focus trap/ESC/`role="dialog"` completo.

### 7.3 CSS — `frontend/src/styles/main.scss`

Adicionar sem reescrever estilos existentes:

```scss
.new-task-modal .field input,
.new-task-modal .field textarea,
.new-task-modal .field select {
  width: 100%;
  min-width: 0;
}

.new-task-modal .field-error {
  margin: -6px 0 14px;
  padding: 0;
  font-size: 12px;
}
```

O `box-sizing: border-box` global já existe. Não alterar `.modal-card`
(largura `520px`), `.modal` ou o painel da issue. O `textarea` já tem
`resize: vertical` global. Testar 375px, 768px e 1280px: sem overflow
horizontal e sem campo cortado.

## 8. Contrato de testes

Fonte única do aceite: `frontend/tests/new-task-modal.feature` + os testes
abaixo + `frontend/cypress/e2e/new-task-modal.cy.js`.

### 8.1 BDD — `frontend/tests/new-task-modal.feature` (pt-BR)

Cobrir, no mínimo:

1. o modal abre com foco no summary e o select lista apenas `initial`;
2. o default é o primeiro `initial` da ordem do workflow;
3. summary e description ocupam a largura útil em 375/768/1280, sem overflow;
4. criação com `initial` grava a issue e preserva type/assignee;
5. `final`, `transitional`, inexistente e de outro workflow retornam 422 com
   `field=statusId` e nenhuma issue é gravada;
6. status omitido usa o primeiro `initial`;
7. workflow sem `initial` bloqueia a UI e o backend responde 422;
8. `columnId` forjado é ignorado e a coluna é derivada do status;
9. `boardId` inválido responde 422 com `field=boardId`, sem fallback;
10. erro 422 do servidor aparece em região `role="alert"` associada ao campo,
    sem fechar o modal;
11. edição existente (summary/assignee via `PATCH`) continua funcionando.

### 8.2 Backend — `frontend/tests/new-task-status.test.mjs` (`node:test`)

Importar `handle` de `../plugin/fake-backend.js` e `createSeed` de
`../src/data/seed.js`. Usar banco novo por teste e helpers para:

- workflow alternativo (`db.workflows.push`, `db.statuses.push`);
- status inicial extra ligado a uma coluna (`columns[0].statusIds.unshift`);
- status inicial “solto”, sem coluna, para o 422 de configuração inconsistente;
- cálculo do próximo id a partir do próprio seed (não fixar `KANB-7`/`KANB-8`,
  pois a KAN-93 adiciona fixture).

Casos mínimos:

- `POST` com `statusId: 'to-develop'` → 201, status initial, sem `columnId` no
  objeto, ordem da coluna derivada;
- `final` e `transitional` → 422, `field=statusId`, `db.issues.length`
  inalterado;
- inexistente → 422, `field=statusId`;
- de outro workflow → 422, `field=statusId`;
- omitido → 201 com o primeiro `initial` da ordem;
- sem `initial` (converter o único initial em transitional) e status omitido →
  422, `field=statusId`, mensagem contém “inicial”, nenhuma issue;
- `boardId` inexistente/ausente → 422, `field=boardId`, nenhuma issue e nenhum
  fallback para `db.boards[0]`;
- `columnId: 'done'` forjado com status `to-develop` → 201; a issue fica em
  `to-develop`, `columnId` não é copiado e a ordem de `done` não muda;
- status inicial sem coluna → 422, `field=statusId`;
- múltiplos `initial` (o primeiro ligado a uma coluna) → default é o primeiro.

Em todos os 422, provar que `db.issues` não mudou e que uma criação seguinte
recebe o id que a rejeitada usaria.

### 8.3 Cliente — `frontend/tests/client-error.test.mjs`

A KAN-93 já deixou esse arquivo em Red; **não criar um concorrente**. Se ele
existir, acrescentar um caso com `422 { error, field: 'statusId' }` e assertar
`failure.status === 422` e `failure.field === 'statusId'`. Se não existir,
criar com os casos de `statusId`, `boardId`, 404 sem `field` e erro de rede,
seguindo o contrato da §6.

### 8.4 Componente — `frontend/tests/new-task-modal.spec.js` (vitest)

Montar `BoardPage` com `attachTo: document.body`, `useRoute` mockado para
`{ params: { boardId: 'KANB' } }`, `AppIcon`/`BoardColumn`/`IssuePanel`
stubados e `boardStore.state` preenchido a partir de `createSeed()`. Mockar
`../src/api/index.js` com `createIssue`/`fetchIssues`.

Cobrir:

- abrir o modal: `.new-task-modal`, foco em `#create-summary`, opções de
  `#create-status` iguais aos `initial` do seed e default correto;
- summary vazio: erro pt-BR, `role="alert"`, `aria-invalid`, foco no summary,
  `createIssue` não chamado;
- sucesso: payload com `statusId` initial, type e assignee escolhidos, sem
  `columnId`; modal fecha;
- erro 422 `field=statusId`: mensagem do servidor, `#create-status-error` com
  `role="alert"`, `aria-describedby` e `aria-invalid` no select, modal aberto;
- sem `initial`: aviso `#create-no-initial`, select e Create desabilitados,
  `createIssue` não chamado;
- múltiplos `initial`: default no primeiro;
- regressão: type e assignee continuam no payload.

Não medir largura no jsdom; essa prova é do Cypress.

### 8.5 E2E — `frontend/cypress/e2e/new-task-modal.cy.js`

Seguir o padrão de `app.cy.js` para coletar `error`, `unhandledrejection` e
`console.error` e exigir zero. Cobrir:

- abrir o modal, `cy.focused().should('have.id', 'create-summary')`;
- ler `GET /api/workspace`, calcular os `initial` do workflow do board e
  assertar que `#create-status option` tem exatamente esses ids/valores, com
  default no primeiro; nenhum `transitional`/`final`;
- largura: em `cy.viewport(375, 667)`, `(768, 900)` e `(1280, 800)`, comparar
  `getBoundingClientRect().width` de `#create-summary` e `#create-description`
  com a largura útil de `.modal-body` (clientWidth menos padding), tolerância
  de 1px; `document.documentElement.scrollWidth <= clientWidth`; `.modal-card`
  dentro da viewport;
- criar com summary/description e assignee `mai`; conferir o card no column
  Todo e `GET /api/issues` com `statusId` initial e `assigneeId: 'mai'`;
- summary vazio: `#create-summary-error` com `role="alert"`, `aria-invalid` e
  foco no summary; modal aberto;
- erro 422 via `cy.intercept('POST', '/api/issues', { statusCode: 422, body: {
  error: 'a nova task precisa nascer em um status inicial', field: 'statusId' } })`:
  `#create-status-error` com `role="alert"`, `aria-describedby` e
  `aria-invalid`; modal aberto;
- `cy.request` direto: `final`, `transitional`, inexistente, outro workflow e
  `boardId` inválido → 422 com `field` correto e contagem de issues inalterada;
- status omitido → 201 com o primeiro `initial`;
- workflow sem `initial`: `PATCH /api/statuses/to-develop` para
  `type: 'transitional'`, visitar, abrir o modal e assertar o aviso
  `#create-no-initial` + select/Create desabilitados; `afterEach` restaura
  `type: 'initial'`;
- `columnId: 'done'` forjado: 201, issue em `to-develop`; conferir o card em
  Todo e a ordem de Done intacta;
- regressão: `PATCH /api/issues/KANB-1` com summary/assignee continua 200.
- limpar as issues criadas (`DELETE`) e o status de outro workflow no fim.

Usar `invoke('val', texto).trigger('input')` para textos longos, não `.type()`.
Não fixar ids numéricos de issue; o seed pode ganhar a fixture da KAN-93.

### 8.6 CI

O job atual roda `node --test frontend/tests/*.test.mjs`, `npm run build` e
`npm run test:e2e`. Os testes `node:test` desta entrega entram sem mudança. O
vitest depende da KAN-102; enquanto ela não estiver na `main`, rodar
`npm run test:unit -- tests/new-task-modal.spec.js` localmente e anexar a saída
ao PR. Não duplicar o passo de CI nesta entrega.

## 9. Subtarefas e ordem

| Subtarefa | Responsável | Entrega | Depende de |
|---|---|---|---|
| **KAN-120** — backend e cliente | Coder | §5 e §6 | — |
| **KAN-121** — UI do modal | Coder | §7 | — |
| **KAN-122** — BDD, `node:test` e vitest | Kaio | §8.1–8.4 | KAN-120/121 |
| **KAN-123** — E2E e HTTP | Mai | §8.5 | KAN-120/121 |

KAN-120 e KAN-121 tocam arquivos diferentes e podem rodar em paralelo. As
subtarefas de teste são criadas desbloqueadas para permitir o Red; cada autora
só fecha `Done` com a produção correspondente verde. Se a base ainda não
estiver na árvore ao assumir, a subtarefa de teste deve bloquear a si mesma com
o motivo e aguardar o desbloqueio.

## 10. Barra de qualidade

Na `frontend/`:

1. `node --test tests/new-task-status.test.mjs tests/client-error.test.mjs`
   (e, no CI, `node --test frontend/tests/*.test.mjs` a partir da raiz);
2. `npm run test:unit -- tests/new-task-modal.spec.js` (local; CI após KAN-102);
3. `npm run build`;
4. `bash run-e2e.sh --spec cypress/e2e/new-task-modal.cy.js`;
5. `npm run test:e2e` sem regressão de runtime/console.

Regras de entrega:

- sem dependência nova, sem rota nova, sem PHP, sem comentários no código;
- um PR por subtarefa, branch `feat/KAN-94-*`, Conventional Commits
  (`feat(KAN-94): ...`/`test(KAN-94): ...`);
- rebase em `origin/main`; adicionar só os caminhos da subtarefa;
- commitar `docs/KAN-94-guide.md` no primeiro PR de produção que mergear, sem
  reescrever o contrato; as demais subtarefas o referenciam;
- atualizar `docs/STATE.md` no PR que fechar a produção;
- não criar `KAN-*.md` na raiz; este guia é o artefato de referência.

## 11. Riscos e mitigação

| Risco | Mitigação |
|---|---|
| Validação depois do `push` | validar tudo antes; teste prova contagem/ordem intactas |
| `boardId` inválido cair no primeiro board | 422 explícito; teste com board inexistente e ausente |
| `columnId` arbitrário reordenar a coluna errada | ignorar o campo; derivar por `column.statusIds` |
| UI listar status não-inicial | filtrar `type === 'initial'`; E2E compara com o workspace |
| Workflow sem initial | aviso na UI, Create desabilitado, 422 no backend |
| Erro 422 virar promise não tratada | `try/catch` no `save()` + `role="alert"` |
| Conflito com KAN-93 | preservar `FieldLimit`/`descriptionError`/`limitProblem` e o `client.js` compartilhado |
| Largura provada só no jsdom | medir no Cypress em três viewports |
| Teste depender de ids fixos | calcular a partir do seed/workspace |

## 12. Dívidas registradas

- Backend PHP real ainda aceita status arbitrário na criação (item próprio).
- `PATCH /api/issues/:id` continua aceitando status não-inicial (fora de escopo).
- `typeId` não é validado contra o workflow do board.
- Escrita não atômica de `db.json`.
- Focus trap/Escape/`role="dialog"` completo do modal não entram nesta rodada.
