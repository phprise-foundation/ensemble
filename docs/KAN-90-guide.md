# Guia de Implementação — KAN-90: Aba History no painel da issue

Status: RFC aprovada (KAN-90). Autora da etapa: Framework Architect (Lucy).
Este guia é a fonte única do contrato das abas Comments | History e da
timeline mock desta entrega; produção e testes mudam juntos. Base: RFC KAN-90,
viabilidade de Jarvis, ADR de David, `docs/PHILOSOPHY.md`, `docs/GUIDE.md`,
`docs/ARCHITECTURE.md` e os guias KAN-86/KAN-87/KAN-88/KAN-89.

## 1. Objetivo e limites

Adicionar ao painel da issue as abas **Comments** (padrão) e **History**, com
uma timeline de auditoria fictícia, contador apenas em Comments, empty state,
reset ao trocar de issue, teclado/ARIA e selo de protótipo.

Fora de escopo, por decisão da RFC:

- auditoria real ou persistente, eventos no backend PHP e geração de eventos
  em runtime no fake backend;
- comentários na History (continuam só em Comments);
- avatar na timeline (KAN-78/79/89 tratam o componente; o reuso é opcional e
  não é aceite desta entrega);
- migração de ícones para Lucide (KAN-88/KAN-76) e tradução da SPA;
- `fetchIssue(id)` e a integração com o `history` real; a lista `/api/issues`
  não carrega `history` e isso continua valendo aqui.

## 2. Estado de partida e frentes em voo

- `frontend/src/components/IssuePanel.vue` tem 95 linhas, nenhuma aba e a
  seção Comments no fim do painel. O `<aside>` usa `v-if="issue"`, mas o
  componente fica sempre montado em `BoardPage.vue`; um `ref` local sobrevive
  à troca de issue e o reset exige `watch` no id.
- `frontend/src/data/seed.js` não tem `history` em nenhuma issue.
- `frontend/plugin/fake-backend.js::load()` só faz backfill de chaves de topo;
  o `POST /issues` não cria `history`.
- `frontend/src/api/simulation.js` não tem a feature `history`.
- O CI não roda `npm run test:unit`; a KAN-102 cobre esse passo.
- O histórico real (`presentWithHistory`) devolve `{from, to, actor, at}`,
  sem `type` e sem `summary`; `actor` já é uma string pronta. O renderer desta
  entrega precisa tolerar esse contrato.
- Em voo nos mesmos arquivos: KAN-93 (limites e edição de descrição no
  `IssuePanel.vue`), KAN-104 (ícones/ARIA no `IssuePanel.vue`), KAN-111
  (Avatar no `IssuePanel.vue`) e KAN-102 (CI). Rebase em `origin/main` e
  preserve o que já estiver na árvore.
- `frontend/cypress/e2e/icon-standard.cy.js` (KAN-106) afirma
  `.issue-panel .panel-section h3 svg`. Depois das abas o ícone de comentários
  passa a viver na aba; o seletor precisa acompanhar. A KAN-90 não edita o
  arquivo de outra subtask: a KAN-114/Mai ajusta se ele estiver na árvore.

## 3. Feature e modelo mock

### 3.1 Feature `history`

Em `frontend/src/api/simulation.js`, adicionar à lista `FEATURES`:

```js
{ key: 'history', label: 'History' },
```

Com `localStorage` vazio o padrão é `true`, como nas demais features. O selo
da aba usa `boardStore.simulated('history')` (que delega a `isSimulated`), no
formato `badge sim` já usado no app. Desligar a feature **não** troca a fonte
de dados: a timeline mock continua visível e apenas o selo some — decisão do
ADR.

### 3.2 Contrato do evento

Cada evento do mock é um objeto compatível com o `history` real:

| Campo | Obrigatório | Contrato |
|---|---|---|
| `type` | no mock | `created`, `status`, `assignee`, `summary` ou `description`; o evento real pode não ter |
| `actor` | sim | string pronta para exibição; exibir como recebida, sem resolver no store |
| `at` | sim | ISO 8601; formatar com `toLocaleString()` |
| `from` | conforme o tipo | id do status, id do agente (ou `null`) ou texto antigo |
| `to` | conforme o tipo | id do status, id do agente (ou `null`) ou texto novo |
| `summary` | não | resumo curto opcional; quando presente, substitui a frase derivada |

`type` e `summary` são extensões do mock, não contrato do backend real. O
renderer nunca pode quebrar com campo ausente.

### 3.3 Cobertura mínima do seed

Todas as issues do seed ganham a chave `history`; as que não têm eventos usam
`[]`. Pelo menos quatro issues com `history` não vazio, cobrindo os cinco
tipos, e uma issue com `history: []` para o empty state. Sugestão de
distribuição:

| Issue | Eventos |
|---|---|
| `KANB-1` | `created`, `summary`, `description`, `assignee` |
| `KANB-2` | `created`, `status`, `assignee`, `status` (inclui rework) |
| `KANB-3` | `created`, `status`, `status`, `assignee` |
| `KANB-4` | `created`, `status`, `status`, `status`, `assignee` |
| `KANB-5` | `created`, `assignee`, `status` (até `done`) |
| `KANB-6` | `[]` |

Exemplo de `KANB-1` (os demais seguem o mesmo formato, com timestamps
relativos a `now` e anteriores ao `updatedAt`):

```js
history: [
  { type: 'created', actor: 'user', at: new Date(now - hours(30)).toISOString(), summary: 'Issue created' },
  { type: 'summary', actor: 'user', at: new Date(now - hours(29)).toISOString(), from: 'Onboarding', to: 'Frame the onboarding intent', summary: 'Summary narrowed to the onboarding intent' },
  { type: 'description', actor: 'user', at: new Date(now - hours(28)).toISOString(), from: 'Smoother onboarding.', to: 'The client wants a smoother onboarding. Clarify value, risks and open questions.', summary: 'Description gained acceptance criteria' },
  { type: 'assignee', actor: 'Vivy', at: new Date(now - hours(27)).toISOString(), from: null, to: 'vivy' },
],
```

Regras do seed:

1. `from`/`to` de `status` são ids de status existentes (`to-develop`,
   `in-development`, `in-review`, `in-test`, `done`, `canceled`).
2. `from`/`to` de `assignee` são ids de agente ou `null`.
3. `from`/`to` de `summary`/`description` são os textos antigo e novo; o
   `summary` do evento é a frase curta que a UI exibe.
4. `actor` é `user` ou o nome do agente (`Vivy`, `Kaio`, `Alan`, `Mai`).
5. A ordem no seed pode ser cronológica; o componente ordena na exibição.
6. Nenhum evento do tipo `comment`: comentários não entram na History.

## 4. Abas, ARIA e teclado

### 4.1 Posição e DOM

A barra de abas fica onde hoje começa a seção Comments, depois da descrição —
o “rodapé” do painel, sem posição fixa e sem alterar o scroll. O contador
`Comments (n)` aparece apenas na aba Comments; a aba History não tem contador.

```html
<section class="panel-section panel-tabs">
  <div class="tabs" role="tablist" aria-label="Issue details">
    <button
      id="issue-tab-comments"
      ref="commentsTab"
      type="button"
      class="tab"
      role="tab"
      :aria-selected="activeTab === 'comments'"
      aria-controls="issue-panel-comments"
      :tabindex="activeTab === 'comments' ? 0 : -1"
      @click="activeTab = 'comments'"
      @keydown="onTabKeydown"
    >
      <AppIcon name="comment" size="16" />
      Comments ({{ issue.comments.length }})
    </button>
    <button
      id="issue-tab-history"
      ref="historyTab"
      type="button"
      class="tab"
      role="tab"
      :aria-selected="activeTab === 'history'"
      aria-controls="issue-panel-history"
      :tabindex="activeTab === 'history' ? 0 : -1"
      @click="activeTab = 'history'"
      @keydown="onTabKeydown"
    >
      History
      <span v-if="boardStore.simulated('history')" class="badge sim">
        <span class="sim-dot sm"></span> Prototype
      </span>
    </button>
  </div>

  <div
    id="issue-panel-comments"
    role="tabpanel"
    aria-labelledby="issue-tab-comments"
    v-show="activeTab === 'comments'"
  >
    <div class="comments-body"></div>
  </div>

  <div
    id="issue-panel-history"
    role="tabpanel"
    aria-labelledby="issue-tab-history"
    tabindex="0"
    v-show="activeTab === 'history'"
  >
    <IssueHistory :events="issue.history || []" />
  </div>
</section>
```

Observações:

- Usar `v-show` nos dois painéis (não `v-if`): os ids de `aria-controls`
  existem sempre e o texto digitado no composer sobrevive à troca de aba.
- O painel de History recebe `tabindex="0"` para leitura por teclado; o de
  Comments não precisa porque já contém textarea e botão.
- Nenhum `h3` novo; o antigo título “Comments” vira a aba. O ícone `comment`
  continua com 16px, alinhado à escala da KAN-88.

### 4.2 Teclado e roving tabindex

- `ArrowLeft`/`ArrowRight` movem o foco e ativam a aba, com wrap entre as duas
  (conteúdo local, sem latência; APG recomenda ativação automática).
- `Home`/`End` vão para a primeira/última aba.
- `Enter`/`Espaço` ativam a aba focada (o `<button>` já faz isso nativamente;
  o handler explícito garante o contrato no teste de componente).
- A aba selecionada tem `tabindex="0"`; a outra, `tabindex="-1"`. Ao mover o
  foco, o `tabindex` acompanha a seleção.
- O foco é movido no `nextTick` para o `ref` da nova aba.
- Foco visível: `outline: 2px solid var(--accent); outline-offset: 2px;` no
  `:focus-visible`, sem depender de cor de fundo.

### 4.3 Reset ao trocar de issue

```js
const activeTab = ref('comments')

watch(
  () => issue.value?.id,
  () => {
    activeTab.value = 'comments'
  },
)
```

O watch cobre abrir outra issue com o painel montado e fechar/reabrir. A aba
inicial é sempre Comments; não persistir a aba em `localStorage`.

## 5. Timeline

### 5.1 Componente `IssueHistory.vue`

Novo componente em `frontend/src/components/IssueHistory.vue`:

- prop `events` (`Array`, default `() => []`);
- `orderedEvents` computado: cópia ordenada por `Date.parse(b.at) -
  Date.parse(a.at)` (mais recente primeiro), sem mutar a prop;
- empty state `No history yet` quando a lista estiver vazia;
- lista semântica `<ol class="history-list">`; cada `<li>` tem
  `:data-history-type="event.type || 'status'"`, ícone `AppIcon` (14px),
  texto curto, autor e `<time :datetime="event.at">{{ format(event.at) }}</time>`;
- `format` usa `toLocaleString()`, como os comentários; o teste não depende da
  string formatada, apenas do atributo `datetime`;
- autor ausente vira `—`; não resolver `actor` no store.

### 5.2 Frases e ícones

| `type` | Ícone (`AppIcon`) | Frase |
|---|---|---|
| `created` | `plus` | `summary` ou `Issue created` |
| `status` | `transitions` | `Status: {from} → {to}` |
| `assignee` | `agents` | `Assignee: {from} → {to}` |
| `summary` | `edit` | `summary` ou `Summary changed to “{to}”` |
| `description` | `issues` | `summary` ou `Description updated` |
| sem `type`, com `from`/`to` | `transitions` | `Status: {from} → {to}` (contrato real) |
| desconhecido | `transitions` | `summary` ou `Issue updated` |

Resolução de nomes:

- status: `boardStore.status(id)?.name`; sem nome, exibir o id bruto; sem id,
  `—`;
- agente: `boardStore.agent(id)?.name`; sem nome, exibir o id bruto; `null` ou
  vazio, `Unassigned`;
- `from`/`to` de `summary`/`description` são textos; não tentar resolvê-los
  como status/agente.

### 5.3 CSS

Acrescentar em `frontend/src/styles/main.scss`, perto do bloco do painel:

```scss
.tabs {
  display: flex;
  align-items: center;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 8px;
}

.tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  background: transparent;
  border: 0;
  border-bottom: 2px solid transparent;
  color: var(--muted);
  font-size: 13px;
  cursor: pointer;
}

.tab[aria-selected='true'] {
  color: var(--text);
  border-bottom-color: var(--accent);
  font-weight: 600;
}

.tab:hover {
  color: var(--text);
}

.tab:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

.history-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.history-entry {
  display: flex;
  gap: 8px;
  padding: 8px 0;
  border-top: 1px solid var(--border);
}

.history-entry:first-child {
  border-top: none;
}

.history-icon {
  color: var(--muted);
  flex: 0 0 auto;
  margin-top: 2px;
}

.history-text {
  margin: 0;
  font-size: 13px;
  overflow-wrap: anywhere;
}

.history-meta {
  display: flex;
  gap: 6px;
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.history-actor {
  color: var(--accent);
  font-weight: 600;
}
```

Não redesenhar o painel; as abas ocupam o lugar da antiga seção Comments e a
timeline usa a mesma densidade dos comentários. Validar dark/light e 1024px.

## 6. Seed e fake backend

### 6.1 Seed

Adicionar `history` diretamente em cada issue de `frontend/src/data/seed.js`,
com os eventos e a cobertura da seção 3.3. Não criar helper novo se ele não
reduzir repetição; não usar URL externa, dependência ou `Math.random`.

### 6.2 Fake backend

Em `frontend/plugin/fake-backend.js`:

```js
for (const issue of database.issues ?? []) {
  issue.history ??= []
}
```

fazer isso em `load()`, depois do backfill de topo e antes do `return`. A
normalização é aditiva e idempotente: chave presente nunca é sobrescrita, e um
`db.json` antigo sem `history` passa a abrir com a aba vazia em vez de quebrar.

No `POST /issues`, o objeto criado ganha `history: []`. O move, o `PATCH` e o
`POST` de comentário **não** geram eventos: o fake backend apenas persiste o
campo; a auditoria real é item futuro.

### 6.3 Integração real futura

O endpoint de lista (`GET /api/issues`) não devolve `history`; o detalhe
(`GET /api/issues/{id}`) devolve `{from, to, actor, at}`. Quando a auditoria
real chegar, o painel precisará de `fetchIssue(id)` e de novos tipos no
domínio. Registrar como item de continuação; não implementar agora.

## 7. Contrato de testes

### 7.1 BDD — `frontend/tests/issue-history.feature`

Nota do refino: o arquivo já existe untracked, escrito pela QA como Red; a
KAN-115 o adota e estende, não o recria.

Cenários em português do Brasil, com origem KAN-90 e este guia como
autoridade:

1. Comments é a aba padrão; contador só nela; History oculta.
2. Clique em History mostra a timeline em ordem cronológica inversa, com
   ícone, autor, timestamp e resumo; nenhum comentário duplicado.
3. Os cinco tipos aparecem com o ícone e a frase corretos.
4. Empty state `No history yet` na issue sem eventos.
5. Trocar de issue volta para Comments.
6. Teclado: setas ←/→ com wrap, Home/End, Enter/Espaço; roving tabindex,
   `aria-selected`, `aria-controls` e painel associado; foco visível.
7. Selo `Prototype` na aba History quando `history` está simulada; sem selo
   quando desligada, mas com a timeline no lugar.
8. Evento no contrato real (sem `type`, com `from`/`to`/`actor`/`at`) rende
   uma transição de status; id desconhecido aparece bruto.

### 7.2 Vitest — `frontend/tests/issue-history.spec.js`

Montar `IssuePanel` com `boardStore` mockado; o mock expõe `selectedIssue`
como um `ref` real para o teste trocar de issue e observar o reset, além de
`status`, `agent`, `role`, `userTargets`, `isOverdue`, `state.agents`,
`clearSelection`, `moveIssue`, `updateIssue`, `addComment` e `simulated`.
Cobrir:

- default: aba Comments `aria-selected="true"`, painéis visíveis/ocultos e
  contador apenas em Comments;
- clique em History e volta;
- teclado: `ArrowRight`/`ArrowLeft`, `Home`/`End`, `Enter`/`Espaço`, foco no
  elemento correto e `tabindex` 0/-1;
- reset ao mudar `selectedIssue.value`;
- empty state na issue com `history: []`;
- selo condicionado a `simulated('history')`.

Montar `IssueHistory` separadamente para ordem (datas fora de ordem na prop),
ícones por tipo, resolução de status/agente, fallback de id bruto, evento sem
`type` e ausência de comentários. Usar `attachTo: document.body` nos testes de
foco.

### 7.3 Guardas estáticas — `frontend/tests/issue-history.test.mjs`

Nota do refino: o arquivo já existe untracked (7 guardas em Red), escrito pela
QA; a KAN-115 o adota e estende. Ele entra na árvore com a KAN-113 ou a
KAN-115, nunca sozinho.

Ler os arquivos e falhar se:

- `FEATURES` perder `{ key: 'history', label: 'History' }`;
- o seed tiver menos de quatro issues com eventos, deixar de cobrir um dos
  cinco tipos, não tiver uma issue com `[]` ou tiver evento sem `actor`/`at`
  ISO;
- `load()` perder o backfill `issue.history ??= []` ou o `POST /issues`
  perder `history: []`;
- move/PATCH/comentários voltarem a gerar eventos (`history.push`);
- `IssuePanel.vue` perder `role="tablist"`, `role="tab"`, `role="tabpanel"`,
  `aria-selected`, `aria-controls`, `aria-labelledby`, `tabindex`, o `watch`
  de reset ou o selo por `simulated('history')`;
- `IssueHistory.vue` perder a ordenação, o empty state ou o fallback sem
  `type`;
- `main.scss` perder foco visível ou os estilos da timeline;
- houver `v-html` no painel.

Testar o backfill com um `db.json` legado temporário (issue sem `history`) e
`load(filePath)`, provando que a chave é criada e o restante é preservado.

### 7.4 E2E — `frontend/cypress/e2e/issue-history.cy.js`

No navegador real, com `collectErrors` e sem `vite-error-overlay`:

- abrir `KANB-1`: Comments selecionada, contador só nela, History oculta;
  clicar em History mostra a timeline;
- timeline de `KANB-1`/`KANB-2` cobre os cinco tipos, com ícone, autor,
  `<time datetime>` e resumo; comparar os `datetime` para provar a ordem
  inversa; nenhum `.comment` dentro da History;
- empty state em `KANB-6`; fechar o painel, abrir `KANB-2` e ver Comments
  selecionada;
- teclado: foco em `#issue-tab-comments`, setas ←/→ com wrap, Home/End,
  Enter/Espaço, `aria-selected`, `aria-controls`, `aria-labelledby`, roving
  tabindex e foco visível;
- `?sim=1` mostra o selo `Prototype`; `?sim=0` o esconde e a History continua
  funcionando; zerar erros de runtime/console.

Se `frontend/cypress/e2e/icon-standard.cy.js` existir e ainda apontar para
`.panel-section h3 svg`, atualizar o seletor para a aba de Comments, mantendo
o ícone `comment` e o tamanho 16. Não alterar outras asserções de ícones.

### 7.5 Barra de qualidade

Executar, na ordem, a partir de `frontend/`:

```bash
npm ci
node --test tests/*.test.mjs
npm run build
npm run test:unit
npm run test:e2e
```

Sem erro de runtime/console e sem regressão em `app`, `legacy-db-boot`,
`icon-standard`, `topbar-order`, `avatar`, `sidebar`, `drawer` e `copilot`.
Em múltiplos worktrees, usar `E2E_PORT` exclusiva porque o dev server é
`--strictPort`. Se a KAN-102 ainda não estiver na `main`, `npm run test:unit`
não roda no CI: anexar a saída desse comando ao PR e não considerar a entrega
concluída sem ela.

## 8. Ondas e subtasks

| Onda | Item | Assignee | Depende de | Entrega |
|---|---|---|---|---|
| 1 | KAN-113 (KAN-90-A) — produção | Coder | — | feature, seed, fake backend, abas, timeline, ARIA, CSS e este guia |
| 2 | KAN-115 (KAN-90-B) — BDD/vitest/guardas | Kaio | KAN-113 | adotar/estender `.feature` e `.test.mjs`; criar `.spec.js` |
| 2 | KAN-114 (KAN-90-C) — E2E/visual | Mai | KAN-113 | `.cy.js` e auditoria visual |

A produção é a base; as duas frentes de teste podem começar em Red contra este
guia, mas só fecham verdes depois que a produção estiver na árvore. KAN-115 e
KAN-114 mexem em arquivos diferentes e podem correr em paralelo. A KAN-113
commita este guia; as demais o referenciam e não precisam reeditá-lo.

**Nota de quadro:** o guard do `kanban` só deixa o worker modificar o item.
Como as subtasks nascem com `worker` vazio e assignee diferente da autora, a
Framework Architect não consegue pré-marcar `blocked`. O assignee deve se
bloquear ao assumir, com o motivo da coluna “Depende de”; a KAN-90 registra a
ordem para o Tech Lead. Não contornar o guard.

**Nota de quadro (KAN-90):** ao sair de Refined, a KAN-90 vira subtarefa
`Coding` de integração. Quem a assumir não refaz as abas: confere a ordem,
acompanha KAN-113, KAN-114 e KAN-115 e fecha a verificação final. Se assumir
antes da base, bloqueia com o motivo “aguardando KAN-113…KAN-115”.

## 9. Riscos e regras de pouso

- **KAN-93 em voo:** o painel ganha edição de descrição e limites no
  comentário. Preserve esses controles dentro do painel de Comments; não
  reescreva o composer.
- **KAN-104 em voo:** o painel ganha `aria-label` e a escala de ícones. O ícone
  de Comments continua `comment` em 16px; preserve os `aria-label` dos botões
  só de ícone.
- **KAN-111 em voo:** o `.portrait` do `panel-meta` vira `Avatar`; não mexer
  nesse bloco nesta entrega.
- **KAN-106 em voo:** o E2E de ícones procura o ícone de comentários dentro de
  `.panel-section h3`; a aba muda o endereço. Ajustar apenas o seletor, sem
  afrouxar a verificação de Lucide/tamanho.
- **KAN-102 em voo:** não criar outra subtask de CI. Se o passo `test:unit`
  ainda não estiver no workflow, rodar localmente e anexar a saída.
- **Contrato real:** não assumir `type`/`summary` no backend; o renderer
  deriva status de `from`/`to`. Não chamar `fetchIssue` nesta entrega.
- **Dados antigos:** o backfill por issue é obrigatório; sem ele, um
  `db.json` existente mostraria empty state em todas as issues.
- **Idioma:** guia, BDD, comentários de board e commits em pt-BR; rótulos da
  UI em inglês, inclusive `No history yet`, por decisão da RFC.
- **Sem comentários no código.** O porquê vive neste guia e no commit.
- **Commits:** Conventional Commits com o ID da subtask; branch
  `feat/kan-90-history`; PR com o guia e a evidência das suítes.

## 10. Barra de qualidade final

- Abas visíveis; Comments selecionada por padrão; contador apenas nela.
- History com os cinco tipos, ícone, autor, timestamp e resumo; nenhum
  comentário duplicado.
- Empty state correto na issue sem eventos.
- Abrir outra issue volta para Comments.
- Teclado e leitor de tela: foco, setas, `aria-selected`, `aria-controls`,
  painel associado e foco visível.
- Teste de componente (vitest), `.feature` em pt-BR e E2E Cypress verdes.
- Nenhuma dependência nova, nenhuma rota nova, nenhum endpoint PHP.
- Visual sem regressão em dark/light, 1280×800 e 1024px.
