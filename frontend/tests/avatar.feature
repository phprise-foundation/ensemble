# language: pt-BR
# Origem: KAN-89
# Guia: docs/KAN-89-guide.md

Funcionalidade: Avatar circular de agentes
  O componente Avatar exibe um avatar circular com recorte central,
  fallback de inicial e suporte à imagem com contraste adequado.

  Cenário: Tamanhos circulares
    Dado que o componente Avatar está sendo montado
    Quando o tamanho é 'sm'
    Então deve ter classes 'avatar sm' e raio 50%
    E deve ter 22×22 px
    E terá o objeto com 'object-fit: cover' e 'object-position: center'

    Quando o tamanho é 'md'  
    Então deve ter classes 'avatar md' e raio 50%
    E deve ter 42×42 px
    E terá o objeto com 'object-fit: cover' e 'object-position: center'

    Quando o tamanho é 'lg'
    Então deve ter classes 'avatar lg' e raio 50%
    E deve ter 64×64 px
    E terá o objeto com 'object-fit: cover' e 'object-position: center'

  Cenário: Fallback com inicial e contraste
    Dado que o componente Avatar está sendo montado
    Quando o nome é 'Vivy' e imagem está vazia
    Então deve exibir 'V' como inicial maiúscula
    E deve ter contraste ≥ 4,5:1 para cores claras, escuras e médias

  Cenário: Imagem quebrada cai no fallback
    Dado que o componente Avatar está sendo montado
    Quando a imagem está definida mas falha no carregamento
    Então deve mostrar o fallback com a inicial maiúscula
    E não deve ter ícone de imagem quebrada

  Cenário: ARIA quando não decorativo
    Dado que o componente Avatar está sendo montado
    Quando o nome é 'Vivy' e não é decorativo
    Então deve ter role="img" e aria-label="Avatar de Vivy"
    E o elemento img interno deve ter alt=""

  Cenário: ARIA quando decorativo
    Dado que o componente Avatar está sendo montado
    Quando o nome é 'Vivy' e é decorativo
    Então deve ter aria-hidden="true" e não deve ter role nem aria-label

  Cenário: Anel opcional desligado por padrão
    Dado que o componente Avatar está sendo montado
    Quando o anel não é ativado
    Então não deve ter a classe 'ring'
    E deve ter raio 50% com recorte central
    
    Quando o anel é ativado
    Então deve ter a classe 'ring'
    E deve ter anel de 2px com a cor do accent

  Cenário: Upload válido atualiza o preview
    Dado que o componente Avatar está sendo montado
    Quando uma imagem válida é fornecida
    Então deve mostrar a imagem do arquivo

  Cenário: Salvar reflete na lista e no painel
    Dado que o componente Avatar está sendo montado
    Quando a imagem é carregada e salva
    Então deve refletir a imagem atualizada em todas as instâncias

  Cenário: Remoção do foto
    Dado que o componente Avatar está sendo montado com uma imagem
    Quando o botão "Remove photo" é clicado
    Então deve retornar ao fallback com a inicial maiúscula

  Cenário: Upload de arquivo inválido ou acima do limite
    Dado que o componente Avatar está sendo montado
    Quando um tipo de arquivo inválido é selecionado
    Então deve mostrar a mensagem de erro
    E manter a imagem atual

    Quando um arquivo acima de 2 MB é selecionado
    Então deve mostrar a mensagem de erro
    E manter a imagem atual

  Cenário: Rótulo sem "(3x4)"
    Dado que o componente Avatar está sendo montado
    Quando o campo de foto tem rótulo mostrado
    Então o rótulo não deve conter "(3x4)"