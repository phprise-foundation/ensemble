# Guia KAN-154 — Capturas do `icon-standard` no Electron

> Origem: item 1 do KAN-154 (único escopo de coding restante após o PR #154).
> Contrato BDD: `frontend/tests/icon-standard-capture.feature` (3 cenários, Red).
> Evidências do QA: `var/kanban.local/artifacts/alexya-kan-114-e2e-full.log`.

## 1. Diagnóstico fechado

### 1.1 O travamento é do caminho de captura do Electron headless — não do app

- `cy.screenshot` existe **só** em `cypress/e2e/icon-standard.cy.js` (grep na suíte):
  este spec é o único exercício de captura do projeto.
- No log do QA as 6 falhas são idênticas
  (`CypressError: cy.screenshot() timed out waiting 30000ms`, ~30s cada; spec em
  3m12s) — **inclusive a primeira captura** (`topbar-padrao`), que roda numa
  página recém-carregada, antes de qualquer diálogo (KAN-127), painel ou avatar.
  Não é o avatar do KAN-108 nem os diálogos do KAN-127.
- Chrome passa 6/6: no Chrome a captura vai por CDP (`Page.captureScreenshot`);
  no Electron vai pelo caminho nativo `capturePage` (janela offscreen), que não
  resolve neste host. O commit do KAN-125 (`444d007`) já confessava: *"explícito
  cy.screenshot travava hosts headless e derrubava toda a rodada completa"*.
- Não há `backdrop-filter` em `frontend/src` (grep) — nada no CSS do app explica
  o hang.
- Os 30000ms são o `responseTimeout` default do Cypress: o timeout do
  `.screenshot()` deriva dele (docs: `cy.screenshot` → option `timeout`).

### 1.2 O portão `visualAudit` está morto no Cypress 16 — capturas nunca executam

- `cypress.config.js` publica o flag em `env: { visualAudit: ... }`. No
  Cypress 16 `Cypress.env()` foi removido: a chave `env` alimenta `cy.env()`, e
  **não** chega a `Cypress.expose()` (Migration Guide + doc de
  `Cypress.expose()`).
- Probe executado no Electron 16.2.0 (spec efêmero com `cy.writeFile`):
  - config atual + `E2E_VISUAL_AUDIT=1` → `Cypress.expose()` → `{}` (portão
    sempre fechado);
  - chave trocada para `expose:` sob `e2e:` + `E2E_VISUAL_AUDIT=1` →
    `Cypress.expose('visualAudit')` → `'1'` (portão abre).
- Consequência: hoje `capture()` é no-op em **toda** rodada. O spec passa "de
  graça" na suíte padrão e o cenário 1 do contrato ("a captura correspondente
  existe no diretório de screenshots") é insatisfazível. O travamento do log só
  aconteceu porque a versão de então ainda lia o valor.

## 2. Caminho de implementação

### Etapa A — portão funcional no Cypress 16

1. `frontend/cypress.config.js`:
   ```js
   expose: {
     visualAudit: process.env.E2E_VISUAL_AUDIT || '0',
   },
   ```
   (sob `e2e:`; provado pelo probe. Nada mais muda no arquivo nesta etapa.)
2. `frontend/cypress/e2e/icon-standard.cy.js`: leitura por chave —
   `Cypress.expose('visualAudit') !== '1'`.
3. `frontend/tests/icon-standard-capture.test.mjs` (novo, `node:test`, padrão
   dos contratos existentes — guardas estáticos na fonte):
   - config declara `expose` com `E2E_VISUAL_AUDIT` (e não `env`);
   - spec lê `Cypress.expose('visualAudit')`;
   - helper `capture` passa `timeout` explícito e não usa `cy.skip`/`this.skip`;
   - existe a guarda de não-repetição da etapa C.
4. Comitar `docs/KAN-154-guide.md` junto da etapa A.

### Etapa B — capturas no runner padrão: bisect e conserto

Baseline (portão aberto, etapa A pronta):

```bash
E2E_VISUAL_AUDIT=1 E2E_PORT=5174 bash run-e2e.sh --spec cypress/e2e/icon-standard.cy.js
```

Esperado hoje: 6×20–30s de timeout. Documente a rodada. Árvore de decisão, na
ordem — pare no primeiro verde e registre a evidência no comentário do item:

1. **Modo de captura.** Probe efêmero (`cypress/e2e/screenshot-probe.cy.js`,
   apagar antes do commit): `cy.visit('/')` + três variantes — default
   (fullPage), `{ capture: 'viewport' }` e captura de elemento (`.topbar`). Se
   só o default travar, fixe `capture: 'viewport'` no helper `capture` e
   registre a decisão (auditoria em viewport).
2. **Switches do Electron** via `setupNodeEvents` no `cypress.config.js`:
   ```js
   on('before:browser:launch', (browser, launchOptions) => {
     if (browser.family === 'electron') {
       launchOptions.preferences.appendSwitch('disable-gpu')
     }
     return launchOptions
   })
   ```
   Bisect, um por vez: `disable-gpu` → `disable-gpu-compositing` →
   `use-angle=swiftshader`.
3. **`--headed` na auditoria.** `cypress run --headed` sobre o Xvfb do harness
   troca janela visível por offscreen. Se for o único caminho funcional, o
   harness ganha `E2E_AUDIT_HEADED=1` (só para a rodada de auditoria; o padrão
   sem auditoria segue como está).
4. **Último recurso — runner específico para auditoria.** Se o Electron não
   capturar de forma nenhuma neste host, a rodada `E2E_VISUAL_AUDIT=1` roda
   `--browser chrome`; a suíte padrão (sem auditoria) segue no Electron. O
   refinamento da Mai sanciona "captura condicional/runner específico — jamais
   skip silencioso". Registre a limitação no guia e no comentário do item.

### Etapa C — guarda do cenário 3 (falha determinística, sem repetição)

Em `icon-standard.cy.js` (helper + hooks do describe):

```js
let capturaEmAndamento = null
let capturasQuebradas = false
const TETO_CAPTURA_MS = 20000

const capture = (name) => {
  if (Cypress.expose('visualAudit') !== '1') return
  if (capturasQuebradas) {
    throw new Error(
      `capturas indisponíveis nesta rodada (primeira falha: "${capturaEmAndamento}")`,
    )
  }
  capturaEmAndamento = name
  cy.screenshot(name, { timeout: TETO_CAPTURA_MS })
}

Cypress.on('fail', (erro) => {
  if (String(erro).includes('cy.screenshot()')) {
    capturasQuebradas = true
    throw new Error(
      `cy.screenshot da captura "${capturaEmAndamento}" estourou o teto de ${TETO_CAPTURA_MS}ms`,
    )
  }
})
```

- O `Cypress.on('fail')` só observa e relabela: o teste que travou **continua
  falhando** (não há swallow), e o nome da captura entra no erro.
- Teto de 20s abaixo dos 30s nativos: um travamento custa 20s, não 189s; as
  capturas seguintes falham em ~0s com o rótulo determinístico.
- Prova do caminho de falha (probe opcional, apagar depois): spec temporário que
  chama `cy.screenshot('probe', { timeout: 1 })` — com o portão aberto o teto de
  1ms estoura de forma determinística; a saída deve mostrar o erro rotulado e os
  testes seguintes falhando rápido, sem repetir o hang.

## 3. Aceite

- `frontend/tests/icon-standard-capture.feature` fecha os 3 cenários: portão
  funcional (A), capturas verdes no runner definido pela bisect (B) e guarda
  diagnóstica (C).
- Critério da QA (Mai): `npm run test:e2e` completo verde no runner padrão
  (Electron), `node --test` serial verde e `npm run build` íntegro. Rodada de
  auditoria (`E2E_VISUAL_AUDIT=1`, só o spec) verde no runner definido, com os
  arquivos de captura presentes no diretório de screenshots.
- Sem `skip` silencioso; `screenshotOnRunFailure: false` global intocado;
  `run-e2e.sh` intocado (exceto o env opcional do cenário headed, se necessário).

## 4. Não-objetivos

- Itens 2–4 do KAN-154: fechados pelo PR #154.
- Item 5 (disputa de `.data/db.json`): verificação de QA na rodada serial
  (`node --test frontend/tests/*.test.mjs` sem flags); se reaparecer, item
  próprio.
