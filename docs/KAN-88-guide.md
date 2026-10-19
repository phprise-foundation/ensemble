# Guia de Implementação — KAN-88: ícones padronizados com Lucide

Status: RFC aprovada (KAN-88). Autora da etapa: Framework Architect (Lucy).
Este guia é a fonte única do contrato de ícones da SPA; produção e testes mudam
juntos. Base: RFC KAN-88, viabilidade de Jarvis, ADR de David,
`docs/PHILOSOPHY.md`, `docs/GUIDE.md`, `docs/ARCHITECTURE.md` e os guias
KAN-18/KAN-62/KAN-63/KAN-83.

## 1. Objetivo e limites

Trocar o dicionário manual de paths do `AppIcon.vue` por
`lucide-vue-next@1.0.0`, preservando a API `<AppIcon name="..." size="..." />`,
sem mudar cores, temas, rotas, textos ou comportamento dos botões. A entrega
também unifica a semântica das ações, fixa a escala de tamanhos, garante nome
acessível em botões só de ícone e adiciona uma guarda automatizada.

Fora de escopo: redesenhar a identidade visual; trocar logos, retratos dos
agentes ou ilustrações; migrar para TypeScript; adotar outra biblioteca de UI;
alterar layout, rotas ou ações.

## 2. Estado de partida

- `frontend/src/components/AppIcon.vue` tem 24 entradas manuais, `stroke-width`
  1.8 e `viewBox 0 0 24 24`; hoje é o único `<svg>` de UI em `frontend/src`.
- São 59 chamadas de `AppIcon` em 10 arquivos, com tamanhos 13/14/15/16/18.
- `edit` (lápis) e `profiles` (estrela) são usados para a mesma ação de editar;
  `close` (X) é usado para excluir. Excluir, editar e fechar precisam ser
  distinguíveis por ícone e por nome acessível.
- Vários botões só de ícone têm apenas `title`; `title` não é nome acessível
  confiável. Os fechamentos de modal/painel e as ações do sidebar entram na
  varredura de `aria-label`.
- Em voo: KAN-18 (console/`App.vue`), KAN-83 (rail/`AppSidebar.vue`) e KAN-87
  (topbar/`App.vue`) tocam os mesmos arquivos. As subtasks de varredura ficam
  bloqueadas até essas frentes aterrissarem.

## 3. Dependência e contrato do `AppIcon`

### 3.1 Dependência

- Pacote: `lucide-vue-next`, versão **exata** `1.0.0` (sem `^` nem `~`), em
  `dependencies`. É uma dependência do bundle da SPA, não um serviço externo.
- Atualizar `frontend/package.json` e `frontend/package-lock.json` no mesmo
  commit. `npm ci` precisa passar.
- Apenas `frontend/src/components/AppIcon.vue` importa `lucide-vue-next`.
  Usar imports nomeados (`import { Pencil, Trash2 } from 'lucide-vue-next'`),
  nunca `import * as icons` nem import dinâmico: isso infla o bundle.
- Registrar a escolha, a versão e a escala em `frontend/README.md`.

### 3.2 API preservada

```vue
<AppIcon name="edit" size="14" />
```

- Props: `name` (String, obrigatória) e `size` (Number|String, default 18).
- Nenhuma prop nova obrigatória; `class` e demais atributos continuam caindo no
  `<svg>` raiz.
- O componente tem um único nó raiz em tempo de execução (`v-if`/`v-else`), para
  o fallthrough de atributos continuar funcionando.
- `stroke-width="2"`, `fill="none"`, `stroke="currentColor"`,
  `stroke-linecap="round"`, `stroke-linejoin="round"` e `aria-hidden="true"`.
- Nome desconhecido não renderiza ícone; a guarda estática impede que isso
  chegue à árvore.

### 3.3 Mapa semântico (fonte única)

| `name` | Componente Lucide | Uso |
|---|---|---|
| `board` | `Kanban` | board na navegação |
| `workspace` | `AppWindow` | workspace |
| `folder` | `Folder` | source/pasta |
| `layers` | `Layers` | workflow |
| `agents` | `Users` | team/agents |
| `roles` | `Shield` | roles |
| `profiles` | `Star` | profiles (navegação) |
| `statuses` | `Clock` | statuses |
| `transitions` | `ArrowRight` | transitions |
| `issues` | `FileText` | issue types |
| `experiences` | `Bookmark` | experiences |
| `plus` | `Plus` | adicionar |
| `chevron` | `ChevronRight` | submenu |
| `sun` | `Sun` | tema claro |
| `moon` | `Moon` | tema escuro |
| `close` | `X` | fechar/descartar |
| `delete` | `Trash2` | excluir |
| `edit` | `Pencil` | editar |
| `check` | `Check` | salvar/confirmar |
| `menu` | `Menu` | menu mobile |
| `comment` | `MessageSquare` | comentários |
| `warning` | `TriangleAlert` | aviso/aging |
| `sidebar` | `PanelLeft` | grade esquerda |
| `copilot` | `PanelRight` | grade direita |
| `console` | `SquareTerminal` | grade inferior |
| `smartboard` | marca custom | logo da sidebar |

`columns`, `drag` e `flask` não têm chamada hoje e saem do mapa. `experiences`
fica porque o rail da KAN-83 já o consome.

Regras semânticas:

1. Toda ação de editar usa `edit` (`Pencil`). `profiles` fica reservado para a
   página/nav de Profiles.
2. Toda ação de excluir usa `delete` (`Trash2`). `close` (`X`) significa apenas
   fechar/descartar.
3. Adicionar usa `plus`; salvar/confirmar usa `check`.
4. Um novo ícone entra primeiro no mapa e na guarda; nunca voltar a path manual
   inline.

### 3.4 Logo da sidebar

`name="smartboard"` é marca, não ícone de UI. Permanece custom dentro do
`AppIcon.vue` (único `<svg>` bruto permitido no componente), com o mesmo
desenho e `stroke-width="2"` para alinhar o traço. Não migrar para Lucide e não
mover para outro componente nesta fatia. O logo, os retratos e as ilustrações
ficam fora da guarda de ícones de UI.

## 4. Escala de tamanhos

| Contexto | `size` |
|---|---|
| Topbar (menu, sidebar, copilot, tema) e ícones de grupo do sidebar | 18 |
| Folhas do sidebar, chevrons e ações compactas de linha (editar/excluir/adicionar) | 14 |
| Ações padrão com texto (New/Add/Save/Create, aviso, comentários) | 16 |
| Fechamento de cabeçalho de modal/painel | 20 |
| Flag de aging no card | 14 |

- Só 14/16/18/20. O default do componente é 18; chamadas sem `size` só são
  aceitáveis no topbar/grupo.
- Não redimensionar por CSS; usar a prop `size`.
- O ícone do rail colapsado (KAN-83) é 18; a árvore continua em 14.

## 5. Varredura de produção

### 5.1 Sidebar (`AppSidebar.vue`)

- Grupo (Workspaces, Boards, Workflows, Teams, Sources): 15 → 18.
- Folhas, chevrons e ações de linha: 13/14/15 → 14.
- Editar workspace/board/workflow/team/source: `edit` (14).
- Excluir source: `delete` (14), nunca `close`.
- Adicionar workspace/board/workflow/team/source: `plus` (14).
- Todo botão só de ícone ganha `aria-label` igual ao `title`; se não houver
  `title`, criar o par com o mesmo texto.

### 5.2 Páginas, modais e painel

| Arquivo | Mudanças |
|---|---|
| `App.vue` | tema ganha `aria-label` dinâmico; topbar segue 18 |
| `BoardPage.vue` | New task `plus` 16; fechar modal `close` 20 + `aria-label`; Create `check` 16 |
| `IssuePanel.vue` | fechar painel `close` 20 + `aria-label`; warning/comments 16 |
| `IssueCard.vue` | warning do aging 14 |
| `CrudPage.vue` | New `plus` 16; editar `edit` 14; excluir `delete` 14; fechar modal `close` 20; Save `check` 16 |
| `AgentsPage.vue` | New `plus` 16; editar `edit` 14; excluir `delete` 14; fechar modal `close` 20; Save `check` 16 |
| `IssueTypesPage.vue` | New `plus` 16; editar `edit` 14; excluir `delete` 14; remove field `delete` 14; Add field `plus` 16; fechar modal `close` 20; Save `check` 16 |
| `BoardEditor.vue` | fechar modal `close` 20; remove column `delete` 14; Add column `plus` 16; Save `check` 16 |
| `EntityModal.vue` | fechar modal `close` 20; Save `check` 16 |
| `ProfilePage.vue` | Save `check` 16 (importar `AppIcon`) |

Ações de fechar sem `title` recebem `title="Close"` e `aria-label="Close"`.
Cancelar/voltar continuam apenas texto, sem ícone.

## 6. Acessibilidade

- Botão cujo único conteúdo é ícone recebe `aria-label` descritivo, em inglês,
  acompanhado de `title` para o tooltip. `title` sozinho não basta.
- Os ícones são decorativos: o wrapper aplica `aria-hidden="true"`; não usar
  `role="img"`, `alt` ou `title` no `AppIcon`.
- Não alterar `tabindex`, foco visível, atalhos ou ordem de tabulação.
- Exemplos: `aria-label="Edit board"`, `"Delete source"`, `"Close"`,
  `"Toggle sidebar"`, `"Light theme"`/`"Dark theme"`.

## 7. Contrato de testes

> **Refinamento absorvido (Mai, 19/10):** `frontend/tests/icon-standard.feature`
> e `frontend/cypress/e2e/icon-standard.cy.js` já existem na árvore principal
> (`/home/esdras/src/phrise/multi-agent`), em Red (2 verdes, 8 vermelhos). A
> KAN-105 valida/estende o `.feature` e cria `.test.mjs` + `.spec.js`; a KAN-106
> leva o `.cy.js` ao verde e faz a auditoria visual. Ninguém recria os arquivos:
> cada subtarefa copia o artefato da árvore principal para o próprio worktree e
> o commita.

Criar, em português do Brasil:

1. `frontend/tests/icon-standard.feature` — cenários BDD entregues pela Mai:
   editar sempre lápis; excluir sempre lixeira; adicionar/fechar/salvar; escala
   14/16/18/20; logo custom; botão só de ícone com nome acessível; ausência de
   SVG de UI fora do wrapper. A KAN-105 valida e estende se necessário.
2. `frontend/tests/icon-standard.test.mjs` (node:test) — guardas estáticas:
   - só `AppIcon.vue` contém `<svg>` em `frontend/src`, exceto a marca
     `smartboard`, que também vive nele;
   - todo `name="..."` estático existe no mapa; o ternário `sun`/`moon` do tema
     é afirmado explicitamente;
   - todo `size` estático pertence a 14/16/18/20; ausência de `size` só no
     topbar/grupo;
   - `stroke-width="2"` e nenhum path manual fora do logo;
   - somente `AppIcon.vue` importa `lucide-vue-next`;
   - versão exata `1.0.0` em `package.json` e no lockfile;
   - todo `<button>` cujo conteúdo é só `AppIcon` tem `aria-label`;
   - `title="Edit"` usa `name="edit"`; `title="Delete"`/`"Remove"` usa
     `name="delete"`;
   - botões Save/Create contêm `name="check"`.
3. `frontend/tests/icon-standard.spec.js` (vitest) — monta `AppIcon` para cada
   nome do mapa e afirma `svg`, tamanho, `stroke-width=2`, `aria-hidden`,
   `currentColor` e fallthrough de `class`; a marca `smartboard` não vira
   Lucide.
4. `frontend/cypress/e2e/icon-standard.cy.js` — navegador real: sidebar,
   topbar, CRUD, Agentes, Tipos de issue, board e painel; classes `lucide-*`
   esperadas; tamanhos e traço computados; `aria-label` dos botões só de ícone;
   editar/excluir com ícones distintos; zero erro de runtime/console. O arquivo
   já foi entregue pela Mai em Red; a KAN-106 o leva ao verde.
5. Atualizar `frontend/tests/copilot.test.mjs` (o contrato citado na RFC está
   na linha 67, não 105): trocar a asserção do path literal de `copilot` por
   `assert.match(iconsVue, /copilot:\s*PanelRight/)` ou equivalente que prove o
   mapeamento Lucide.

O E2E não precisa commitar screenshots; a auditoria visual de sidebar, topbar,
CRUD, board e painel deve ser registrada no comentário do item com o caminho do
artefato, se houver imagem.

## 8. CI e orçamento de bundle

- `.github/workflows/ci.yml` ganha o passo `Unit (vitest)` com
  `npm run test:unit` em `frontend/`, depois do `Install`. Manter
  `node --test frontend/tests/*.test.mjs`, `npm run build` e o E2E.
- Baseline do bundle: 55.397 B gzip. Teto: 66.000 B (≈ +10 KB). Medir após o
  build com `gzip -9 -c dist/assets/index-*.js | wc -c` (somar os chunks JS se
  houver code-splitting) e registrar antes/depois no PR. Acima do teto, o
  import deve ser revisto antes do merge.

## 9. Ondas e subtasks

| Onda | Item | Assignee | Depende de | Entrega |
|---|---|---|---|---|
| 1 | KAN-101 — fundação Lucide e wrapper | Coder | — | dependência, `AppIcon.vue`, `frontend/README.md` |
| 1 | KAN-102 — CI com `test:unit` | David | — | `.github/workflows/ci.yml` |
| 2 | KAN-103 — varredura do sidebar | Coder | KAN-101, KAN-83 | `AppSidebar.vue` |
| 2 | KAN-104 — varredura de páginas/modais/painel | Coder | KAN-101, KAN-18, KAN-87 | `App.vue` e views/componentes |
| 3 | KAN-105 — BDD + guardas + componente | Kaio | KAN-101, KAN-103, KAN-104 | valida/estende `.feature` (herdado da Mai) + `.test.mjs` + `.spec.js` |
| 3 | KAN-106 — E2E e auditoria visual | Mai | KAN-101, KAN-103, KAN-104 | `.cy.js` (herdado da Mai) verde + evidência visual |

KAN-101 e KAN-102 podem correr em paralelo. KAN-103 e KAN-104 tocam arquivos
disjuntos e podem correr em paralelo depois dos respectivos bloqueios. KAN-105
e KAN-106 só fecham verdes depois que as duas varreduras estiverem na árvore:
as guardas e o E2E medem o estado final, não a transição.

**Nota de quadro:** o guard do `kanban` só deixa o worker modificar o item.
Como as subtasks nascem com `worker` vazio e assignee diferente da autora, a
Framework Architect não consegue pré-marcar `blocked`. O assignee deve se
bloquear ao assumir, com o motivo da coluna "Depende de"; o KAN-88 registra a
ordem para o Tech Lead. Não contornar o guard.

**Nota de quadro (KAN-88):** ao sair de `Refined`, a KAN-88 vira subtarefa
`Coding` de integração. O Coder que a assumir não refaz as varreduras: confere
a ordem, acompanha KAN-101…KAN-106 e fecha a verificação final. Se assumir antes
das bases, bloqueia a KAN-88 com o motivo "aguardando KAN-101…KAN-106".

## 10. Regras de pouso e riscos

- Rebasear em `origin/main` antes de começar; KAN-18, KAN-83 e KAN-87 estão em
  voo nos mesmos arquivos. Não reordenar nem reescrever o que já estiver lá.
- A KAN-101 deve manter todos os nomes antigos funcionando durante a transição;
  as varreduras é que removem `profiles`/`close` dos usos errados.
- Não alterar CSS de layout para acomodar 18px; validar em 1280×800 e 1024px.
  Se algum ajuste for inevitável, justificar no PR e provar que não há regressão.
- Não tocar em logo, retratos, ilustrações, `seed.js`, stores, `api/` ou backend.
- Sem comentários no código; o porquê vive neste guia e no commit.
- Commits Conventional com `KAN-101`…`KAN-106`; branch `feat/kan-88-...`.
- A KAN-101 commita este guia em `docs/KAN-88-guide.md`; as demais PRs o
  referenciam e não precisam reeditá-lo.

## 11. Barra de qualidade

- `npm run test:unit`, `node --test frontend/tests/*.test.mjs`,
  `npm run build` e `npm run test:e2e` verdes.
- Nenhum erro de runtime/console; nenhuma regressão nas suítes de sidebar,
  copilot, perfil, drawer, console, rail e smoke.
- Auditoria visual de sidebar, topbar, CRUD, agentes, issue types, board e
  painel da issue sem regressão de densidade ou alinhamento.
- `rg` final: nenhum `<svg>` de UI fora de `AppIcon.vue`; nenhum tamanho fora
  de 14/16/18/20; nenhum `title="Edit"` com `profiles`; nenhum `title="Delete"`
  com `close`; todo botão só de ícone com `aria-label`.
- Bundle dentro do teto de 66.000 B gzip.
