# language: pt-BR
# não há arquivos de spec correspondentes ao specPattern
# com --spec vazio, o Cypress retorna status 0
# com --spec não-vazio, mas que não casa, retorna status 0 ou 1 dependendo da versão

Funcionalidade: Rodar E2E com --spec que não casa
  O objetivo é garantir que o run-e2e.sh trate corretamente o caso
  onde o --spec não casa com nenhuma spec file

  Cenário: --spec sem match com arquivos de spec
    Dado que executo o run-e2e.sh com --spec=invalid-pattern
    Quando o Cypress é executado
    Então o status de saída deve ser 1

  Cenário: --spec vazio
    Dado que executo o run-e2e.sh com --spec=""
    Quando o Cypress é executado
    Então o status de saída deve ser 1

  Cenário: Sem --spec
    Dado que executo o run-e2e.sh sem --spec
    Quando o Cypress é executado
    Então o status de saída deve ser 0