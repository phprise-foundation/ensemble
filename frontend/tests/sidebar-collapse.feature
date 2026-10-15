# language: pt-BR
Funcionalidade: Colapso do menu lateral
  Origem: KAN-10.

  Cenário: Sidebar expandido por padrão no desktop
    Dado que a largura da tela é superior ao breakpoint móvel (> 1024px)
    Quando a aplicação é carregada
    Então o sidebar tem largura completa (200px ou mais)

  Cenário: Botão de toggle visível no topo
    Dado que é desktop
    Então o botão `.sidebar-toggle` está visível

  Cenário: Colapsar o sidebar
    Dado que o sidebar está expandido
    Quando o usuário clica no toggle
    Então o sidebar tem a classe `.collapsed` e largura de ~48px

  Cenário: Expandir o sidebar
    Dado que o sidebar está colapsado
    Quando o usuário clica no toggle
    Então o sidebar NÃO tem a classe `.collapsed`

  Cenário: Tipos de navegação preservados
    Dado que o sidebar está colapsado
    Então os ícones de navegação permanecem acessíveis

  Cenário: Hover exibe tooltip
    Dado que o sidebar está colapsado
    Quando o usuário faz hover sobre ícone de navegação
    Então um tooltip com nome legível aparece

  Cenário: Atalho Ctrl+B alterna
    Dado que o sidebar está expandido
    Quando o usuário pressiona Ctrl+B
    Então o sidebar colapsa
    E quando o usuário pressiona Ctrl+B novamente
    Então o sidebar expande

  Cenário: Acessibilidade do botão
    Dado que é desktop
    Então o botão tem `aria-label="Toggle sidebar"` e `aria-expanded`

  Cenário: Comportamento mobile inalterado
    Dado que a largura da tela é inferior ou igual ao breakpoint móvel (≤ 1024px)
    Então o botão `.sidebar-toggle` não está visível