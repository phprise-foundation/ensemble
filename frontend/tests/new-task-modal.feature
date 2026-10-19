#language: pt

Funcionalidade: Modal de nova task com status inicial validado
  Para manter a integridade do board
  Como pessoa criando uma task a partir do board
  Eu quero que a nova task nasça apenas em um status inicial do workflow do board

  Contexto:
    Dado um board "KANB" com o workflow "standard"
    E os statuses "to-develop" (initial), "in-development" (transitional) e "done" (final)

  Cenário: Modal abre com foco no summary e apenas statuses iniciais
    Dado que estou no board
    Quando eu abro o modal de nova task
    Então o campo "create-summary" recebe o foco
    E o select de status lista apenas os statuses de tipo "initial"
    E nenhum status "transitional" ou "final" aparece como opção

  Cenário: Default é o primeiro status inicial da ordem do workflow
    Dado que o workflow tem pelo menos um status "initial"
    Quando eu abro o modal de nova task
    Então o select de status vem selecionado com o primeiro "initial"

  Cenário: Summary e description ocupam a largura útil sem overflow
    Dado viewport de largura 375, 768 ou 1280
    Quando eu abro o modal de nova task
    Então "create-summary" e "create-description" ocupam a largura útil do corpo do modal
    E a página não tem rolagem horizontal
    E o cartão do modal permanece dentro da viewport

  Cenário: Criação com status inicial grava a issue e preserva type e assignee
    Dado que o modal está aberto
    Quando eu preencho summary e description
    E escolho type "task" e assignee "mai"
    E o status permanece o "initial" padrão
    E eu confirmo a criação
    Então a issue é gravada com o status inicial escolhido
    E o payload preserva o type e o assignee selecionados
    E o corpo da requisição não contém "columnId"

  Cenário: Status final, transitional, inexistente ou de outro workflow é rejeitado com 422
    Dado que o modal está aberto
    Quando o servidor responde 422 com "field=statusId" para o status informado
    Então nenhuma issue é gravada
    E o id que a criação rejeitada usaria não é consumido
    E o erro do servidor aparece associado ao campo de status
    E o modal permanece aberto

  Cenário: Status omitido usa o primeiro status inicial
    Dado que o modal está aberto
    Quando eu envio a criação sem "statusId"
    Então a issue nasce com o primeiro status "initial" da ordem do workflow

  Cenário: Workflow sem status inicial bloqueia a criação
    Dado que o único status "initial" se tornou "transitional"
    Quando eu abro o modal de nova task
    Então o aviso de ausência de status inicial é exibido
    E o select de status e o botão de criação ficam desabilitados
    E a criação direta no backend responde 422 com "field=statusId"

  Cenário: columnId forjado é ignorado
    Dado que o modal está aberto
    Quando eu envio a criação com "columnId" apontando para outra coluna
    Então a issue nasce no status inicial escolhido
    E a coluna é derivada do status inicial
    E a ordem da coluna forjada não muda

  Cenário: boardId inválido responde 422 sem fallback
    Dado uma criação com "boardId" inexistente ou ausente
    Quando eu envio a criação
    Então o backend responde 422 com "field=boardId"
    E nenhuma issue é gravada
    E não há fallback para o primeiro board do banco

  Cenário: Erro 422 aparece em região acessível sem fechar o modal
    Dado que o modal está aberto
    Quando o servidor responde 422 com "field=statusId"
    Então o erro aparece em uma região "role=alert" associada ao campo
    E o campo recebe "aria-invalid" e "aria-describedby"
    E o modal permanece aberto

  Cenário: Edição existente continua funcionando
    Dado uma issue existente no board
    Quando eu altero o summary e o assignee via "PATCH"
    Então a edição responde com sucesso
    E os campos alterados são preservados
