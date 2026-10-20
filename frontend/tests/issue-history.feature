# language: pt-BR
Funcionalidade: Aba History do painel da issue
  Como pessoa que usa o board
  Quero ver a linha do tempo de uma issue em uma aba separada dos comentários
  Para entender o que aconteceu com a issue sem confundir com a conversa

  Antecedente:
    Dado que o painel de issue está aberto com o quadro KANB simulação ligada
    E a aba padrão é Comments

  Cenário: Comments é a aba padrão e o contador só existe nela
    Então a aba Comments está selecionada com aria-selected="true"
    E o painel de comentários está visível e o painel de History está oculto
    E o contador de comentários aparece apenas na aba Comments
    E a aba History não exibe contador

  Cenário: clicar em History mostra a linha do tempo em ordem inversa
    Quando aciono a aba History
    Então a linha do tempo lista os eventos do mais recente para o mais antigo
    E cada entrada tem ícone, autor, timestamp e resumo
    E nenhum comentário aparece dentro da History

  Cenário: os cinco tipos de evento têm ícone e frase próprios
    Dado que a issue tem eventos de criação, status, responsável, título e descrição
    Então cada tipo aparece com o ícone correspondente
    E os eventos de transição mostram o texto "de → para" com os nomes resolvidos

  Cenário: issue sem eventos mostra o estado vazio
    Dado que a issue aberta tem history vazia
    Quando aciono a aba History
    Então vejo a mensagem "No history yet" no lugar da linha do tempo

  Cenário: trocar de issue volta para Comments
    Dado que estou na aba History da issue aberta
    Quando outra issue é selecionada no quadro
    Então a aba ativa volta a ser Comments

  Cenário: navegação por teclado nas abas
    Dado que o foco está na aba Comments
    Quando pressiono a seta direita
    Então a aba History fica selecionada e recebe o foco
    Quando pressiono a seta direita novamente
    Então a seleção volta para a aba Comments com wrap
    E as setas esquerda, Home, End, Enter e Espaço selecionam a aba correta
    E o tabindex é roving: 0 na aba selecionada e -1 na outra
    E cada aba tem aria-selected, aria-controls e o painel associado com aria-labelledby

  Cenário: selo de protótipo depende da simulação
    Dado que a feature history está simulada
    Então a aba History exibe o selo "Prototype"
    Quando a simulação da feature history é desligada
    Então o selo desaparece e a linha do tempo continua no lugar

  Cenário: evento no contrato real sem type
    Dado que a history tem um evento com from, to, actor e at e sem type
    Então a linha do tempo renderiza como transição de status
    E um id de status desconhecido aparece como texto bruto
