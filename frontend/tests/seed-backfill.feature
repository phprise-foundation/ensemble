# language: pt-BR
Funcionalidade: Backfill das chaves novas do seed em um db.json antigo
  Origem: KAN-56 (achado da QA no Testing da KAN-37, `main` @ `bbf4c1f`).
  O seed do fake backend só era aplicado quando `.data/db.json` não existia.
  Num banco criado antes da KAN-32 (sem a chave `user`), `GET` e `PATCH`
  `/api/user` estouravam em 500 e o menu de perfil nascia sem login nem nome.

  Contrato congelado:
  - A leitura do banco mescla SOMENTE as chaves ausentes do seed. Toda chave
    presente no arquivo é preservada como está — inclusive arrays vazios.
  - A chave `user` já existente nunca é substituída pelo valor do seed.
  - `GET /api/user` responde 200 com login `admin` e displayName vazio depois
    do backfill; `PATCH /api/user` volta a persistir o nome.
  - O boot do app sobre um db.json antigo não emite erro de runtime nem
    `unhandledrejection`, e o menu de perfil mostra o login.

  Contexto:
    Dado um `.data/db.json` criado antes da KAN-32, sem a chave `user`
    E com coleções existentes (por exemplo, um workspace `legacy`)

  Cenário: chave nova do seed é acrescentada ao banco antigo
    Quando o fake backend lê o arquivo
    Então a chave `user` passa a existir com `{ login: "admin", displayName: "" }`
    E `GET /api/user` responde 200 com esse login
    E `PATCH /api/user` grava o displayName e mantém o login

  Cenário: coleções existentes não são sobrescritas
    Quando o fake backend lê um banco com um workspace `legacy`
    Então o workspace `legacy` permanece, sem o seed de `default` o substituir
    E um array vazio existente continua vazio
    E nenhuma issue existente é duplicada ou reordenada

  Cenário: displayName já definido sobrevive ao backfill
    Dado um banco que já tem `user` com displayName "Ana"
    Quando o fake backend lê o arquivo
    Então o displayName continua "Ana"
    E o login continua `admin`

  Cenário: boot com db.json antigo sobe sem erro e mostra o login
    Dado o dev server iniciado sobre um `.data/db.json` sem `user`
    Quando o navegador abre a aplicação
    Então nenhum `unhandledrejection` ou `console.error` é observado
    E o menu de perfil exibe o login `admin`
    E `GET /api/user` responde 200 pela interface em execução
    E `PATCH /api/user` persiste o nome e mantém o login
