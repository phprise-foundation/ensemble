# Contrato de captura do spec icon-standard (origem: KAN-154, item 1).
# language: pt-BR
Funcionalidade: Capturas do spec de ícones não travam a suíte no Electron
  Como o CI que roda a suíte Cypress no Electron (runner padrão)
  Quero que cada cy.screenshot() do icon-standard.cy.js complete dentro do teto
  Para que o spec não consuma ~189 s de capturas travadas nem estoure o
  orçamento do run-e2e.sh

  Contexto:
    Dado que o spec declara screenshotOnRunFailure: false
    E que a suíte roda no Electron que o Cypress traz por padrão
    E que o teto de cada captura é o timeout nativo de 30000 ms do cy.screenshot

  Cenário: cada captura do spec retorna antes do teto
    Quando o spec executa qualquer captura (topbar-padrao, sidebar-padrao,
      crud-agents-lista, crud-agents-modal, crud-issue-types-lista,
      crud-issue-types-modal, painel-issue ou corte-1024)
    Então o cy.screenshot correspondente retorna sem CypressError de timeout
    E a captura correspondente existe no diretório de screenshots da rodada

  Cenário: o spec inteiro fecha verde no Electron dentro do orçamento do harness
    Quando o run-e2e.sh executa apenas o spec icon-standard no Electron
    Então os 6 testes saem verdes
    E o spec termina sem nenhuma captura abortada
    E o tempo do spec fica abaixo do orçamento por spec do harness (60 s + 120 s)

  Cenário: se a captura travar, a falha é determinística e diagnosticável
    Dado que uma captura excede o teto de 30000 ms
    Então a falha aponta a captura e o teste responsáveis (nome da captura no erro)
    E a rodada aborta sem pendurar o resto da suíte
    E nenhuma outra captura da suíte repete o travamento na mesma rodada
