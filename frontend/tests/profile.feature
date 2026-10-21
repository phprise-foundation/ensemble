# language: pt-BR
Funcionalidade: Perfil do usuário — PATCH parcial, rascunho de foto e estados do Save
  Origem: KAN-132, a partir da RFC KAN-126 e do guia docs/KAN-126-guide.md (§3, §5 e §8).
  A foto e o nome vivem no fake backend (`PATCH /api/user` parcial e idempotente).
  Na página, a foto é um rascunho local: nada grava fora do Save — um único
  PATCH carrega nome e foto juntos. O login é somente leitura.

  Contrato congelado do PATCH /api/user:
  - Corpo parcial `{ displayName?, avatar?, avatarColor? }`, merge por campo:
    campo ausente preserva o valor atual; `avatar: null` remove a foto.
  - `avatar` aceita `null` ou data URL `data:image/(png|jpeg|webp);base64,`
    com base64 de até 512 KiB; qualquer outra coisa é 422.
  - `avatarColor` aceita hex `#[0-9a-fA-F]{6}`; caso contrário, 422.
  - `displayName` mantém trim e teto de 40 caracteres; acima disso, 422.
  - Tudo é validado antes de qualquer mutação: um PATCH inválido não altera
    nem os campos válidos do corpo.
  - Repetir o mesmo PATCH produz o mesmo estado (idempotência).
  - GET e PATCH respondem `{ login, displayName, avatar, avatarColor }`.
  - No boot, o backfill completa `avatar` e `avatarColor` ausentes no
    db.json e nunca sobrescreve a foto salva.

  Contexto:
    Dado que o usuário `admin` está autenticado no protótipo
    E o fake backend tem o usuário com nome "Ana", sem foto e com a cor determinística do login

  Cenário: PATCH parcial de apenas um campo preserva os demais
    Quando o cliente envia `PATCH /api/user` com `{ "displayName": "Bruna" }`
    Então a resposta traz `displayName` "Bruna"
    E a resposta preserva `avatar` nulo e a `avatarColor` anterior
    E uma leitura `GET /api/user` confirma o mesmo estado

  Cenário: PATCH com avatar define a foto sem tocar no nome
    Quando o cliente envia `PATCH /api/user` com `{ "avatar": "data:image/webp;base64,AAAA" }`
    Então a resposta traz o data URL em `avatar`
    E a resposta preserva `displayName` "Ana" e a `avatarColor` anterior

  Cenário: PATCH com avatar null remove a foto
    Dado que o usuário já tem uma foto definida
    Quando o cliente envia `PATCH /api/user` com `{ "avatar": null }`
    Então a resposta traz `avatar` nulo
    E a `avatarColor` e o `displayName` permanecem os mesmos

  Cenário: repetir o mesmo PATCH é idempotente
    Quando o cliente envia o mesmo PATCH duas vezes seguidas
    Então as duas respostas são iguais
    E uma leitura `GET /api/user` confirma um único estado final

  Cenário: PATCH inválido é recusado com 422 sem mutação parcial
    Quando o cliente envia `PATCH /api/user` com `{ "displayName": "Bruna", "avatar": "http://exemplo/foto.png" }`
    Então a resposta é 422 com erro em `avatar`
    E `displayName` permanece "Ana"
    E `avatar` e `avatarColor` permanecem como estavam

  Cenário: avatar fora do contrato é recusado com 422
    Quando o cliente envia `PATCH /api/user` com um `avatar` que não é data URL de png, jpeg ou webp em base64
    Então a resposta é 422 com erro em `avatar`
    E o estado do usuário não muda

  Cenário: avatar acima de 512 KiB é recusado com 422
    Quando o cliente envia `PATCH /api/user` com um data URL cujo base64 excede 512 KiB
    Então a resposta é 422 com erro em `avatar`
    E o estado do usuário não muda

  Cenário: avatarColor fora do hex é recusado com 422
    Quando o cliente envia `PATCH /api/user` com `avatarColor` "azul", "#00b8d" ou `42`
    Então cada resposta é 422 com erro em `avatarColor`
    E o estado do usuário não muda

  Cenário: displayName acima de 40 caracteres é recusado com 422
    Quando o cliente envia `PATCH /api/user` com um nome de 41 caracteres
    Então a resposta é 422 com erro em `displayName`
    E o nome anterior permanece

  Cenário: GET responde os quatro campos do usuário
    Quando o cliente lê `GET /api/user`
    Então a resposta tem exatamente `login`, `displayName`, `avatar` e `avatarColor`
    E o `login` é `admin` e nunca é alterável por PATCH

  Cenário: boot com db.json antigo completa avatar e cor sem sobrescrever a foto salva
    Dado um `.data/db.json` com `user` já tendo foto e cor definidas
    Quando o fake backend lê o arquivo no boot
    Então a foto e a cor salvas permanecem exatamente como estavam

  Cenário: rascunho de foto não grava fora do Save
    Dado que a página de perfil está aberta com o usuário hidratado
    Quando a pessoa seleciona um arquivo de imagem válido
    Então o preview circular mostra imediatamente a imagem escolhida
    E nenhuma requisição PATCH é enviada

  Cenário: remover a foto é decisão de rascunho até o Save
    Dado que a pessoa selecionou uma foto no rascunho
    Quando ela aciona "Remove photo"
    Então o preview volta ao fallback de inicial sobre a cor determinística
    E nenhuma requisição PATCH é enviada

  Cenário: arquivo inválido vira mensagem inline e é descartado
    Quando a pessoa seleciona um arquivo de tipo não suportado ou acima de 2 MiB
    Então uma mensagem inline custom aparece junto ao campo de arquivo
    E o rascunho mantém a foto anterior
    E nenhum diálogo nativo `window.alert` é usado

  Cenário: Save desabilitado sem alterações
    Dado que a página de perfil está aberta sem alterações no rascunho
    Então o botão de Save está desabilitado

  Cenário: Save envia um único PATCH com nome e foto juntos
    Dado que a pessoa alterou o nome e selecionou uma foto
    Quando ela aciona o Save
    Então exatamente um `PATCH /api/user` é enviado com `displayName` e `avatar`
    E o botão fica em estado de carregamento durante a requisição
    E o sucesso é anunciado por um elemento com `role="status"`

  Cenário: falha do Save vira erro inline custom
    Dado que o `PATCH /api/user` responde 422
    Quando o Save termina
    Então uma mensagem de erro inline custom aparece na página
    E o rascunho local é preservado para nova tentativa

  Cenário: login é somente leitura na página de perfil
    Dado que a página de perfil está aberta
    Então o campo de login é somente leitura
    E nenhum PATCH altera o `login`

  Cenário: voltar da página permanece na Fase 1
    Dado que a página de perfil está aberta com rascunho alterado
    Então o botão voltar ainda existe nesta fase
    E a guarda de descarte é entregue na Fase 2 (KAN-134), fora deste escopo
