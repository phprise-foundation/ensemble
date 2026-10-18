# Guia de Implementação — KAN-59

Portão "no spec files found" no harness de E2E (`frontend/run-e2e.sh`).

Status: refinamento entregue pela Mai. Achado central: a premissa "exit 0" não
reproduz na `main` (`200e9f0`) — o Cypress 16.2.0 já sai com 1 nesse caso e a
KAN-57 repassa. O item vira **defesa em profundidade**: o harness aplica o
portão nas próprias mãos, sem depender do humor da versão do Cypress. Escopo
desta etapa: **correção pontual no harness**. Autor da etapa: Framework
Architect. Interface: `frontend/` (SPA + `run-e2e.sh`). Backend PHP **não** é
tocado.

Fonte única do aceite: `frontend/tests/run-e2e-nospec.{feature,test.mjs}` +
relatório `KAN-59-refinamento.md` (já escritos; untracked na árvore).

## 1. Causa e contrato (congelado)

Hoje o `run-e2e.sh` repassa o status do Cypress (KAN-57), mas o portão
"nenhum spec executado" depende da versão do Cypress. O contrato:

- Capturar a saída do `cypress run` **sem** engolir o diagnóstico (o motivo
  continua visível para o caller).
- status 0 **e** a mensagem casar com "no spec files found" → sair 1.
- verde real → 0; vermelho/timeout → ≠ 0, como já está. **Sem `exit 1` fixo.**

## 2. Alteração de produção (única)

`frontend/run-e2e.sh`. Três toques:

1. Junto ao `LOG="$(mktemp)"` (linha ~16), adicionar
   `CYPRESS_LOG="$(mktemp)"`.
2. No `cleanup()`, incluir o novo arquivo na remoção:
   `rm -f "$LOG" "$BACKUP" "$CYPRESS_LOG"`.
3. No fim, teer a saída do Cypress e aplicar o portão:

```bash
CYPRESS_BASE_URL="$BASE" timeout -k 10 "$TIMEOUT" ./node_modules/.bin/cypress run "$@" 2>&1 | tee "$CYPRESS_LOG" &
CYPRESS_PID=$!
wait "$CYPRESS_PID"
STATUS=$?
CYPRESS_PID=""
if [ "$STATUS" -eq 0 ] && grep -Eqi 'no spec files( were)? found' "$CYPRESS_LOG"; then
  STATUS=1
fi
exit "$STATUS"
```

## 3. Por que `tee` (e não redirect + `cat`)

- O `wait` em `cmd 2>&1 | tee arquivo &` espera o **pipeline inteiro**: com
  `set -o pipefail` (já presente no script), o status do `wait` é o do lado
  esquerdo (verificado: vermelho → 1, timeout → 124, verde → 0), e o `tee` já
  fluiu e fechou o arquivo quando o `wait` retorna — o `grep` do portão nunca
  lê o arquivo pela metade.
- A saída continua chegando ao caller em tempo real (o `tee` espelha em
  stdout), então o motivo "no spec files" fica visível — exigência do teste.

## 4. Artefatos a commitar (já na árvore, untracked)

| arquivo | papel |
|---|---|
| `frontend/run-e2e.sh` | o portão acima |
| `frontend/tests/run-e2e-nospec.feature` | 3 cenários BDD (pt-BR) |
| `frontend/tests/run-e2e-nospec.test.mjs` | integração `node:test`, 3 testes |

Um único PR com a correção + estes 3 artefatos + este guia. **Não** usar
`git add .` — a árvore tem untracked de outros itens (dark-theme,
source-directory, kan-29, runner-hygiene etc.). Adicionar **apenas** os
caminhos da tabela.

## 5. Barra de qualidade

1. `node --test tests/run-e2e-nospec.test.mjs` → **3/3 verde**.
2. `npx vitest run` → sem regressão (os `*.test.mjs` seguem fora do vitest via
   `test.exclude`).
3. `npm run test:e2e` (suíte padrão de `cypress/e2e/`) → verde, sem erro de
   runtime/console.
4. Atualizar `docs/STATE.md` no PR (convenção GITFLOW).

## 6. Nota: o teste não distingue o portão do "exit 1 natural"

Na `main` atual o Cypress 16.2.0 já sai com 1 no caso "no spec files", então os
3 testes do contrato passam **antes** do portão (é defesa em profundidade, não
reprodução do sintoma). A prova de que o portão está amarrado é a leitura do
código (o `grep` + `STATUS=1`), não a reexecução do teste. Não forje vermelho:
o cenário 3 (verde → 0) impede qualquer `exit 1` fixo.

## 7. Arquivo quente: `run-e2e.sh`

`run-e2e.sh` foi tocado pela KAN-56 (topo, seed do db legado) e KAN-57 (fim,
status). Este portão mexe **só no fim** do script (mesma região da KAN-57, que
já está em `main`). Ao commitar, incluir **apenas** os hunks deste item;
rebasar sobre `origin/main`.
