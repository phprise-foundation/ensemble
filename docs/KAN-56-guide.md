# Guia de Implementação — KAN-56

Backfill das chaves novas do seed em `db.json` antigo (correção do `500` em
`GET/PATCH /api/user`).

Status: refinamento entregue pela Alexya (PASS). Escopo desta etapa: **correção
pontual** no backend fake. Autor da etapa: Framework Architect. Interface:
`frontend/` (SPA Vue 3 + Vite, backend fake). O backend PHP real **não** é
tocado.

Fonte única do aceite: `frontend/tests/seed-backfill.{feature,test.mjs}` +
`frontend/cypress/e2e/legacy-db-boot.cy.js` (já escritos pela QA no
Refinamento; untracked na árvore).

## 1. Causa e contrato (congelado)

`load()` só cria o seed quando `dataFile` **não existe**. Chaves novas do seed
(`user`, KAN-32) nunca são acrescentadas a um banco preexistente. O contrato:

- A leitura mescla **somente chaves ausentes** do seed — `db[key] ??= seed[key]`.
- Chave presente **nunca** é sobrescrita, inclusive arrays vazios e
  `user.displayName` já definido.
- O caminho "arquivo não existe" continua criando o seed completo.
- `GET /api/user` → 200 `{ login: "admin", displayName: "" }`; `PATCH` volta a
  persistir sem mexer no login.

## 2. Alteração de produção (única)

`frontend/plugin/fake-backend.js` — só o `load()`. Passa a aceitar
`filePath = dataFile` (para o teste usar um arquivo temporário) e a exportar:

```js
export function load(filePath = dataFile) {
  if (!existsSync(filePath)) {
    mkdirSync(dirname(filePath), { recursive: true })
    writeFileSync(filePath, JSON.stringify(createSeed(), null, 2))
  }
  const database = JSON.parse(readFileSync(filePath, 'utf-8'))
  const seed = createSeed()
  for (const key of Object.keys(seed)) {
    database[key] ??= seed[key]
  }
  return database
}
```

- `handle` já é exportado (KAN-11); não duplicar.
- `save()` e o handler `user` (KAN-32) **não** mudam.
- O `unhandledrejection` do `session.load()` é sintoma, não correção própria:
  some quando `/api/user` volta a responder 200.
- `dirname` já é importado no topo do arquivo.

## 3. Artefatos a commitar (já na árvore, untracked/modificados)

| arquivo | papel | estado |
|---|---|---|
| `frontend/tests/seed-backfill.feature` | 4 cenários BDD (pt-BR) | untracked |
| `frontend/tests/seed-backfill.test.mjs` | integração `node:test` (4 testes) | untracked |
| `frontend/cypress/e2e/legacy-db-boot.cy.js` | E2E de navegador (boot sobre db legado) | untracked |
| `frontend/run-e2e.sh` | harness sobe com db legado + backup/restore | modificado |
| `frontend/cypress.config.js` | `screenshotOnRunFailure: false` | modificado |

Um único PR com a correção + estes 5 artefatos + este guia. **Não** usar
`git add .` — a árvore tem untracked de outros itens (KAN-55/57, dark-theme,
source-directory, mobile-drawer, kan-29 etc.). Adicionar **apenas** os caminhos
da tabela.

## 4. Barra de qualidade

1. `node --test tests/seed-backfill.test.mjs` → **4/4 verde**.
2. `bash run-e2e.sh --spec cypress/e2e/legacy-db-boot.cy.js` → tabela
   `All specs passed`; **não** ler `EXIT=0` como verde (KAN-55).
3. `npm run test:e2e` → sem regressão de runtime/console no spec novo.
4. Atualizar `docs/STATE.md` no PR (convenção GITFLOW).

## 5. Arquivo quente: `run-e2e.sh` (KAN-55/KAN-57 em voo)

`run-e2e.sh` também é tocado pela KAN-57 (código de saída). As duas mudanças
não se sobrepõem (esta mexe no topo, a KAN-57 no fim do script), mas convivem
no mesmo arquivo. Ao commitar, incluir **apenas os hunks do backfill/seeding**,
não os da KAN-57; rebasar sobre `origin/main` na execução. Se a KAN-57 mesclar
primeiro, o rebase resolve sozinho (hunks distintos).
