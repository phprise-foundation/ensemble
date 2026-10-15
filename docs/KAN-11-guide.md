# Guia de Implementação — KAN-11

Registro de source com diretório válido (correção do erro "sources not found").

Status: RFC aprovada (design aprovado por David; leitura do Product Owner por
Vivy). Escopo desta etapa: **Fase 1 — MVE** (correção do registro + validação
de diretório). Autor da etapa: Framework Architect.

## 1. O que já foi entregue

- **KAN-20 (Done, PR #10):** a causa raiz foi corrigida — o verbo nasce do
  fato, nunca de um valor derivado.
  - `addSource()` abre o rascunho com `id: ''` (`frontend/src/components/AppSidebar.vue`);
  - `saveEditor()` só deriva `id` quando `current.draft.__existing` (edição);
  - `boardStore.saveEntity()` escolhe `POST` quando `id` é vazio e `PATCH`
    quando há `id` (`frontend/src/store/board.js`).
  - Efeito: source nova sai como `POST /api/sources`; o `PATCH` em item
    inexistente — que produzia "sources not found" — deixa de ocorrer na
    criação.
- Cobertura já existente: `frontend/tests/source-creation.feature` e
  `frontend/tests/source-creation.test.mjs`.
- A derivação do **nome** a partir do caminho quando o campo vem vazio já
  existe em `saveEditor()` (`path.split('/').filter(Boolean).pop()`).

## 2. O que falta para fechar o MVE

Só resta o **critério 3** da RFC: caminho inválido precisa de mensagem com
causa verdadeira, e a validação precisa morar no processo do anfitrião.
Os critérios 1, 2 e 5 já estão cobertos pelo KAN-20.

## 3. Decisões que governam a implementação (herdadas do design aprovado)

- **Validade do diretório** é conferida pelo processo onde o workdir existe —
  hoje o middleware simulado (`frontend/plugin/fake-backend.js`), amanhã o
  backend PHP (fora do escopo). O navegador apenas reflete a resposta
  (`frontend/src/api/client.js` transforma `{ error }` em `Error.message`; o
  editor exibe em `current.error`).
- **Contrato de erros (congelado):**

| Caso | Rota | HTTP | corpo `{ error }` |
|---|---|---|---|
| caminho vazio/ausente | POST/PATCH `/api/sources` | 422 | `informe o caminho do diretório` |
| caminho não é diretório existente | POST/PATCH `/api/sources` | 422 | `diretório não encontrado` |
| editar source inexistente | PATCH `/api/sources/:id` | 404 | `source não encontrada` |
| recriar source com id existente | POST `/api/sources` | 409 | `source id já existe` |
| criação válida | POST `/api/sources` | 201 | a source |
| edição válida | PATCH `/api/sources/:id` | 200 | a source |

- **Nome:** ausente → último segmento do caminho; `id = slugify(name)`. O
  fallback genérico atual (`item-<timestamp>`) deixa de valer para sources.
- **id estável:** `PATCH` nunca recomputa o `id`, mesmo que o nome mude.
- **Duplicata:** a mesma pasta (mesmo `id`) registrada de novo → `409`. Isso é
  comportamento desejado, não falha do fluxo (leitura oficial do critério 4:
  usos repetidos com diretórios distintos fluem sem atrito; a duplicata
  deliberada recebe recusa clara).
- **Windows:** separador `/` apenas; limitação conhecida do protótipo, fora do
  escopo.

## 4. Ponto de atenção (testabilidade sem engano)

Rejeitar de propósito o atalho de "pular a validação quando `VITE_API_URL` não
está definida". Regra condicionada a variável de ambiente é regra sem dono e
esconde o defeito. O teste exercita o **sistema de arquivos real**: cria um
diretório temporário (`mkdtempSync`) para o caso válido e usa um caminho
garantidamente inexistente para o 422.

## 5. Costura de teste (seam) — congelada para permitir paralelismo

`frontend/plugin/fake-backend.js` passa a **exportar** a função de roteamento
existente:

```js
export function handle(db, method, segments, body)
```

- Sem mudar a assinatura nem o formato de retorno (`{ data, status }` ou
  `{ error, status }`).
- O middleware continua chamando `handle(...)`; o teste importa `handle` e
  `createSeed` (`frontend/src/data/seed.js`) e roda sobre uma **db em memória**
  — nunca grava em `.data/db.json` (o `save()` fica no middleware, não no
  `handle`).
- `segments` de uma rota:
  `url.replace(/^\/api\/?/, '').split('/').filter(Boolean)` (mesma linha do
  middleware).

Assim os dois arquivos são independentes: o teste pode ser escrito em Red
antes da implementação e ficar verde depois.

## 6. Mapa de alterações

```
frontend/plugin/fake-backend.js            → validação + nome derivado + erros em PT + export handle   (KAN-11/1)
frontend/tests/source-directory.feature    → cenários BDD (pt-BR)                                       (KAN-11/2)
frontend/tests/source-directory.test.mjs   → integração via handle()                                    (KAN-11/2)
```

Nada mais muda. `AppSidebar.vue` e `board.js` permanecem (o verbo já nasce do
fato — KAN-20).

## 7. Sub-tasks

1. **KAN-11/1 — validação no fake backend** (Coder): `frontend/plugin/fake-backend.js`.
2. **KAN-11/2 — BDD + teste de integração** (Coder): `frontend/tests/`.

Arquivos distintos → podem correr em paralelo. O padrão `node:test` do projeto
está em `frontend/tests/source-creation.test.mjs`.

## 8. Barra de qualidade e aceite

- `node --test frontend/tests/*.test.mjs` verde.
- `vendor/bin/phpunit` e `vendor/bin/psalm` sem regressão (a mudança é só no JS
  de protótipo).
- Sem comentários no código; sem imports/variáveis mortos; retornos cedo (sem
  `else`/`switch`).
- Critérios 1–5 da RFC cobertos pelos cenários do KAN-11/2. O critério 6
  (conferência visual do cliente) é salvaguarda barata: a evidência HTTP
  registrada no item basta para elegibilidade.
- `docs/GUIDE.md` existe (estilo e regras do OTAKU Manifesto); ele **não** tem
  exemplo de teste da persistência simulada — este guia o estabelece (seções 5
  e 7) para reuso futuro.
