# Funcionalidade: Diálogos e toasts acessíveis

Como pessoa que usa o board
Quero confirmações e avisos próprios, acessíveis e previsíveis
Para excluir, salvar e receber resultados sem diálogos nativos

## Cenário: confirmação com foco inicial no cancelar
Dado um diálogo de exclusão com variante danger
Quando ele abre
Então o foco vai para o botão Cancelar
E o card tem role alertdialog e aria-modal true

## Cenário: cancelar com clique
Dado um diálogo de confirmação aberto
Quando clico em Cancelar
Então a promise resolve false
E o diálogo fecha

## Cenário: cancelar com Escape
Dado um diálogo de confirmação aberto
Quando pressiono Escape
Então a promise resolve false
E o diálogo fecha

## Cenário: confirmar com Enter fora de um botão
Dado um diálogo de confirmação aberto
Quando pressiono Enter com o foco fora dos botões
Então a promise resolve true

## Cenário: confirmar pelo botão
Dado um diálogo de confirmação aberto
Quando clico no botão de confirmação
Então a promise resolve true

## Cenário: retorno de foco
Dado um gatilho que abriu o diálogo
Quando o diálogo fecha
Então o foco volta para o gatilho

## Cenário: scroll lock e inert
Dado um diálogo aberto
Quando ele monta
Então o body fica sem scroll
E o app fica inert
Quando ele fecha
Então o scroll e o inert são restaurados

## Cenário: scrim configurável
Dado um diálogo com scrimCloses false
Quando clico no scrim
Então ele continua aberto
Dado um alert
Quando clico no scrim
Então ele fecha

## Cenário: uma caixa por vez
Dado um diálogo já aberto
Quando peço outro confirm
Então o segundo resolve false imediatamente

## Cenário: alerta tem só confirmação
Dado um alerta
Quando ele abre
Então não há botão de cancelar
E o rótulo de confirmação é OK

## Cenário: toasts empilham até três
Dado três toasts na tela
Quando um quarto é criado
Então o mais antigo sai
E restam três

## Cenário: toasts expiram
Dado um toast de sucesso
Quando passam 4 segundos
Então ele desaparece
Dado um toast de erro
Quando passam 6 segundos
Então ele desaparece

## Cenário: guarda contra diálogos nativos
Dado o código de src
Quando a guarda procura window.confirm, alert ou prompt
Então não encontra nenhuma ocorrência
E chamadas a dialog.confirm e dialog.alert continuam permitidas

## Cenário: quatro fluxos migrados
Dado os fluxos de exclusão de CRUD, agente, tipo de issue e sidebar
Quando confirmo a exclusão
Então o diálogo próprio é usado
E um toast de sucesso é exibido
Quando cancelo
Então nada é excluído
