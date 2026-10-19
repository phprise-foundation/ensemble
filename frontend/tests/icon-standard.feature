# language: pt-BR
Funcionalidade: Semântica de ícones padronizada com Lucide
  A SPA usa o wrapper AppIcon como única fonte de ícones de interface,
  com Lucide atrás, escala fixa 14/16/18/20 e nome acessível em todo
  botão só de ícone. A marca smartboard é a única exceção do wrapper.

  Cenário: Editar sempre com lápis
    Dado um botão de edição na árvore de componentes
    Quando o ícone é reservado à ação
    Então o botão usa "name=edit" com o glifo Pencil
    E o título "Edit" nunca aparece com "profiles" nem com "close"

  Cenário: Excluir sempre com lixeira
    Dado um botão de exclusão na árvore de componentes
    Quando o ícone é reservado à ação
    Então o botão usa "name=delete" com o glifo Trash2
    E o título "Delete" nunca aparece com "close"

  Cenário: Adicionar com Plus e salvar com Check
    Dado botões de criação e salvamento
    Então quem adiciona usa "name=plus"
    E quem salva usa "name=check"

  Cenário: Fechar com X
    Dado botões de fechamento de modal, painel e drawer
    Então usam "name=close" e o título "Close" jamais coexiste com "delete"

  Cenário: Escala única de tamanhos
    Dado todas as chamadas estáticas de AppIcon em frontend/src
    Então cada "size" pertence a 14, 16, 18 ou 20
    E a ausência de size só acontece nos controles do topbar e dos grupos

  Cenário: Logo custom como exceção
    Dado o componente AppIcon
    Então o glifo "smartboard" é desenhado com paths manualmente
    E nenhum outro nome vira Lucide importado

  Cenário: Nome acessível em botão só de ícone
    Dado todo botão cujo único conteúdo é AppIcon
    Então ele tem aria-label
    E o aria-label acompanha o title do tooltip

  Cenário: Único svg de UI
    Dado todos os arquivos .vue de frontend/src
    Então só AppIcon.vue contém "<svg"
    E nenhum outro componente desenha ícone manualmente

  Cenário: Guarda do tema
    Dado o alternador de tema no topbar
    Então o ternário "sun"/"moon" está coberto por assinatura
