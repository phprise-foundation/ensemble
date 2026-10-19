# language: pt-BR
Funcionalidade: Drawer em tela cheia abaixo de 768px
  Origem: KAN-86 (sub-task KAN-96). Complementa o drawer parcial de KAN-30.
  Faixas: abaixo de 768px o drawer ocupa a tela; entre 768px e 1024px o drawer
  parcial de 200px permanece; acima de 1024px o desktop não muda.

  Cenário: Tela cheia em 767px (última faixa móvel)
    Dado que a largura da viewport é 767px
    Quando o usuário abre o drawer pelo menu-toggle
    Então o painel fica com a largura igual ao clientWidth do documentElement
    E a borda esquerda e a borda superior do painel ficam em 0
    E o body recebe a classe drawer-locked com overflow hidden

  Cenário: Faixa parcial em 768px
    Dado que a largura da viewport é 768px
    Quando o usuário abre o drawer pelo menu-toggle
    Então o painel tem 200px de largura
    E o botão sidebar-close não existe
    E o body não recebe a classe drawer-locked
    E o scrim fecha o drawer ao receber o clique

  Cenário: Faixa parcial em 1023px e 1024px
    Dado que a largura da viewport é 1023px ou 1024px
    Quando o usuário abre o drawer pelo menu-toggle
    Então o painel tem 200px de largura com scrim
    E o botão sidebar-close não existe
    E o body não recebe a classe drawer-locked

  Cenário: Desktop em 1025px
    Dado que a largura da viewport é 1025px
    Então a sidebar fica fixa e visível com 200px de largura
    E não existe menu-toggle visível
    E não existe scrim

  Cenário: Fechamento pelo botão dedicado em 767px
    Dado que o drawer está aberto em tela cheia em 767px
    Quando o usuário clica no botão sidebar-close
    Então o drawer fecha
    E o body deixa de ter a classe drawer-locked

  Cenário: Fechamento por Escape em 767px
    Dado que o drawer está aberto em tela cheia em 767px
    Quando o usuário pressiona Escape
    Então o drawer fecha
    E o foco volta para o menu-toggle
    E o body deixa de ter a classe drawer-locked

  Cenário: Fechamento pela navegação em 767px
    Dado que o drawer está aberto em tela cheia em 767px
    Quando o usuário acessa um destino da navegação
    Então o drawer fecha ao trocar de rota
    E o body deixa de ter a classe drawer-locked

  Cenário: Alvos de toque de 44px em 767px
    Dado que o drawer está aberto em tela cheia em 767px
    Então o botão sidebar-close mede 44px por 44px
    E cada nav-entry e cada leaf tem altura de pelo menos 44px

  Cenário: Sem overflow horizontal em 320px, 375px e 767px
    Dado que a largura da viewport é 320px, 375px ou 767px
    Então o scrollWidth do documentElement não passa do clientWidth
    E não há erro de runtime ou console durante a interação
