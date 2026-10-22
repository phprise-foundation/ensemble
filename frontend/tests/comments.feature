# language: pt-BR
Funcionalidade: Avatares circulares e comentários com autor
  Origem: KAN-128 (RFC aprovada) e guia docs/KAN-128-guide.md. Decisões fechadas
  com o cliente no KAN-79.

  Contratos derivados do item e dos comentários:
  - Assignee do card: avatar circular via componente compartilhado (KAN-108),
    fallback inicial/cor, tooltip/aria-label com o nome; sem assignee, "?" numa
    cor neutra ("Unassigned" é o termo do painel).
  - Mapeamento do autor do comentário, nesta ordem: agente por id, agente por
    nome, user por login, user por displayName; desconhecido recebe a inicial da
    string original com cor determinística. Em colisão, o id do agente vence.
  - Comentários antigos com author "user"/"admin": "admin" casa com o login;
    "user" cai no fallback (não é id nem nome de agente e não é o login).
  - Timestamp absoluto dd/MM/yyyy HH:mm (pad 2, fuso local do instante já
    carregado), sem formato relativo e sem tradução nesta rodada.
  - O POST /api/issues/{id}/comments continua recebendo uma string em author;
    o store passa a enviar o login da sessão. API intacta (ADR do David).
  - Seed cobre as ramificações: "admin" (user por login), "vivy" (agente por
    id), "Kaio" (agente por nome) e o legado "user" (fallback), com um agente
    portando imagem em data URL de até 10 KB.
  - Nunca há imagem quebrada: toda miniatura resolve em imagem existente ou
    fallback inicial/cor.
  - Ordem cronológica, layout e responsividade preservados; nomes longos não
    quebram a linha; abas fora do escopo (KAN-80/KAN-90).

  Cenário: Comentário de agente por id mostra a identidade do agente
    Dado que o board autenticado está carregado
    E o painel do card exibe comentários com author "vivy" (id do agente Vivy)
    Quando o usuário abre o painel do card
    Então a linha do comentário mostra o nome "Vivy" na legenda do autor
    E a linha mostra o retrato do agente quando ele tiver portrait
    E a linha mostra a cor do avatar do agente no fallback sem retrato
    E o avatar é circular com a inicial como conteúdo quando não há imagem

  Cenário: Comentário de agente por nome e comentário de user por login
    Dado que o board autenticado está carregado
    Quando o usuário abre o painel do card
    Então o comentário com author "Kaio" resolve para o agente de nome "Kaio"
    E o comentário com author "admin" resolve para o user de login "admin"
    E o nome visível do user é o displayName quando existir, senão o login

  Cenário: Comentário antigo de autor desconhecido cai no fallback
    Dado que o board autenticado está carregado
    Quando o usuário abre o painel do card
    Então o comentário legado com author "user" mostra "user" como nome do autor
    E a miniatura mostra a inicial da string original sobre uma cor
    determinística
    E nenhuma imagem quebrada aparece na linha do comentário

  Cenário: Em colisão o id do agente vence o login do user
    Dado que um agente tem id igual ao login do usuário
    Quando um comentário tem como author essa string
    Então o autor resolve como agente (retrato, cor e nome do agente)
    E o login do user perde a colisão

  Cenário: Timestamp absoluto dos comentários
    Dado que o painel do card exibe comentários
    Então cada comentário mostra o instante no formato dd/MM/yyyy HH:mm
    E o formato é o mesmo em rodadas distintas, sem diferença de fuso na
    escrita do texto
    E nenhum timestamp usa termos relativos como "há" ou "ago"

  Cenário: Assignee do card com avatar circular
    Dado que o board autenticado está carregado com cards de agente atribuído
    E há card sem assignee
    Então todo assignee atribuído aparece como avatar circular do componente
    compartilhado, com foto quando existir e fallback inicial/cor quando não
    E cada assignee tem tooltip e aria-label com o nome do agente
    E o card sem assignee mostra o fallback de cor neutra com "?"

  Cenário: Nomes longos não quebram o layout dos comentários
    Dado que um comentário tem um nome de autor muito longo
    Quando o usuário abre o painel do card
    Então a linha do comentário mantém uma coluna de legenda sem estourar a
    largura do painel
    E o nome longo é truncado com reticências sem empurrar o corpo do texto
    E o corpo e a ordem cronológica dos comentários permanecem intactos

  Cenário: Novo comentário postado pela interface assume o login da sessão
    Dado que o usuário logado está com o painel do card aberto
    Quando digita um texto e envia pelo botão "Comment"
    Então o POST sai com a string do login da sessão no campo author
    E o comentário novo aparece no fim da lista com a identidade do user
    E o contrato REST continua intocado (author permanece string)

  Cenário: Sem erro de runtime ou console durante o fluxo
    Dado que o usuário abre cards, comenta e navega pelo board
    Então a interface não registra nenhum erro de runtime nem de console
    E nenhum avatar quebrado aparece nos cards ou nos comentários
