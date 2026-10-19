# Guia de Implementação — KAN-93: limites de descrição e comentário

Status: RFC aprovada (KAN-93). Autora da etapa: Framework Architect (Lucy).
Este guia é a fonte única do contrato de limites, contagem, erro `422`, edição
da descrição e fixture de legado. Produção e testes mudam juntos.

Base: RFC KAN-93, viabilidade de Jarvis
(`var/kanban.local/artifacts/KAN-93-jarvis-viabilidade.md`), integração de David
(`var/kanban.local/artifacts/KAN-93-david-integracao.md`), `docs/PHILOSOPHY.md`,
`docs/GUIDE.md`, `docs/ARCHITECTURE.md` e os guias KAN-56/KAN-90.

Caminho absoluto do repositório: `/home/esdras/src/phprise/multi-agent`
(`phprise`, com dois “p”). O caminho com `phrise` está errado.

## 1. Objetivo e fronteira

O protótipo em `frontend/` deve impedir que uma descrição ou um comentário acima
do limite do canal dos agentes seja gravado sem aviso. O limite nasce no helper
`kanban` (`MAX_DESCRIPTION = 4095`, `MAX_COMMENT = 1023`), não no backend PHP.
O critério de produto é: **o conteúdo que o agente consome não é truncado**.

Entra nesta entrega:

- contador, aviso em 90% e bloqueio de salvar/enviar nos três campos;
- edição da descrição no painel da issue, com `Save` e `Cancel`;
- `422 { error, field }` no fake backend, com validação antes de qualquer
  mutação;
- fixture de legado exibida integralmente, sem truncar;
- acessibilidade por `aria-describedby` e `aria-invalid`;
- testes de unidade, backend, componente e E2E.

Fica fora:

- limite de `summary`, anexos, demais campos;
- validação no backend PHP real e migração dos dados de produção;
- i18n da SPA (o painel segue em inglês; só a mensagem de erro do limite é
  pt-BR, por decisão explícita da RFC);
- auditoria/histórico de edições da descrição;
- escrita atômica do `db.json` (tmp + rename), dívida pré-existente.

## 2. Decisões congeladas

| Tema | Contrato |
|---|---|
| Limite da descrição | `4095` code points |
| Limite do comentário | `1023` code points |
| Contagem | `Array.from(texto).length`; equivalente a `mb_strlen` no PHP |
| Aviso | a partir de `Math.ceil(limite * 0.9)`: `3686` e `921`; só visual |
| Acima do limite | erro visível e botão de salvar/enviar desabilitado |
| Erro do fake backend | `422 { error, field }`, `field` = `description` ou `comment` |
| Erro no cliente | `client.js` anexa `status` e `field` ao `Error`; a UI decide pelo campo |
| Edição da descrição | modo de edição no painel, com `Save` e `Cancel` |
| Legado | conteúdo exibido integral; salvar exige reduzir |
| Fixture | apenas em `createSeed()`; `load()` da KAN-56 não muda |
| Backend PHP | fora do escopo; paridade em item próprio |

A mensagem de erro é única nos dois lados:

```
A descrição excede o limite de 4095 caracteres (4096).
O comentário excede o limite de 1023 caracteres (1024).
```

O `field` identifica o campo; a mensagem nunca é usada para parsing.

### 2.1 Notas de framework (verificadas no projeto)

- **Vue `^3.5.13`**: `<script setup>`, `defineProps` em runtime, `computed`,
  `watch` e `v-model` em `<textarea>`, como nos componentes atuais. Não usar
  `defineModel` nem APIs experimentais.
- **Vite `^6.0.7`**: `frontend/shared/` está dentro da raiz do projeto, então o
  bundle importa o módulo por caminho relativo. O plugin do fake backend roda no
  processo Node do dev server e importa `../shared/limits.js` sem alias do Vite.
- **Cypress `^16.2.0`**: `cy.request` envia JSON e devolve status/body;
  `invoke('val', texto).trigger('input')` atualiza o `v-model` sem digitação
  caractere a caractere; `cy.contains('.card', texto)` localiza a issue.
- **Node 24 (CI)**: `node --test`, `fetch` e `Response` globais; `Array.from`
  conta code points. Os testes `.test.mjs` ficam fora do vitest pelo
  `test.exclude` já existente em `vitest.config.js`.
- **Vitest `^5.0.5` + jsdom**: testes de componente; montar com `@vue/test-utils`
  e stubar `AppIcon`, como em `tests/App.spec.js` e `tests/entity-creation-verb.spec.js`.
- Nenhuma dependência nova é necessária.

## 3. Estado de partida e frentes em voo

Arquivos que esta entrega toca:

- `frontend/src/views/BoardPage.vue` (190 linhas): modal de criação; textarea de
  descrição sem limite.
- `frontend/src/components/IssuePanel.vue` (95 linhas): descrição só como texto;
  textarea de comentário sem limite.
- `frontend/src/api/client.js` (15 linhas): descarta status e corpo do erro.
- `frontend/plugin/fake-backend.js` (281 linhas): nenhuma validação de tamanho;
  o middleware descarta `field`.
- `frontend/src/data/seed.js` (139 linhas): seis issues, sem fixture de legado.
- `frontend/src/styles/main.scss`: estilos de `.field`, `.error`, painel e
  comentários.

Frentes que disputam os mesmos arquivos:

- **KAN-102** (David): adiciona `npm run test:unit` ao CI. Se ainda não estiver
  na `main`, rodar o vitest localmente e registrar a saída.
- **KAN-104** (Coder): ícones e ARIA em `BoardPage.vue` e `IssuePanel.vue`.
- **KAN-111** (Coder): `Avatar` em `IssuePanel.vue`.
- **KAN-113/114/115** (KAN-90): abas History em `IssuePanel.vue` e testes do
  painel.
- **KAN-94** (RFC aprovada): modal de nova task full-width e status inicial em
  `BoardPage.vue`.

Regra de convivência: rebase em `origin/main` antes de codar; preservar o que já
estiver na árvore; adicionar apenas os caminhos da própria subtarefa (nunca
`git add .`); não reescrever o contrato deste guia. Se a KAN-113 já tiver
transformado a seção de comentários em abas, o composer e o contador entram
dentro da aba Comments, sem duplicar o painel.

## 4. Fundação compartilhada

### 4.1 `frontend/shared/limits.js` (novo)

Módulo ESM puro: sem `window`, `document`, `process`, `import.meta.env` ou
dependência de Vue. Importável pelo bundle, pelo fake backend (processo do dev
server), pelo vitest e pelo `node:test`.

```js
export const DESCRIPTION_LIMIT = 4095
export const COMMENT_LIMIT = 1023

export function countCharacters(value) {
  return Array.from(String(value ?? '')).length
}

export function warningThreshold(limit) {
  return Math.ceil(limit * 0.9)
}

export function nearLimit(length, limit) {
  return length >= warningThreshold(limit) && length <= limit
}

export function limitExceeded(length, limit) {
  return length > limit
}

export function limitError(field, length, limit) {
  const label = field === 'comment' ? 'O comentário' : 'A descrição'
  return `${label} excede o limite de ${limit} caracteres (${length}).`
}
```

Regras:

- `countCharacters` conta code points, não unidades UTF-16: `'😀'` vale `1`;
  `'e\u0301'` vale `2` (grafemas não são agrupados).
- `nearLimit` é falso acima do limite; nesse caso o erro tem precedência.
- `limitError` só conhece `description` e `comment`; não criar um terceiro
  campo nesta entrega.
- Nada de mensagens de UI além da mensagem de erro; o rótulo do aviso
  (`Approaching the limit`) fica no componente.

### 4.2 `frontend/src/api/client.js`

Preservar a assinatura de `request` e anexar os dados do erro:

```js
if (!response.ok) {
  const error = new Error((data && data.error) || `${response.status} ${response.statusText}`)
  error.status = response.status
  error.field = (data && data.field) || null
  throw error
}
```

- `status` e `field` são propriedades do `Error`, não um novo formato de
  retorno.
- A UI nunca faz parsing de `error.message` para descobrir o campo.
- Erro de rede continua sendo o `TypeError` do `fetch`, sem `status`/`field`.

## 5. Fake backend

Em `frontend/plugin/fake-backend.js`:

```js
import {
  COMMENT_LIMIT,
  DESCRIPTION_LIMIT,
  countCharacters,
  limitError,
} from '../shared/limits.js'

function limitProblem(field, value, limit) {
  const length = countCharacters(value)
  if (length <= limit) return null
  return { error: limitError(field, length, limit), field, status: 422 }
}
```

Validar **antes** de qualquer mutação, nos três caminhos:

1. `POST /api/issues`: no início do bloco, antes de `boardOf`, `nextIssueId` e
   `push`. `body.description` ausente conta como `''`.
2. `PATCH /api/issues/:id`: localizar a issue (404 primeiro), validar
   `body.description` quando `!== undefined`, e só então `Object.assign`. Um
   patch com descrição acima do limite não pode alterar `assigneeId`, `id` nem
   `updatedAt`.
3. `POST /api/issues/:id/comments`: localizar a issue (404 primeiro), validar
   `body.body` antes do `push` e antes de atualizar `updatedAt`.

O middleware passa a devolver o campo:

```js
res.end(JSON.stringify(result.error ? { error: result.error, field: result.field } : result.data))
```

`JSON.stringify` omite `field` quando ele é `undefined`; os erros 404/409/500
continuam com o corpo antigo. O middleware já não persiste quando há erro; a
validação antes da mutação garante que o processo em memória também não muda.
Nada de truncar: acima do limite é rejeição, nunca corte silencioso.

O helper `limitProblem` é privado do plugin; não exportar sem necessidade.

## 6. Interface

Os dois arquivos importam `FieldLimit` e as constantes/helpers de
`../../shared/limits.js`; `IssuePanel.vue` passa a importar `watch` do Vue.

### 6.1 `frontend/src/components/FieldLimit.vue` (novo)

Componente pequeno, usado nos três campos. Props: `id` (String, obrigatória),
`text` (String, padrão `''`), `limit` (Number, obrigatório), `field` (String,
`description` ou `comment`) e `error` (String, padrão `''`).

Comportamento:

- `length = countCharacters(text)`;
- `over = limitExceeded(length, limit)`;
- `warning = nearLimit(length, limit)`;
- `message = error || (over ? limitError(field, length, limit) : '')`;
- raiz `<div :id="id" class="field-limit" :class="{ warning, over }">`;
- contador sempre visível: `{{ length }}/{{ limit }}`;
- aviso `Approaching the limit` quando `warning`;
- mensagem de erro sempre no DOM, com `role="status"` e texto quando houver
  `message` (região viva para leitor de tela).

O `id` é o alvo do `aria-describedby` do campo; o mesmo elemento contém contador,
aviso e erro. O componente não conhece o store, não faz requisição e não decide
salvar.

### 6.2 Criação (`BoardPage.vue`)

Importar `DESCRIPTION_LIMIT`, `countCharacters`, `limitError`,
`limitExceeded` de `../../shared/limits.js` e `FieldLimit` de
`../components/FieldLimit.vue`.

Estado/derivados:

```js
const descriptionError = ref('')
const descriptionLength = computed(() => countCharacters(draft.value.description))
const descriptionOver = computed(() => limitExceeded(descriptionLength.value, DESCRIPTION_LIMIT))
```

`openCreate()` zera `descriptionError`. Um
`watch(() => draft.value.description, ...)` limpa o erro ao digitar. `save()`:

1. mantém a validação de `summary` como está;
2. zera `descriptionError`;
3. se `descriptionOver`, preenche `descriptionError` com `limitError(...)` e
   retorna sem chamar o store;
4. envolve `boardStore.createIssue` em `try/catch`: se `failure.field ===
   'description'`, mostra `failure.message` em `descriptionError`; caso
   contrário, mantém o erro geral em `error`;
5. só fecha o modal no sucesso.

Template da textarea:

```html
<div class="field">
  <label for="create-description">Description</label>
  <textarea
    id="create-description"
    rows="4"
    v-model="draft.description"
    aria-describedby="create-description-limit"
    :aria-invalid="descriptionOver || Boolean(descriptionError)"
  ></textarea>
  <FieldLimit
    id="create-description-limit"
    field="description"
    :text="draft.description"
    :limit="DESCRIPTION_LIMIT"
    :error="descriptionError"
  />
</div>
```

O `<label for>` mantém o nome acessível do campo como apenas `Description`; o
contador e o erro ficam fora do label, ligados por `aria-describedby`. Não
envolver o `FieldLimit` em um `<label>`.

O botão `Create` recebe `:disabled="descriptionOver"`. A mensagem geral
`<p v-if="error" class="error">` continua para o resumo e para falhas que não
sejam de descrição. Não desabilitar o botão por resumo vazio: a mensagem
`Summary is required` continua sendo o comportamento atual.

### 6.3 Edição da descrição (`IssuePanel.vue`)

Estado local:

```js
const editingDescription = ref(false)
const descriptionDraft = ref('')
const descriptionError = ref('')
const descriptionLength = computed(() => countCharacters(descriptionDraft.value))
const descriptionOver = computed(() => limitExceeded(descriptionLength.value, DESCRIPTION_LIMIT))
```

Funções:

- `startDescriptionEdit()`: copia `issue.value.description ?? ''`, limpa o erro
  e liga o modo de edição;
- `cancelDescriptionEdit()`: desliga a edição, limpa draft e erro; a descrição
  original permanece intacta;
- `saveDescription()`: guarda contra `descriptionOver`, chama
  `boardStore.updateIssue(id, { description: descriptionDraft.value })` e, no
  sucesso, desliga a edição; no `catch`, mostra `failure.message`;
- `watch(() => issue.value?.id, ...)`: ao trocar ou fechar a issue, desliga a
  edição e limpa draft/erros; o composer de comentário continua como está;
- `watch(descriptionDraft, ...)`: limpa o erro ao digitar.

Template:

```html
<section class="panel-section">
  <div class="section-heading">
    <h3>Description</h3>
    <button
      v-if="!editingDescription"
      type="button"
      class="link-button"
      @click="startDescriptionEdit"
    >
      Edit
    </button>
  </div>

  <template v-if="editingDescription">
    <textarea
      v-model="descriptionDraft"
      rows="4"
      aria-label="Description"
      aria-describedby="issue-description-limit"
      :aria-invalid="descriptionOver || Boolean(descriptionError)"
    ></textarea>
    <FieldLimit
      id="issue-description-limit"
      field="description"
      :text="descriptionDraft"
      :limit="DESCRIPTION_LIMIT"
      :error="descriptionError"
    />
    <div class="section-actions">
      <button type="button" class="button ghost" @click="cancelDescriptionEdit">Cancel</button>
      <button type="button" class="button" :disabled="descriptionOver" @click="saveDescription">Save</button>
    </div>
  </template>

  <p v-else class="body">{{ issue.description || 'No description' }}</p>
</section>
```

O texto legado é exibido integralmente: `white-space: pre-wrap` já existe e não
há truncamento por CSS. `Edit` só aparece no modo de leitura.

### 6.4 Comentário (`IssuePanel.vue`)

O payload salvo é `comment.trim()`, como hoje; a contagem deve ser feita sobre
esse mesmo valor:

```js
const commentPayload = computed(() => comment.value.trim())
const commentLength = computed(() => countCharacters(commentPayload.value))
const commentOver = computed(() => limitExceeded(commentLength.value, COMMENT_LIMIT))
const commentError = ref('')
```

`submitComment()`:

1. retorna se `commentPayload` for vazio (comportamento atual);
2. guarda contra `commentOver` e preenche `commentError` com `limitError(...)`;
3. chama `boardStore.addComment` em `try/catch`; no sucesso limpa `comment` e
   `commentError`; no erro mostra `failure.message`;
4. `watch(comment, ...)` limpa `commentError` ao digitar.

Template:

```html
<textarea
  v-model="comment"
  rows="3"
  placeholder="Write a comment..."
  aria-label="Comment"
  aria-describedby="issue-comment-limit"
  :aria-invalid="commentOver || Boolean(commentError)"
></textarea>
<FieldLimit
  id="issue-comment-limit"
  field="comment"
  :text="commentPayload"
  :limit="COMMENT_LIMIT"
  :error="commentError"
/>
<button class="button" :disabled="commentOver" @click="submitComment">Comment</button>
```

Não desabilitar por comentário vazio: o envio vazio já é um no-op silencioso.

### 6.5 CSS (`frontend/src/styles/main.scss`)

Adicionar, sem reescrever estilos existentes:

```scss
.field-limit {
  display: block;
  margin-top: 4px;
  font-size: 12px;
  color: var(--muted);
}

.field > label {
  display: block;
  margin-bottom: 4px;
  font-size: 12px;
  color: var(--muted);
}

.field-limit.warning {
  color: var(--warn);
}

.field-limit.over,
.field-limit-error {
  color: var(--danger);
}

.field-limit-count {
  margin-right: 8px;
  font-variant-numeric: tabular-nums;
}

.field-limit-warning,
.field-limit-error {
  display: block;
}

textarea[aria-invalid='true'] {
  border-color: var(--danger);
}

.section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.section-heading h3 {
  margin: 0 0 8px;
}

.section-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 8px;
}

.link-button {
  padding: 0;
  color: var(--accent);
  font-size: 12px;
}

.link-button:hover {
  text-decoration: underline;
}

.button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.panel-section .body,
.comment p {
  overflow-wrap: anywhere;
}
```

`overflow-wrap: anywhere` evita que a fixture de legado (sequência longa sem
espaços) estoure o painel. Conferir os temas claro/escuro e 1024px.

O seletor existente `.field > span` estiliza os rótulos dos campos antigos; o
campo novo de descrição usa `<div class="field">` com `<label for>`, então a
regra `.field > label` replica o mesmo estilo. O `FieldLimit` é um `div` e não é
afetado por `.field > span`.

## 7. Fixture de legado

Decisão: **opção 1 do ADR de David** — a fixture vive apenas em `createSeed()`.
Não alterar `load()` nem o contrato da KAN-56 (“chave presente nunca é
sobrescrita”). O `run-e2e.sh` regenera o banco a partir de `createSeed()`, então
o E2E e o CI veem a fixture. Um desenvolvedor com `.data/db.json` antigo só a vê
depois de remover o arquivo; registrar isso no PR.

Em `frontend/src/data/seed.js`, no topo do módulo:

```js
const legacyDescription = `LEGACY-DESCRIPTION-START\n${'D'.repeat(4200)}\nLEGACY-DESCRIPTION-END`
const legacyComment = `LEGACY-COMMENT-START\n${'C'.repeat(1100)}\nLEGACY-COMMENT-END`
```

Nova issue no array `issues`:

```js
{
  id: 'KANB-7', boardId: 'KANB', typeId: 'task', summary: 'Legacy issue over the limits',
  description: legacyDescription,
  statusId: 'to-develop', assigneeId: 'vivy', reporter: 'user', worker: '', order: 1,
  flags: [], history: [], comments: [
    { id: 'c-legacy', author: 'user', body: legacyComment, at: new Date(now - hours(25)).toISOString() },
  ],
  createdAt: new Date(now - hours(25)).toISOString(), updatedAt: new Date(now - hours(25)).toISOString(),
},
```

Regras:

- `KANB-7` mantém o padrão numérico; `nextIssueId` passa a devolver `KANB-8` e
  nenhum teste atual depende de `KANB-7`.
- A descrição e o comentário devem ter marcadores de início e fim para o E2E
  provar que o conteúdo inteiro chegou ao DOM, não só o começo.
- Ambos excedem os limites: descrição > 4095, comentário > 1023.
- O item entra em `to-develop`, com `order: 1`, depois da `KANB-1`.
- O fake backend não trata legado de forma especial: `GET` devolve o texto
  inteiro; só salvar descrição/comentário passa pela validação.
- Mover, atribuir e editar outros campos da `KANB-7` continuam funcionando.
- Incluir `history: []` na `KANB-7` para manter o contrato da KAN-113 (“toda
  issue do seed tem a chave”); não gerar eventos de limite.

## 8. Contrato de testes

Fonte única do aceite: `frontend/tests/description-comment-limits.feature` +
os testes abaixo + `frontend/cypress/e2e/description-comment-limits.cy.js`.

Estado do contrato (Red): Alexya já entregou na árvore
`description-comment-limits.feature` (15 cenários), `limits-backend.test.mjs` e
`client-error.test.mjs`, ainda não commitados. A KAN-118 revisa e commita esses
três arquivos, completa `limits.test.mjs` e os specs vitest e **não os recria**.
`limits.test.mjs`, `field-limit.spec.js` e `issue-limits.spec.js` ainda não
existem.

### 8.1 BDD (`description-comment-limits.feature`, pt-BR)

Cenários mínimos:

1. descrição com 4095 code points é aceita e gravada;
2. descrição com 4096 é rejeitada com `field=description`, sem gravar;
3. comentário com 1023 é aceito; com 1024 é rejeitado com `field=comment`,
   sem gravar;
4. contador em code points (emoji) e aviso a partir de 3686/921;
5. `PATCH` acima do limite não altera outros campos nem `updatedAt`;
6. edição da descrição: `Save` grava, `Cancel` descarta;
7. legado exibido integral; editar exige reduzir; outras ações seguem normais;
8. `aria-describedby` nos três campos e `aria-invalid` quando aplicável;
9. resposta HTTP `422` com `{ error, field }` e registro anterior intacto.

### 8.2 Unidade (`frontend/tests/limits.test.mjs`, `node:test`)

- constantes `4095`/`1023`;
- `countCharacters` para ASCII, emoji (par substituto) e combinante;
- `warningThreshold`/`nearLimit`: 3685/3686/4095/4096 e 920/921/1023/1024;
- `limitExceeded`;
- `limitError` para os dois campos.

### 8.3 Backend (`frontend/tests/limits-backend.test.mjs`, `node:test`)

Importar `handle` de `../plugin/fake-backend.js` e `createSeed` de
`../src/data/seed.js`. Cobrir:

- `POST /issues`: 4095 aceito (`201`); 4096 rejeitado (`422`,
  `field=description`), sem `push` e sem consumir id;
- `PATCH /issues/KANB-1`: 4095 aceito; 4096 rejeitado, sem alterar description,
  assignee, id nem `updatedAt`;
- `POST /issues/KANB-1/comments`: 1023 aceito; 1024 rejeitado, sem `push` e sem
  atualizar `updatedAt`;
- code points: 4095 emoji aceitos, 4096 rejeitados;
- `GET /issues/KANB-7` devolve a fixture inteira, com os marcadores de fim.

### 8.4 Cliente (`frontend/tests/client-error.test.mjs`, `node:test`)

Stubar `globalThis.fetch` devolvendo `422 { error, field }` e chamar `request`
de `../src/api/client.js`. Assertar que o `Error` lançado tem `status === 422` e
`field === 'description'`; restaurar o `fetch` no `finally`.

### 8.5 Componente (`frontend/tests/field-limit.spec.js` e
`frontend/tests/issue-limits.spec.js`, vitest)

`field-limit.spec.js`: contador, warning, erro calculado, erro vindo do prop,
`role="status"` e contagem de emoji.

`issue-limits.spec.js`: montar `IssuePanel` com `boardStore.state.issues` e
`selectedIssueId`; mockar `../src/api/index.js` com `updateIssue`/`addComment`.
Cobrir: `Edit` abre com a descrição atual; acima do limite mostra erro e
desabilita `Save`; `Cancel` restaura; salvar abaixo do limite chama
`updateIssue` com o texto novo; comentário acima do limite desabilita e não
chama `addComment`; comentário válido é enviado com `trim()`; trocar de issue
sai do modo de edição. Usar `flushPromises` e stubar `AppIcon`.

### 8.6 E2E (`frontend/cypress/e2e/description-comment-limits.cy.js`)

Cobrir no navegador:

- criação: 3685 sem aviso, 3686 com aviso, 4095 com `Create` habilitado, 4096
  com erro e `Create` desabilitado; criar uma issue de 4095 e conferir o texto
  integral no painel;
- edição: `Edit` abre; `Save` grava; `Cancel` descarta; `4096` bloqueia;
- comentário: `1024` bloqueia com erro; `1023` envia e aparece;
- legado `KANB-7`: descrição e comentário inteiros (marcadores `*-END`),
  `Save`/`Comment` desabilitados acima do limite, reduzir habilita; trocar o
  assignee continua funcionando;
- `aria-describedby` nos três ids (`create-description-limit`,
  `issue-description-limit`, `issue-comment-limit`; o de edição após clicar em
  `Edit`) e `aria-invalid`;
- `cy.request` direto: `POST /api/issues` com 4096 → `422` com
  `field=description`; `PATCH /api/issues/KANB-1` com 4096 e `assigneeId`
  → `422` sem alterar nenhum dos dois; `POST .../comments` com 1024 → `422` com
  `field=comment`; conferir por `GET` que nada mudou;
- coletar `error`, `unhandledrejection` e `console.error` e exigir zero, como
  em `app.cy.js`.

Preencher textos longos com `invoke('val', texto).trigger('input')`, não com
`.type()` de milhares de caracteres. Não fixar o total exato da fixture: ler o
contador e comparar com `> 4095`/`> 1023`. O banco do fake persiste durante a
spec: cada cenário usa uma issue própria ou restaura o texto que editou.

### 8.7 CI

O job atual roda `node --test frontend/tests/*.test.mjs`, `npm run build` e
`npm run test:e2e`; os testes `node:test` desta entrega já entram sem mudança.
O vitest depende da KAN-102. Enquanto ela não estiver na `main`, rodar
`npm run test:unit` localmente e anexar a saída ao PR. Não duplicar o passo de CI
nesta entrega.

## 9. Subtarefas e ordem

| Subtarefa | Responsável | Entrega | Depende de |
|---|---|---|---|
| **KAN-116** — fundação, fake backend e fixture | Coder | §4, §5, §7 | — |
| **KAN-117** — contadores, bloqueio e edição | Coder | §6 | KAN-116 |
| **KAN-118** — BDD, `node:test` e vitest | Kaio | §8.1–8.5 (revisar o Red de Alexya; completar `limits.test.mjs` e vitest) | KAN-116/117 |
| **KAN-119** — E2E e HTTP | Mai | §8.6 | KAN-117 |

As subtarefas de teste podem nascer em Red, mas só fecham verdes depois da
produção correspondente. Se a base ainda não estiver na árvore ao assumir,
bloquear a própria subtarefa com o motivo e aguardar o desbloqueio.

## 10. Barra de qualidade

Na `frontend/`:

1. `node --test tests/limits.test.mjs tests/limits-backend.test.mjs tests/client-error.test.mjs`
   (e, no CI, `node --test frontend/tests/*.test.mjs` a partir da raiz);
2. `npm run test:unit` (vitest) — local; CI após KAN-102;
3. `npm run build`;
4. `bash run-e2e.sh --spec cypress/e2e/description-comment-limits.cy.js`;
5. `npm run test:e2e` sem regressão de runtime/console.

Regras de entrega:

- sem dependência nova, sem rota nova, sem PHP, sem comentários no código;
- um PR por subtarefa, branch `feat/kan-93-*`, Conventional Commits
  (`feat(KAN-93): ...`/`test(KAN-93): ...`);
- rebase em `origin/main`; adicionar só os caminhos da subtarefa;
- commitar `docs/KAN-93-guide.md` junto com a produção (é documentação da
  entrega e não conta como arquivo de produção), sem reescrever o contrato;
- atualizar `docs/STATE.md` no PR que fechar a produção;
- não criar `KAN-*.md` na raiz; este guia é o artefato de referência.

## 11. Fora de escopo e dívidas

- **Paridade no backend PHP**: o PHP real não valida 4095/1023; o helper
  `kanban` trunca ao ler. Abrir item próprio, com o critério “o agente não
  recebe conteúdo truncado” e o mesmo contrato de code points. Não implementar
  aqui.
- **Escrita atômica do `db.json`**: `writeFileSync` direto pode corromper o
  arquivo se o processo morrer no meio; risco pré-existente, aceitável no
  protótipo. Registrar como dívida, não corrigir nesta entrega.
- **i18n**: o painel continua em inglês; a mensagem de erro do limite é pt-BR
  por decisão da RFC. Tradução geral é item separado.
- **Histórico de edição da descrição**: a edição não gera evento; a KAN-90
  cobre a timeline mock e não muda este contrato.
- **Limite de `summary` e anexos**: não fazem parte do canal dos agentes nesta
  rodada.

## 12. Riscos e mitigação

| Risco | Mitigação |
|---|---|
| Contagem do navegador divergir do `mb_strlen` | code points nos dois lados; testes com emoji e combinante |
| UI mais rígida que o backend real | mensagem de erro explica o limite; paridade em item próprio |
| Conflito com KAN-104/111/113/94 | rebase, preservar a árvore e não editar arquivo de outra frente |
| Fixture invisível em banco antigo | documentar; E2E/CI regeneram; `load()` intocado |
| Erro 422 sem campo na UI | `client.js` preserva `status`/`field`; teste do cliente |
| Gravação parcial em memória | validação antes de `push`/`Object.assign`; testes de backend |
| Vitest fora do CI | `node:test` cobre unidade/backend; KAN-102 cobre componente |
