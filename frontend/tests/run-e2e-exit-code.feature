# language: pt-BR
Funcionalidade: Código de saída do harness de E2E reflete o resultado do Cypress
  Origem: KAN-55 (achado da QA na KAN-37, `main` @ `bbf4c1f`).

  O harness `frontend/run-e2e.sh` (exposto como `npm run test:e2e`) sobe o dev
  server, roda o Cypress e derruba o servidor. O seu código de saída é a cerca
  de confiança de todo veredito de QA e da automação de PR: suíte vermelha
  precisa reprovar o comando. Hoje o status do `wait` é descartado e a última
  atribuição (`CYPRESS_PID=""`) fixa o status em 0, então qualquer falha do
  Cypress chega ao chamador como sucesso.

  Fixtures do contrato (fora de `cypress/e2e/`, portanto invisíveis à rodada
  padrão):
    | fixture                                  | resultado esperado |
    | cypress/harness/failing.cy.js            | reprova            |
    | cypress/harness/passing.cy.js            | passa              |

  Contexto:
    Dado que o harness roda o Cypress com `--spec` apontando para uma fixture
    E as fixtures só são alcançáveis com
      `--config "specPattern=cypress/harness/*.cy.js,screenshotOnRunFailure=false"`
    E a rodada padrão (sem `--spec`) continua restrita a `cypress/e2e/`
    E o `E2E_PORT` aponta para uma porta livre da rodada

  Cenário: spec vermelho reprova o comando canônico
    Dado que o spec selecionado tem uma falha real de asserção no navegador
    Quando rodo "npm run test:e2e" com esse spec
    Então a tabela do Cypress mostra o spec como reprovado
    E o comando termina com código de saída diferente de zero

  Cenário: spec verde mantém o comando verde
    Dado que o spec selecionado passa na interface
    Quando rodo "npm run test:e2e" com esse spec
    Então a tabela do Cypress mostra o spec como aprovado
    E o comando termina com código de saída zero
    E o verde não é forjado por um "exit 1" fixo no script

  Cenário: timeout do Cypress reprova o comando
    Dado que o Cypress excede o `E2E_TIMEOUT` configurado
    Quando o `timeout` mata o processo do Cypress
    Então o comando termina com código de saída diferente de zero
    E a interrupção não é confundida com sucesso

  Cenário: o cleanup roda mesmo no caminho vermelho
    Dado que o harness acabou de rodar um spec vermelho
    Quando o script termina de qualquer forma
    Então o dev server é derrubado
    E a porta do `E2E_PORT` fica livre para a próxima rodada
    E o código de saída diferente de zero é preservado depois do cleanup
