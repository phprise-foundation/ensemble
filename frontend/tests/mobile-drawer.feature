# language: pt-BR
Funcionalidade: Menu lateral esquerdo com comportamento de drawer em telas pequenas
  Origem: KAN-9.

  Cenário: Menu lateral recolhido por padrão em telas pequenas
    Dado que a largura da tela é inferior ao breakpoint móvel
    Quando a aplicação é carregada
    Então o menu lateral esquerdo fica oculto por padrão
    E um botão acionador visível é exibido no topo esquerdo

  Cenário: Abertura e fechamento do drawer por overlay e acionador
    Dado que a largura da tela é inferior ao breakpoint móvel
    E o menu lateral está oculto
    Quando o usuário clica no acionador do menu
    Então o menu lateral sobrepõe o conteúdo como overlay
    E ao clicar fora do menu (scrim) ou no acionador novamente, o menu é fechado

  Cenário: Fechamento automático ao navegar
    Dado que o menu lateral em drawer está aberto em tela pequena
    Quando o usuário seleciona e clica em um item de navegação
    Então o menu lateral é fechado automaticamente

  Cenário: Acessibilidade do drawer (A11y)
    Dado que o menu lateral em drawer está aberto em tela pequena
    Então o foco do teclado fica preso dentro do drawer (focus trap)
    E pressionar a tecla ESC fecha o drawer e restaura o foco anterior

  Cenário: Comportamento desktop inalterado
    Dado que a largura da tela é superior ao breakpoint móvel
    Então o menu lateral exibe comportamento fixo de desktop sem sobreposição
