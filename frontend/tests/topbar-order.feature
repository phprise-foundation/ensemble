# language: pt-BR
Funcionalidade: Ordem dos controles do grupo direito do topbar
  Como pessoa que usa o board
  Quero que os controles do topo tenham posição fixa
  Para localizar Sim, grades, tema e perfil sem procurar

  Antecedente:
    Dado que a sessão está iniciada em uma tela desktop de 1025px ou mais

  Cenário: ordem completa em desktop
    Então o grupo direito contém exatamente os slots Sim, grade esquerda, grade inferior, grade direita, tema e perfil
    E a ordem visual das bordas esquerdas acompanha a ordem do DOM
    E o perfil é o último item à direita

  Cenário: Sim alterna os dados simulados
    Quando aciono o controle Sim
    Então o atributo aria-pressed de Sim reflete o estado da simulação

  Cenário: grade esquerda alterna o menu lateral
    Quando aciono a grade esquerda
    Então o menu lateral alterna entre expandido e colapsado
    E o aria-expanded da grade esquerda acompanha o painel

  Cenário: grade inferior alterna o console
    Quando aciono a grade inferior
    Então o console alterna entre visível e oculto
    E o aria-expanded da grade inferior acompanha o painel

  Cenário: grade direita alterna o copilot
    Quando aciono a grade direita
    Então o copilot alterna entre visível e oculto
    E o aria-expanded da grade direita acompanha o painel

  Cenário: tema alterna e perfil continua acessível
    Quando aciono o controle de tema
    Então o modo de tema alterna entre claro e escuro
    E o perfil permanece o último item do grupo e abre as opções de perfil e sair

  Cenário: tela mobile não exibe grades
    Dado que a viewport tem 1024px ou menos
    Então nenhum controle de grade é renderizado
    E o menu mobile, Sim, tema e perfil permanecem visíveis
    E não há rolagem horizontal

  Cenário: título longo trunca sem empurrar o grupo
    Dado que o título da página é longo
    Então o título trunca com reticências
    E o grupo direito permanece dentro da largura da tela
