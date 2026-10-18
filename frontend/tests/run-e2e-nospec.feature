# language: pt-BR
# na main (Cypress 16.2.0), --spec vazio ativa o specPattern default e
# a rodada sai com 0; o portão converte 0 em 1 apenas quando a saída
# casa com "no spec files found" (contrato de saída do item)

Funcionalidade: Rodar E2E com --spec que não casa
  O objetivo é garantir que o run-e2e.sh trate corretamente o caso
  onde o --spec não casa com nenhuma spec file

  Cenário: --spec sem match com arquivos de spec
    Dado que executo o run-e2e.sh com --spec=invalid-pattern
    Quando o Cypress é executado
    Então o status de saída deve ser 1

  Cenário: --spec vazio
    Dado que executo o run-e2e.sh com --spec=""
    Quando o Cypress é executado sem nenhum spec correspondente
    Então o status de saída deve ser 0
    E o specPattern default da rodada padrão é aplicado

  Cenário: Sem --spec
    Dado que executo o run-e2e.sh sem --spec
    Quando o Cypress é executado
    Então o status de saída deve ser 0
