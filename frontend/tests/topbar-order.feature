# language: pt-BR
Funcionalidade: Ordem dos controles do topbar e slots das grades
  Origem: RFC KAN-87. Contrato único em `docs/KAN-87-guide.md`.
  A ordem do DOM é a mesma da ordem visual, da esquerda para a direita:
  Sim → grade esquerda → grade inferior → grade direita → tema → perfil.
  A fase 1 (KAN-97/KAN-99) entrega Sim, grade esquerda, grade direita, tema e
  perfil; a fase 2 (KAN-98/KAN-100) encaixa a grade inferior do console.

  Contexto:
    Dado que o contrato de posição é `docs/KAN-87-guide.md`
    E que `[data-topbar-slot]` é o seletor público de ordem
    E que o corte mobile é o único `--breakpoint-sidebar` (≤1024 mobile, ≥1025 desktop)

  @fase1
  Cenário: Ordem exata do grupo direito em desktop na fase 1
    Dado que a largura da tela é 1280px
    E que o console ainda não está na árvore da aplicação
    Quando a aplicação é carregada
    Então os slots dentro de `.topbar-actions` são, nesta ordem:
      | slot       |
      | sim        |
      | grid-left  |
      | grid-right |
      | theme      |
      | profile    |
    E o slot `grid-bottom` não existe no DOM
    E a coordenada `left` de cada slot cresce na mesma ordem do DOM
    E o slot `profile` é o último nó e sua borda direita coincide com a do container

  @fase2 @kan-100
  Cenário: Ordem completa com a grade inferior do console
    Dado que a largura da tela é 1280px
    E que o console (KAN-18) está na árvore da aplicação
    Quando a aplicação é carregada
    Então os slots são, nesta ordem:
      | slot        |
      | sim         |
      | grid-left   |
      | grid-bottom |
      | grid-right  |
      | theme       |
      | profile     |
    E `grid-bottom` fica entre as duas grades
    E o botão do console alterna o painel `#app-console`
    E o `aria-expanded` do botão acompanha o estado do painel

  Cenário: Sim é um botão de alternância, não um rótulo inerte
    Dado que a simulação está ligada
    Quando o usuário clica no slot `sim`
    Então a simulação é desligada
    E o slot continua sendo um `button` com dot e o rótulo visível `Sim`
    E `aria-pressed` passa a valer `false`
    E ao clicar de novo a simulação volta e `aria-pressed` vale `true`

  Cenário: Grade esquerda alterna o sidebar e mantém o atalho
    Dado que o sidebar está expandido em desktop
    Então o slot `grid-left` tem `aria-controls="app-sidebar"` e `aria-expanded="true"`
    Quando o usuário clica no slot `grid-left`
    Então o sidebar recebe a classe `.collapsed`
    E o slot expõe `aria-expanded="false"`
    Quando o usuário pressiona Ctrl+B
    Então o sidebar expande e o slot expõe `aria-expanded="true"`

  Cenário: Grade direita alterna o copilot e mantém o atalho
    Dado que o copilot está expandido em desktop
    Então o slot `grid-right` tem `aria-controls="app-copilot"` e `aria-expanded="true"`
    Quando o usuário clica no slot `grid-right`
    Então o painel `#app-copilot` sai do DOM
    E o slot expõe `aria-expanded="false"`
    Quando o usuário pressiona Ctrl+Alt+B
    Então o painel volta e o slot expõe `aria-expanded="true"`

  Cenário: Tema é o último controle de grade e tema
    Dado que a tela está em modo desktop
    Então o slot `theme` fica imediatamente antes de `profile`
    E tem `aria-label` e `title` coerentes com o modo atual
    Quando o usuário clica no slot `theme`
    Então o tema alterna entre claro e escuro
    E `aria-label` e `title` passam a descrever o novo modo

  Cenário: Perfil é o último item e abre profile/logout
    Dado que a tela está em modo desktop
    Então o slot `profile` é o último nó do grupo direito
    Quando o usuário clica no perfil
    Então o menu abre com os itens `profile` e `logout`

  Cenário: Nenhum controle de grade em largura mobile
    Dado que a largura da tela é 1024px
    Quando a aplicação é carregada
    Então nenhum nó `[data-topbar-slot^="grid-"]` existe no DOM
    E nenhum botão `.sidebar-toggle`, `.console-toggle` ou `.copilot-toggle` existe
    E o menu mobile, `sim`, `theme` e `profile` permanecem visíveis
    E não há overflow horizontal no documento nem no topbar

  Cenário: Sem overflow no primeiro pixel desktop
    Dado que a largura da tela é 1025px
    Quando a aplicação é carregada
    Então os slots de grade existem na ordem canônica
    E não há overflow horizontal no documento nem no topbar
    E o título trunca com `text-overflow: ellipsis` antes de empurrar o grupo

  Cenário: Slot estrutural, sem controle morto
    Dado que a presença do slot `grid-bottom` é estrutural
    Então `grid-bottom` existe se e somente se o painel do console existir
    E não existe flag de disponibilidade, placeholder, botão oculto ou desabilitado
    E cada grade desktop usa `v-if="!isMobile"` além da regra CSS desktop-only

  Cenário: Acessibilidade e teclado de todos os controles
    Dado que a tela está em modo desktop
    Então todo slot tem `aria-label` ou `title` legível
    E cada grade disponível aponta para seu painel via `aria-controls`
    E todo controle recebe foco visível (`:focus-visible`) ao navegar por teclado
    E `Ctrl+B` e `Ctrl+Alt+B` continuam alternando sidebar e copilot sem interferência mútua
