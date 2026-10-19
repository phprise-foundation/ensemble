# language: pt-BR
Funcionalidade: Colapso do painel do copilot (grade direita)
  Origem: RFC KAN-62, critérios de aceite 1 a 5.

  Cenário: Painel expandido por padrão no desktop (1280px)
    Dado que a largura da tela é 1280px
    Quando a aplicação é carregada
    Então o painel `#app-copilot` está visível
    E a largura dele é min(25vw, 400px) — 320px nesse viewport
    E o símbolo `copilot-toggle` está visível no menu superior

  Cenário: Painel expandido por padrão no desktop (1920px)
    Dado que a largura da tela é 1920px
    Quando a aplicação é carregada
    Então o painel `#app-copilot` está visível com largura 400px

  Cenário: Clicar no símbolo colapsa o painel
    Dado que o painel do copilot está expandido
    Quando o usuário clica no botão `copilot-toggle`
    Então o painel `#app-copilot` sai do DOM
    E o botão tem `aria-expanded` valendo `false`

  Cenário: Clicar de novo expande o painel
    Dado que o painel do copilot está colapsado
    Quando o usuário clica no botão `copilot-toggle`
    Então o painel `#app-copilot` volta ao DOM
    E o botão tem `aria-expanded` valendo `true`

  Cenário: O controle não afeta sidebar, painel de issue nem colunas do board
    Dado que o sidebar está expandido e um issue está aberto no painel
    Quando o usuário alterna o copilot por clique ou atalho
    Então o sidebar mantém o estado que tinha antes
    E o painel de issue continua visível com o mesmo título
    E as colunas do board continuam visíveis

  Cenário: Em viewport no breakpoint nem painel nem símbolo existem no DOM
    Dado que a largura da tela é 1024px (no breakpoint, lado mobile)
    Quando a aplicação é carregada
    Então o elemento `#app-copilot` não existe no DOM
    E o botão `copilot-toggle` não existe no DOM

  Cenário: Em viewport de telefone nem painel nem símbolo existem no DOM
    Dado que a largura da tela é 390px (viewport de telefone)
    Quando a aplicação é carregada
    Então o elemento `#app-copilot` não existe no DOM
    E o botão `copilot-toggle` não existe no DOM

  Cenário: Recarregar a página volta com o painel expandido (sem memória)
    Dado que o usuário colapsou o painel do copilot
    Quando a página é recarregada
    Então o painel volta expandido

  Cenário: Acessibilidade do botão e atalho (bônus)
    Dado que a tela está em modo desktop
    Então o botão tem `aria-label="Toggle copilot"`
    E tem o atributo `aria-expanded` ligado ao estado do painel
    E tem `aria-controls="app-copilot"`
    E o atalho Ctrl+Alt+B (ou Cmd+Alt+B) alterna o painel
    E o atalho Ctrl+Alt+B não colapsa o sidebar
