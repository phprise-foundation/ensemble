# language: pt-BR
Funcionalidade: Limites de descrição e comentário no protótipo
  Origem: KAN-93 (RFC aprovada; guia `docs/KAN-93-guide.md` §8.1).

  O canal dos agentes entrega no máximo 4095 code points de descrição e 1023 de
  comentário. O excedente é truncado sem aviso a quem escreveu, o que é perda
  silenciosa de contexto. O protótipo passa a recusar o excesso, avisar antes do
  limite e nunca cortar texto em silêncio.

  Contrato congelado:
  - limites: descrição 4095, comentário 1023, contados em code points
    (`Array.from(texto).length`, equivalente a `mb_strlen` no PHP);
  - aviso visual a partir de 3686 e 921 (`Math.ceil(limite * 0.9)`);
  - acima do limite: mensagem em pt-BR e botão de salvar/enviar desabilitado;
  - fake backend responde `422` com `{ error, field }`, onde `field` é
    `description` ou `comment`, e valida antes de qualquer mutação;
  - conteúdo legado é exibido integralmente, sem truncar;
  - os três campos ligam contador e erro por `aria-describedby` e expõem
    `aria-invalid` quando inválidos.

  Contexto:
    Dado que o fake backend nasce de `createSeed()` e a fixture `KANB-7` é legada
    E o limite da descrição é 4095 code points e o do comentário é 1023

  @backend
  Cenário: descrição com exatamente 4095 code points é aceita e gravada
    Quando envio `POST /api/issues` com descrição de 4095 code points
    Então a resposta é 201
    E a issue criada guarda os 4095 code points sem alteração

  @backend
  Cenário: descrição com 4096 code points é rejeitada sem gravar nem consumir id
    Dado que existem N issues no banco
    Quando envio `POST /api/issues` com descrição de 4096 code points
    Então a resposta é 422 com `field` igual a `description`
    E a mensagem informa o limite 4095 e a contagem 4096
    E o banco continua com N issues
    E a próxima issue criada recebe o id que a requisição rejeitada usaria

  @backend
  Cenário: patch acima do limite não altera nenhum campo do registro
    Dado que a issue `KANB-1` tem descrição, assignee e `updatedAt` conhecidos
    Quando envio `PATCH /api/issues/KANB-1` com descrição de 4096 e outro assignee
    Então a resposta é 422 com `field` igual a `description`
    E a descrição, o assignee, o id e o `updatedAt` permanecem iguais aos anteriores

  @backend
  Cenário: comentário com 1023 code points é aceito e com 1024 é rejeitado
    Dado que a issue `KANB-1` tem C comentários e um `updatedAt` conhecido
    Quando envio `POST /api/issues/KANB-1/comments` com 1024 code points
    Então a resposta é 422 com `field` igual a `comment`
    E o comentário não é gravado e o `updatedAt` não muda
    Quando envio o mesmo endpoint com 1023 code points
    Então o comentário é gravado com o texto integral

  @backend
  Cenário: a contagem é em code points, não em unidades UTF-16
    Quando envio uma descrição com 4095 emojis
    Então a issue é aceita, embora o texto tenha 8190 unidades UTF-16
    Quando envio uma descrição com 4096 emojis
    Então a resposta é 422 com `field` igual a `description`

  @backend
  Cenário: limite só barra descrição e comentário; o resto do item continua normal
    Dado o item legado `KANB-7`, acima dos dois limites
    Quando troco o assignee por `PATCH /api/issues/KANB-7`
    Então a alteração é aceita
    E mover o item para outra coluna continua permitido

  @backend
  Cenário: issue inexistente responde 404 antes de validar o limite
    Quando envio `PATCH /api/issues/KANB-404` com descrição acima do limite
    Então a resposta é 404, sem `field`, e nada é criado

  @backend
  Cenário: o conteúdo legado é servido inteiro
    Quando leio `GET /api/issues/KANB-7`
    Então a descrição tem mais de 4095 code points e termina em `LEGACY-DESCRIPTION-END`
    E o último comentário tem mais de 1023 code points e termina em `LEGACY-COMMENT-END`

  @cliente
  Cenário: o erro do backend chega ao chamador com status e campo
    Dado que o `fetch` devolve 422 com `{ error, field: "description" }`
    Quando o cliente chama `PATCH /api/issues/KANB-1`
    Então o `Error` lançado tem `status` 422 e `field` igual a `description`
    E um erro sem `field` no corpo resulta em `field` nulo, sem parsing de mensagem

  @ui
  Cenário: contador e aviso na criação seguem os limiares
    Dado que abro o modal de criação de issue
    Quando a descrição tem 3685 code points
    Então o contador mostra `3685/4095` sem aviso de proximidade
    Quando a descrição tem 3686 code points
    Então o aviso de proximidade aparece
    Quando a descrição tem 4095 code points
    Então nenhum erro aparece e `Create` está habilitado
    Quando a descrição tem 4096 code points
    Então a mensagem de limite aparece e `Create` fica desabilitado

  @ui
  Cenário: salvar a descrição no painel grava e cancelar descarta
    Dado que abro o painel de uma issue e clico em `Edit`
    Então o campo abre com a descrição atual
    Quando altero o texto e clico em `Save`
    Então a descrição é gravada e o painel volta ao modo de leitura
    Quando clico em `Edit` e depois em `Cancel`
    Então a descrição original permanece intacta

  @ui
  Cenário: comentário acima do limite não é enviado
    Dado que abro o painel de uma issue
    Quando digito 1024 code points no campo de comentário
    Então a mensagem de limite aparece e `Comment` fica desabilitado
    Quando reduzo para 1023 code points e envio
    Então o comentário é gravado com `trim()` aplicado

  @ui
  Cenário: item legado aparece inteiro e exige redução para salvar
    Dado o item `KANB-7`, cuja descrição e comentário excedem os limites
    Quando abro o painel
    Então o contador mostra mais de `4095/4095` e a descrição termina em `LEGACY-DESCRIPTION-END`
    E o comentário termina em `LEGACY-COMMENT-END`, sem truncamento
    E `Save` e `Comment` estão desabilitados
    Quando reduzo a descrição para 4095 code points
    Então `Save` fica habilitado

  @ui
  Cenário: os três campos são descritos para tecnologia assistiva
    Então o campo de criação aponta para `create-description-limit`
    E o campo de edição aponta para `issue-description-limit`
    E o campo de comentário aponta para `issue-comment-limit`
    E cada campo inválido expõe `aria-invalid="true"`
    E a mensagem de erro é anunciada em região viva

  @e2e
  Cenário: o contrato HTTP 422 é observável pela interface em execução
    Quando crio uma issue de 4095 code points pela interface
    Então ela aparece no painel com o texto integral
    Quando envio direto 4096 e depois leio a issue por `GET`
    Então a issue anterior continua intacta e nenhum erro de runtime é emitido
