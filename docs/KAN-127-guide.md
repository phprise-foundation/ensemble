# Guia de Implementação — KAN-127: diálogos acessíveis — serviço central de confirm/alert e toasts

Status: RFC aprovada (KAN-127). Autora da etapa: Framework Architect (Lucy).
Este guia é a fonte única do contrato de diálogos/notificações da SPA; produção e
testes mudam juntos. Base: RFC KAN-127, viabilidade do Jarvis (comentário #1),
ADR de integração do David (`var/kanban.local/artifacts/david-kan-127.md`),
`docs/PHILOSOPHY.md`, `docs/GUIDE.md`, `docs/ARCHITECTURE.md` e o guia do perfil
(`docs/KAN-126-guide.md` — consumidor futuro do contrato).

## 1. Objetivo e limites

Serviço central promise-based de diálogos e toasts, com um `DialogHost` global
acessível, removendo todos os `window.confirm` nativos do código e provando a
migração com testes. Fronteira: a KAN-134 (Perfil Fase 2) consome
`dialog.confirm`/toasts e **não** os altera — o contrato deste guia é vinculante
para lá. Sem dependência de biblioteca de UI (componente próprio, decisão do
cliente). Strings da UI em inglês, sem i18n (decisão do cliente de 2026-10-19).
Fora de escopo: `dialog.prompt` (assinatura prevista na RFC, sem UI/uso/aceite
nesta rodada), diálogos nativos do navegador, i18n.

## 2. Estado de partida

- Quatro diálogos nativos a migrar: `frontend/src/components/CrudPage.vue:64`,
  `frontend/src/views/AgentsPage.vue:66`, `frontend/src/views/IssueTypesPage.vue:78`
  e `frontend/src/components/AppSidebar.vue:310` (todos exclusões).
- Tokens: `--danger` existe nos dois temas e `.button.danger` já estiliza a ação
  destrutiva; não há `--success`/`--warning`. Ícones existentes: `check`,
  `warning` (TriangleAlert), `close`, `delete`. Falta `info` (entrada aditiva no
  dicionário de `AppIcon.vue`, padrão KAN-63: uma linha, nenhum consumidor
  alterado).
- `App.vue` monta o shell (sidebar, `.content` com topbar + `.main` + console,
  `AppCopilot`); os hosts entram como filhos finais do shell, teletransportados
  para `body` (padrão dos editores da sidebar).

## 3. Contrato do serviço de diálogo — `frontend/src/store/dialog.js` (novo)

Singleton reativo no padrão `store/session.js`: `state` com
`{ open, title, message, confirmLabel, cancelLabel, variant, scrimCloses, resolve }`.

- `dialog.confirm(options) => Promise<boolean>` — `options = { title, message?,
  confirmLabel?, cancelLabel?, danger?, scrimCloses? }`. Resolve `true` no botão
  de confirmação; `false` em cancelar, Escape, scrim (quando habilitado) ou
  recusa (abaixo). `danger: true` ⇒ variante `danger` + `role="alertdialog"`.
- `dialog.alert(options) => Promise<void>` — `{ title, message?, confirmLabel?,
  variant? }`; um único botão (default `OK`); Escape/scrim/confirmar resolvem.
  `scrimCloses` default `true` aqui.
- Defaults: `variant: 'info'` (info usa `--accent`; success/warning/danger usam
  seus tokens), `confirmLabel: 'Confirm'`, `cancelLabel: 'Cancel'`,
  `scrimCloses: false` para confirm (perigoso perder a resposta por um clique
  fora; chaves sobrescrevem).
- **Uma caixa por vez:** chamada enquanto `open` resolve `false`
  imediatamente (comportamento determinístico e testável; segunda exclusão
  rápida vira no-op seguro, sem fila nem rejeição).
- `dialog.prompt` **não** será implementado nesta rodada; assinatura futura
  prevista na RFC (`{ label, placeholder, maxlength, validate }`) fica
  documentada aqui apenas como reserva de espaço na API.

## 4. Contrato dos toasts — `frontend/src/store/toast.js` + `ToastHost.vue` (novos)

- API: `toast.success(text)`, `toast.error(text)`, `toast.info(text)`,
  `toast.warning(text)` — empilham `{ id, kind, text }` (id por contador).
- Não bloqueantes; máx. 3 visíveis (o mais antigo sai); auto-dismiss 4s para
  success/info e 6s para error/warning; botão de fechar com `aria-label="Close"`.
- Container `.toast-host` com `aria-live="polite"`; toasts de `error` ganham
  `role="alert"` (anúncio assertivo). Texto sempre interpolado, sem `v-html`;
  zero rede, zero store alheio (padrão KAN-62).

## 5. Acessibilidade do modal — `DialogHost.vue`

- `<Teleport to="body">` com `.dialog-overlay` (scrim fixo) e `.dialog-card`.
- ARIA: `role="dialog"` (ou `alertdialog` quando `danger`), `aria-modal="true"`,
  `aria-labelledby="dialog-title"`, `aria-describedby="dialog-message"`.
- Foco inicial: **botão Cancelar** quando a caixa tem cancelar (todas as
  exclusões são `danger`); caso contrário o botão de confirmação. Via
  `nextTick` + `.focus()`.
- Teclado: `Escape` sempre cancela/fecha; `Enter` confirma apenas quando o foco
  não está sobre um botão (evita duplo disparo com o foco inicial no Cancelar;
  sobre um botão o Enter nativo já age). Tab/Shift+Tab ciclam dentro do card.
- Enquanto aberto: conteúdo principal (`#app`) recebe `inert` e o scroll do
  `document.body` trava (`overflow: hidden`), ambos restaurados ao fechar.
- Retorno de foco: guardar `document.activeElement` ao abrir e restaurá-lo ao
  fechar (após `nextTick`) — o gatilho da exclusão volta a ser o ponto de
  partida.
- Scrim: clique nele só fecha quando `scrimCloses` (resolve `false`).

## 6. Tokens e estilo — `frontend/src/styles/main.scss`

- Tokens novos nos dois temas (dark alinhado à paleta Dark+): dark
  `--success: #3fb950` e `--warning: #d29922`; light `--success: #1a7f37` e
  `--warning: #9a6700`. Variante `info` reusa `--accent` (sem token novo).
- `.dialog-overlay` (scrim), `.dialog-card` (máx. 420px, `--surface-2`, borda
  `--border`, radius herdado), `.dialog-title` com ícone da variante à esquerda
  (`info`/`check`/`warning`, cor por token), `.dialog-message`, `.dialog-actions`
  reusando `.button` + `.button.danger`.
- `.toast-host` fixo no canto inferior direito; `.toast` com `--surface-2`, borda
  lateral pela cor da variante, largura máx. 320px; transição curta de entrada
  (respeitando `prefers-reduced-motion`).

## 7. Migração — fim dos diálogos nativos

- Nos quatro pontos: `const ok = await dialog.confirm({ title: 'Delete?',
  message: `"${label}" will be removed.`, confirmLabel: 'Delete', danger: true })`;
  só prosseguir com a exclusão se `ok`. Nada de digitar o nome (decisão da RFC).
- Toasts nos fluxos (regra da RFC §6: inline = validação de campo; toast =
  resultado global): exclusão concluída → `toast.success` com o nome; falha de
  exclusão → `toast.error(problem.message)`; save bem-sucedido → `toast.success`;
  erro **sem campo** (rede/422 genérico) → `toast.error`; erro de campo continua
  inline (`.error` do modal hoje) — não duplicar.
- Nenhum `window.confirm`/`alert`/`prompt` pode restar em `src/**` (a guarda
  varre tudo).

## 8. Contrato de testes

- **8.1 BDD** `frontend/tests/dialogs.feature` (pt-BR, fonte única do aceite):
  foco inicial no Cancelar de caixas danger; Enter confirma; Escape cancela;
  scrim ignora em confirm e fecha em alert; retorno de foco ao gatilho;
  `role`/`aria-modal`/`alertdialog`; scroll travado; toasts empilham, expiram e
  anunciam erro; migração dos quatro fluxos (cancelar nada faz, confirmar
  exclui + toast); guarda estática verde.
- **8.2 Guarda** `frontend/tests/native-dialog-guard.test.mjs` (`node:test`):
  varre recursivamente `frontend/src/**` e reprova `window.confirm`/
  `window.alert`/`window.prompt` e chamadas diretas `\b(confirm|alert|prompt)\s*\(`
  **não precedidas por `.`** (lookbehind `(?<!\.)` — `dialog.alert(` continua
  legal). Falha aponta arquivo e linha.
- **8.3 vitest** `tests/dialog-host.spec.js` + `tests/toast.spec.js`: montar o
  host com o store real; confirm resolve `true`/`false` por clique; foco
  inicial; Escape; retorno de foco; role/aria-modal; uma caixa por vez; toast
  empilha, limita a 3 e auto-dismiss com fake timers.
- **8.4 E2E** `cypress/e2e/dialogs.cy.js`: navegador real nos fluxos de
  exclusão (CrudPage/AgentsPage/IssueTypesPage/AppSidebar), foco/Escape/retorno
  medidos no DOM, toast visível após save/exclusão.
- Barra de qualidade (padrão do board): `node --test tests/*.test.mjs`,
  `npm run test:unit`, `npm run build` e `npm run test:e2e` em porta livre,
  sem erro de runtime/console.

## 9. Fases e subtarefas

| # | Sub-tarefa | Agente | Dependência | Entrega |
|---|---|---|---|---|
| 1 | Fundação: `store/dialog.js`, `DialogHost.vue`, `store/toast.js`, `ToastHost.vue`, hosts em `App.vue`, tokens/ícone/estilo | Coder | — | PR `feat(KAN-127): dialog host global e toasts acessíveis` |
| 2 | Migração dos 4 fluxos nativos + toasts de sucesso/erro | Coder | 1 | PR `feat(KAN-127): migrar confirmacoes para o servico de dialogos` |
| 3 | BDD, guarda estática e vitest do contrato | Kaio | 2 | PR `test(KAN-127): ...` |
| 4 | E2E Cypress dos fluxos e da acessibilidade | Mai | 2 | PR `test(KAN-127): ...` |

## 10. Dívidas conscientes

- `dialog.prompt` fica sem UI/aceite até existir fluxo real (ex.: comentário com
  limite de 1023 chars).
- Foco inicial configurável além da regra danger/cancelar e animações mais
  ricas ficam para demanda.
