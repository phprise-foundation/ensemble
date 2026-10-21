# Funcionalidade: Avatar acessível e guarda de upload

Como interface do board
Quero um avatar circular com fallback legível e uma guarda de upload
Para identificar pessoas e agentes sem perder acessibilidade

## Cenário: tamanhos circulares
Dado o componente Avatar com os tamanhos sm, md e lg
Quando ele é renderizado em cada tamanho
Então o elemento tem a classe do tamanho e borda circular
E as medidas vêm do CSS, não de estilo inline

## Cenário: recorte central da foto
Dado um Avatar com imagem
Quando a imagem é exibida
Então ela ocupa o quadrado inteiro com object-fit cover e object-position center

## Cenário: inicial maiúscula no fallback
Dado um Avatar sem imagem e com o nome "vivy"
Quando ele é renderizado
Então o texto exibido é "V"
E a cor de fundo é a cor informada

## Cenário: nome vazio
Dado um Avatar sem imagem e com nome vazio
Quando ele é renderizado
Então o texto exibido é "?"

## Cenário: contraste do fallback
Dado um Avatar sem imagem
Quando a cor de fundo é clara, escura ou média
Então o contraste entre texto e fundo é de pelo menos 4,5:1

## Cenário: ARIA do avatar informativo
Dado um Avatar não decorativo com nome "Vivy"
Quando ele é renderizado
Então ele tem role img e aria-label "Avatar de Vivy"

## Cenário: avatar decorativo
Dado um Avatar decorativo
Quando ele é renderizado
Então ele tem aria-hidden true e não expõe role nem aria-label

## Cenário: erro de imagem cai no fallback
Dado um Avatar com imagem
Quando a imagem dispara erro
Então a imagem some e a inicial aparece
E trocar a imagem volta a exibir a nova foto

## Cenário: upload válido
Dado um arquivo PNG, JPEG ou WebP de até 2 MB
Quando a guarda valida o arquivo
Então não há erro e a leitura devolve um data URL

## Cenário: upload inválido
Dado um arquivo de tipo não suportado ou acima de 2 MB
Quando a guarda valida o arquivo
Então a mensagem custom é retornada
E a leitura rejeita com a mesma mensagem

## Cenário: retrato do seed
Dado o seed do board
Quando ele é carregado
Então Vivy nasce com um portrait PNG em data URL
