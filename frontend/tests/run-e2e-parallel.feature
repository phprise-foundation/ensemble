# Contrato do harness run-e2e.sh: composição paralela dos testes de
# contrato sob a carga do CI (critério de aceite de KAN-125).
# language: pt-BR
Funcionalidade: Contratos de E2E passam em execução paralela com a porta 5173 ocupada
  Como o CI que roda `node --test frontend/tests/*.test.mjs`
  Quero que os arquivos de contrato run-e2e-nospec e run-e2e-exit-code passem juntos
  Para que nenhum PR quebre por corrida no harness, não importa o que use a porta 5173

  Contexto:
    Dado que um servidor estranho escuta na porta 5173 (o dev server de uma
      rotina de QA ou o servidor de outra árvore de trabalho)
    E que `node --test` executa os arquivos de teste em paralelo
    E que run-e2e-nospec e run-e2e-exit-code cada um invoca run-e2e.sh
      de verdade (dev server + Cypress reais, não simulação)

  Cenário: Os dois arquivos de contrato passam na mesma rodada do node --test
    Dado que a porta 5173 está ocupada pelo servidor estranho
    Quando o `node --test` executa run-e2e-nospec.test.mjs e
      run-e2e-exit-code.test.mjs juntos, cada um como subprocesso próprio
    Então ambos os arquivos saem com status 0
    E nenhum teste tenta usar a porta 5173
    E nenhum deles lê ou grava o .data/db.json da árvore de desenvolvimento
    E nenhuma saída contém "Unexpected end of JSON input" nem "already in use"

  Cenário: Os arquivos de contrato passam porta e arquivo de dados próprios
    Dado que cada contrato invoca run-e2e.sh com a porta e o arquivo de dados
      que o próprio teste escolheu (porta efêmera livre, arquivo efêmero)
    Quando o `node --test` executa os dois arquivos em paralelo
    Então cada invocação do harness usa o par porta/arquivo que recebeu
    E a necessidade de rodar sobre o mesmo .data/db.json desaparece da suíte
