# language: pt-BR
Funcionalidade: Colapso do menu lateral esquerdo
  Origem: KAN-10.

  Cenário: Sidebar expandido por padrão no desktop
    Dado que a largura da tela é superior ao breakpoint móvel
    Quando a aplicação é carregada
    Então o menu lateral tem largura completa (200px ou mais)

  Cenário: Botão de toggle visível no topo
    Dado que a tela está em modo desktop
    Então o botão `.sidebar-toggle` está visível

  Cenário: Colapsar o sidebar
    Dado que o sidebar está expandido
    Quando o usuário clica no botão de toggle
    Então o sidebar tem a classe `.collapsed`
    E o sidebar tem largura aproximadamente 48px

  Cenário: Expandir o sidebar
    Dado que o sidebar está colapsado
    Quando o usuário clica no botão de toggle
    Então o sidebar NÃO tem a classe `.collapsed`

  Cenário: Estado convergente e sem persistência na Fase 1
    Dado que o sidebar está expandido
    Quando o usuário alterna o estado três vezes seguidas
    Então o sidebar termina colapsado
    E ao recarregar a aplicação o sidebar volta expandido

  Cenário: Tipos de navegação preservados
    Dado que o sidebar está colapsado
    Então os ícones de navegação permanecem acessíveis

  Cenário: Hover exibe tooltip
    Dado que o sidebar está colapsado
    Quando o usuário passa o mouse sobre um ícone de navegação
    Então um tooltip com nome legível aparece

  Cenário: Atalho Ctrl+B alterna
    Dado que o sidebar está expandido
    Quando o usuário pressiona Ctrl+B
    Então o sidebar colapsa
    E quando o usuário pressiona Ctrl+B novamente
    Então o sidebar expande

  Cenário: Acessibilidade do botão
    Dado que a tela está em modo desktop
    Então o botão de toggle tem `aria-label="Toggle sidebar"`
    E tem o atributo `aria-expanded`
    E recebe foco visível ao navegar por teclado

  Cenário: Comportamento mobile inalterado
    Dado que a largura da tela é inferior ou igual ao breakpoint móvel
    Então o botão `.sidebar-toggle` não está visível

  Cenário: Ponto de corte compartilhado com a KAN-9
    Dado que o ponto de corte desktop/mobile é decidido uma única vez
    Então a regra do drawer mobile e a regra do toggle desktop usam a mesma variável SCSS
