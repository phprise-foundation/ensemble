# Funcionalidade: rail do sidebar colapsado

  Como pessoa usuária em desktop
  Quero um rail apenas com páginas folha clicáveis
  Para navegar sem ícones mortos quando o sidebar está colapsado

  Contexto:
    Dado que estou em uma janela de 1280x800
    E abri a aplicação em "#/boards/KANB"

  Cenário: contagem do rail igual às folhas do modelo
    Quando aciono o botão de alternar o sidebar
    Então o sidebar recebe a classe "collapsed"
    E o rail exibe um link para cada folha da árvore expandida mais a folha Experiences
    E o último link do rail é "Experiences", que não existe na árvore expandida
    E cada link do rail tem ícone de 18px e não vazio

  Cenário: nenhum elemento não navegável no rail
    Quando aciono o botão de alternar o sidebar
    Então o rail não exibe entrada de grupo, chevron, lápis, badge, caminho de source, rótulo nem ponto de simulação
    E a árvore expandida fica oculta
    E a marca do sidebar fica sem largura

  Cenário: tooltip, nome acessível e rota ativa
    Quando aciono o botão de alternar o sidebar
    Então cada link do rail tem "title" não vazio igual ao seu "aria-label"
    E o board atual é o único link com "aria-current" igual a "page"

  Cenário: clique navega e atualiza o destaque em cada link
    Quando aciono o botão de alternar o sidebar
    E clico em cada link do rail
    Então a rota passa a ser o endereço do link
    E apenas esse link fica com "aria-current" igual a "page"
    E o link atual também recebe "router-link-active"

  Cenário: destaque com mais de uma entidade do mesmo tipo
    Dado que o workspace também tem o board "Board 2", o workflow "Flow B" e o time "Design team"
    Quando abro o board "Board 2" e colapso o sidebar
    Então apenas o link "Board 2" fica com "aria-current" igual a "page"
    Quando abro a página de status do workflow "Flow B"
    Então apenas o link "Flow B Statuses" fica com "aria-current" igual a "page"
    Quando abro a página de papéis do time "Design team"
    Então apenas o link "Design team Roles" fica com "aria-current" igual a "page"

  Cenário: destaque mantido após recarregar em deep link
    Dado que abri o sidebar na página de transições do workflow "Standard"
    Quando recarrego a página e colapso o sidebar de novo
    Então apenas o link "Standard Transitions" fica com "aria-current" igual a "page"

  Cenário: navegação por teclado e foco visível
    Quando colapso o sidebar
    E parto do primeiro link do rail
    Então ele tem contorno de foco de 2px
    E cada Tab move o foco para o link seguinte do rail
    E o link focado é um único ícone clicável, sem texto

  Cenário: voltar ao expandido restaura a árvore
    Quando aciono o botão de alternar o sidebar duas vezes
    Então o rail deixa de existir
    E a árvore expandida volta a exibir todas as suas folhas

  Cenário: fronteira de desktop
    Dado que estou em uma janela de 1024px
    Então o botão de alternar não aparece e a árvore fica visível
    Quando aumento a janela para 1025px e colapso o sidebar
    Então o rail aparece com links
