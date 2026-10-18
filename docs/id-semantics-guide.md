# Norma — o verbo de persistência nasce do fato (`__existing`), não do campo `id`

Origem: follow-up do KAN-29 (review de Alan) e do padrão do KAN-20. Autor da
etapa: Framework Architect (Lucy).

## Contexto

`boardStore.saveEntity()` (`frontend/src/store/board.js`) decide o verbo pelo
**campo**: `data.id ? updateEntity (PATCH) : createEntity (POST)`. Esse sinal é
correto apenas quando o id é derivado (`source`, KAN-20) ou nunca digitado na
criação (páginas CRUD). Ele falha nas superfícies em que o id nasce preenchido
na criação:

1. `board` — id obrigatório digitado na criação → PATCH → 404.
2. `workflow` — campo "ID" em `addWorkflow`; id digitado → PATCH → 404.
3. `team` — campo "ID (optional)" em `addTeam`; id digitado → PATCH → 404.
4. `copyWorkflow` (cópia de template) — ids pré-gerados em entidades novas →
   PATCH → 404.

Há ainda uma quinta boca, de outra natureza: `editWorkflow` expõe "ID"
**editável** — o id é a chave da coleção; renomeá-lo via PATCH cai em 404 ou
sobrescreve outra entidade (inconsistente com `board`, que trava o id em
`BoardEditor`, e com `team`, que nunca o expõe na edição).

## Norma (congelada)

- O verbo (POST vs PATCH) deriva **do fato de existência** — o flag
  `__existing` — nunca do campo `data.id`.
- O id é a **chave imutável** da entidade: aceito na criação (board exige;
  workflow/team aceitam), nunca editável na edição.
- `__existing` é o sinal único de existência em todos os editores.

## Mapa de alterações

```
frontend/src/store/board.js             → saveEntity lê data.__existing para o verbo e remove o flag antes do request
frontend/src/components/AppSidebar.vue  → saveEditor para de apagar __existing; editWorkflow deixa de expor id
frontend/src/components/CrudPage.vue    → openEdit marca __existing
frontend/src/views/AgentsPage.vue       → openEdit marca __existing
frontend/src/views/IssueTypesPage.vue   → openEdit marca __existing
frontend/tests/entity-verb.feature      → BDD (pt-BR): criação→POST, edição→PATCH, id digitado/pré-gerado
frontend/tests/entity-verb.spec.js      → integração (vitest) exercitando o fluxo como o cliente
```

## Contrato de verbo (congelado)

| Situação | Sinal | Verbo |
|---|---|---|
| criar board/workflow/team (id vazio ou digitado) | sem `__existing` | POST (id respeitado; backend gera se vazio) |
| editar board/workflow/team/source | `__existing` true | PATCH |
| copiar template (`copyWorkflow`) | sem `__existing` | POST (ids pré-gerados) |
| editar via CRUD (statuses/transitions/roles/profiles/agents/issueTypes) | `__existing` true (marcado no `openEdit`) | PATCH |

## Barra de qualidade

- Teste que exercita o fluxo **como o cliente** (vitest: monta o editor, clica,
  salva e observa o verbo no limite `store → api`), não "não lançou exceção".
- `npx vitest run` e `node --test frontend/tests/*.test.mjs` verdes;
  `vendor/bin/phpunit` e `vendor/bin/psalm` sem regressão.
- Sem comentários no código; retornos cedo (sem `else`/`switch`); diff mínimo.
- Este follow-up generaliza o padrão do `source` já registrado em
  `docs/KAN-11-guide.md` ("o verbo nasce do fato").

## Norma — lado servidor (fake backend, depois a API PHP)

O cliente decide o verbo pelo fato. O servidor precisa fechar a outra ponta do
mesmo contrato — hoje os handlers divergem:

- `issues` PATCH (`frontend/plugin/fake-backend.js:146`) faz
  `Object.assign(issue, body, { updatedAt })` **sem fixar a chave**: um id no
  corpo alteraria a chave de verdade, corrompendo referências externas.
- `sources` PATCH (`:181`) e coleções PATCH (`:205`) fixam `{ id }` — imutabilidade
  aplicada, mas **em silêncio**: a UI exibe uma chave que não foi gravada.
- coleções POST (`:194`) faz `{ ...body }` sem remover `__existing`: qualquer
  caminho que esqueça o `delete` persistiria o flag e o vazaria aos agentes via API.

Regra (congelada):

1. **id divergente da URL nunca é aplicado** — o PATCH fixa `{ id }` (chave
   imutável). Divergência no corpo responde explícito **409** com a causa
   verdadeira, nunca descarta em silêncio. Texto congelado (asserção exata):
   `409 "<coleção> id divergente da URL: o id é imutável"`.
2. **Higiene do flag** — POST e PATCH nunca persistem `__existing` no registro;
   o flag é sinal de transporte, não dado. Teste assegura payload limpo.
3. **Padronizar `issues`** com a fixação dos demais handlers.

A migração para a API PHP herda este parágrafo por exigência do guia.

## Invariantes (congeladas)

1. `__existing` é carimbado **exclusivamente na abertura do editor**, nunca no
   submit. Os editores de entidade existente já fazem isso (`editBoard`,
   `editWorkflow`, `editTeam`, `editSource`).
2. `saveEntity` é o **único ponto que lê e remove** o flag; nenhum editor apaga
   `__existing` antes de chamar `saveEntity`.
3. No bloco `workflow` do `saveEditor`, `isNew = !__existing` é calculado
   **antes** do `delete`; após o `delete`, nada pode mais ler o flag.

## Contraexemplos (Red)

O contrato de verbo deve falhar nominalmente nos contraexemplos, não apenas nos
caminhos felizes:

- **entidade existente sem flag** → deve nascer nova (POST) ou ser sinalizada;
  nunca PATCH silencioso contra chave existente.
- **entidade nova com flag** → nunca PATCH contra chave inexistente (404).

## Sub-tasks

1. **Testes (Red)** — BDD + integração do contrato de verbo, incluindo os
   contraexemplos acima.
2. **Implementação (Green)** — `saveEntity` + editores + páginas CRUD.
3. **Lado servidor (Red)** — teste de higiene do flag + id divergente + handler
   `issues` padronizado.
4. **Lado servidor (Green)** — handlers do fake backend conforme a norma.

Arquivos distintos → paralelizáveis contra o contrato congelado acima. O teste
nasce vermelho contra o código atual (prova o defeito) e fica verde quando a
implementação pousa.
