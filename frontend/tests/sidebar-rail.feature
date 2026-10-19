# Funcionalidade: rail do sidebar colapsado

  Como pessoa usuária em desktop
  Quero um rail apenas com páginas folha clicáveis
  Para navegar sem ícones mortos quando o sidebar está colapsado

  Contexto:
    Dado que estou em uma janela de 1280x800
    E abri a aplicação em "/"

  Cenário: contagem do rail igual às folhas da árvore
    Quando aciono o botão de alternar o sidebar
    Então o sidebar recebe a classe "collapsed"
    E o rail exibe um link para cada folha da árvore mais a folha Experiences
    E cada link do rail tem ícone de 18px e não vazio

  Cenário: nenhum elemento não navegável no rail
    Quando aciono o botão de alternar o sidebar
    Então o rail não exibe entrada de grupo, chevron, lápis, badge, caminho de source nem rótulo
    E a árvore expandida fica oculta
    E a marca do sidebar fica sem largura

  Cenário: tooltip, nome acessível e rota ativa
    Quando aciono o botão de alternar o sidebar
    Então cada link do rail tem "title" não vazio igual ao seu "aria-label"
    E o board atual é o único link com "aria-current" igual a "page"

  Cenário: clique navega e atualiza o destaque
    Quando aciono o botão de alternar o sidebar
    E clico no link Experiences do rail
    Então a rota passa a ser "#/experiences"
    E o link Experiences fica com "aria-current" igual a "page"
    E clicar em cada link do rail navega para o seu endereço e destaca apenas ele

  Cenário: voltar ao expandido restaura a árvore
    Quando aciono o botão de alternar o sidebar duas vezes
    Então o rail deixa de existir
    E a árvore expandida volta a exibir todas as suas folhas
