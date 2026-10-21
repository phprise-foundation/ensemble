# Contrato do harness run-e2e.sh: isolamento de dados por invocação.
# language: pt-BR
Funcionalidade: Isolamento de dados do harness de contratos E2E (run-e2e.sh)
  Como desenvolvedor que roda `node --test frontend/tests/*.test.mjs` ou
  `npm run test:e2e` em qualquer árvore de trabalho
  Quero que cada invocação do run-e2e.sh controle onde os dados vivem
  Para que corridas entre invocações não truncem o banco e o db do
  desenvolvedor nunca seja vítima nem refém do teste

  Cenário: Arquivo de dados efêmero por invocação
    Dado que o repositório tem um .data/db.json com a assinatura do desenvolvedor
    E que run-e2e.sh recebe E2E_DATA_FILE apontando para um arquivo temporário
      que ainda não existe
    Quando o harness executa uma suíte Cypress mínima
    Então o .data/db.json do desenvolvedor permanece com os mesmos bytes de antes
    E o arquivo efêmero nasce com o seed legacy (sem a chave `user`)
    E o comando sai com status 0

  Cenário: O dev server lê e grava o arquivo efêmero, não o db do desenvolvedor
    Dado que run-e2e.sh recebe E2E_DATA_FILE apontando para um arquivo efêmero
    E que o spec da rodada grava através do backend fake (ex.: PATCH /api/user)
    Quando o spec termina
    Então a gravação persistiu no arquivo efêmero
    E o backend fake não tocou o .data/db.json do desenvolvedor

  Cenário: Duas invocações concorrentes do harness não se corrompem
    Dado que dois processos run-e2e.sh partem em paralelo
    E que cada um usa E2E_PORT e E2E_DATA_FILE próprios
    Quando ambas as suítes Cypress mínimas terminam
    Então ambos os comandos saem com status 0
    E cada arquivo de dados efêmero contém JSON válido, sem truncamento
    E o .data/db.json do desenvolvedor permanece intocado

  Cenário: Sem E2E_DATA_FILE o comportamento atual é preservado
    Dado que o repositório tem um .data/db.json com a assinatura do desenvolvedor
    E que run-e2e.sh roda sem a env E2E_DATA_FILE
    Quando o harness termina
    Então o .data/db.json volta com exatamente os bytes de antes da rodada
    E o comando sai com status 0
