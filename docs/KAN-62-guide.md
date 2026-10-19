# Guia de implementação — KAN-62: painel do copilot colapsável (grade direita)

Status: RFC aprovada (KAN-62) + ondas em voo. Escopo: **MVE de UI**.
Autor da etapa: Framework Architect.

## Objetivo

Espelhar a grade esquerda (KAN-10) no lado direito: um painel de copilot em
esqueleto, montado como irmão do `.content`, alternado por um símbolo de grade no
menu superior. Zero rede, zero store, zero persistência nesta rodada.

## Decisões que já governam a implementação

- **Raiz do painel é irmã de `.content`**, dentro de `.app` (não tocar em `.main`
  nem no painel de issue).
- **Breakpoint único**: `$sidebar-breakpoint` (1024px). Desktop = `!isMobile`;
  exatamente 1024px segue mobile (o símbolo e o painel não existem no DOM).
- **Token próprio** `--copilot-width: min(25vw, 400px)`; nunca reutilizar
  `--panel-width`.
- **Nasce expandido** (`copilotOpen = ref(true)`), sem memória entre recargas.
- **Atalho** `Ctrl+Alt+B`/`Cmd+Alt+B` desktop-only, checado **antes** do ramo do
  `Ctrl+B` com guarda `event.altKey`, para um atalho não disparar dois toggles.

## Contrato de nomes (fonte única — guia e testes mudam juntos)

| Item | Valor acordado |
|---|---|
| Componente | `frontend/src/components/AppCopilot.vue` |
| Raiz do painel | `<section id="app-copilot" class="copilot-panel">` |
| Header | `.copilot-header` com título `Copilot` |
| Corpo | `.copilot-body` com placeholder estático (`Coming soon`) |
| Botão de topo | `.icon-btn.copilot-toggle` |
| Rótulo do botão | `aria-label="Toggle copilot"` |
| Reflexo de estado | `:aria-expanded="copilotOpen"` |
| Alvo do botão | `aria-controls="app-copilot"` |
| Estado | `copilotOpen = ref(true)` em `App.vue` |
| Montagem | `<AppCopilot v-if="!isMobile && copilotOpen" />` (irmão de `.content`) |
| Token de largura | `--copilot-width: min(25vw, 400px)` no `:root` |
| Chave do ícone | `copilot: ['M17 3h4v18h-4z', 'M3 3h11v18H3z']` (KAN-69) |
| Atalho | `Ctrl+Alt+B` (Linux/Windows) e `Cmd+Alt+B` (macOS) |

## Mapa de alterações

```
frontend/src/components/AppIcon.vue         → entrada copilot (KAN-69)
frontend/src/components/AppCopilot.vue      → painel esqueleto (KAN-70)
frontend/src/App.vue                        → estado + botão + atalho + montagem
frontend/src/styles/main.scss               → --copilot-width + .copilot-*
frontend/tests/copilot.feature              → cenários BDD (KAN-71)
frontend/tests/copilot.test.mjs             → guardas estáticos (KAN-71)
frontend/cypress/e2e/copilot.cy.js          → prova no navegador (KAN-71)
```

## Mapa de ondas

| Onda | Item | Conteúdo |
|---|---|---|
| 1 | KAN-69 | chave `copilot` em `AppIcon.vue` |
| 2 | KAN-70 | `AppCopilot.vue`, toggle, atalho e CSS (depois da KAN-65) |
| 3 | KAN-71 | BDD, guardas e E2E (depois de 69 e 70) |

A hereditariedade no `frontend/` (App.vue, main.scss, AppIcon.vue) é compartilhada
com a grade inferior (KAN-18); rebase antes de puxar a onda.

## Barra de qualidade

- `node --test tests/copilot.test.mjs` verde (guardas estáticos, sem importar
  `.vue` — o `node:test` lê os arquivos-fonte como texto).
- `npm run test:unit` verde (contém os testes de componente).
- `npm run test:e2e` verde nos viewports 1280×900, 1920×900 e 1024×900.
- Sem erro de runtime/console.

## Observações para quem codar

- O painel de issue (`.main`), sidebar e console não mudam de comportamento; o
  único efeito colateral é a compressão de largura do board em 1024–1280px
  (custo aceito na decisão 6 do RFC).
- Colapsar é desmontar (`v-if`); nesta rodada não há estado de conversa a perder.
- Sem comentários no código; o "porquê" vive neste guia e na mensagem de commit.
