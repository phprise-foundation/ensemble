# Guia de Implementação — KAN-86: drawer em tela cheia abaixo de 768px

Status: RFC aprovada (KAN-86). Etapa: Framework Architect.
Base: KAN-73/KAN-86 (RFC), KAN-30 (drawer, scrim, Escape, focus trap,
fecho na navegação e retorno de foco), KAN-12 (breakpoint de 1024px),
KAN-9/KAN-10 (sidebar e colapso).
Estudos: `var/kanban.local/artifacts/KAN-86-analise-viabilidade.md` e
`var/kanban.local/artifacts/KAN-86-adr-integracao.md`.

Sub-tasks:

| onda | sub-task | responsável | depende de |
|---|---|---|---|
| 1 | Produção: token, tela cheia, botão de fechar e scroll lock | Coder | — |
| 2 | BDD + integração + E2E da faixa completa | Kaio | onda 1 |

A onda 2 pode ser escrita contra este contrato enquanto a onda 1 roda, mas só
fecha verde depois que os seletores e o comportamento estiverem na árvore.

## Objetivo

Abaixo de 768px o drawer passa a ocupar a largura útil da tela, com header
visível, navegação rolável, botão de fechar e body travado. Entre 768px e
1024px o drawer parcial de 200px e o scrim atuais permanecem. Acima de 1024px
nada muda. A entrega é só de frontend: sem endpoint, store, schema, migração ou
`localStorage`.

## Decisões que governam a implementação

- **Faixas exatas.** `< 768px`: tela cheia. `768px–1024px`: drawer parcial de
  200px. `>= 1025px`: sidebar desktop. O valor 1024 não muda; 1024px continua
  mobile, conforme KAN-12 e o ADR.
- **O corte é estrito.** `@media (max-width: $mobile-breakpoint - 1)` (767px
  compilado) e o mesmo corte no JS (`offset = -1`). Um `max-width` direto em
  768px colocaria 768 na tela cheia e quebraria o critério.
- **Botão de fechar e alvos de 44px só abaixo de 768px.** Em 768–1024px o
  scrim continua sendo o fecho adicional; não criar botão nessa faixa.
- **Scrim em tela cheia não é clicável.** Com o painel em `inset: 0`, o scrim
  fica atrás dele e não sobra área para clique. A prova de scrim é feita em
  768–1024px; em `< 768px` os fechos são navegação, Escape e botão.
- **Uma única fonte para o corte.** `$mobile-breakpoint: 768px` no SCSS e
  `--breakpoint-mobile: 768px` no `:root`, no padrão de
  `--breakpoint-sidebar`. O JS lê o token computado; não repetir `768` em
  `matchMedia`.
- **Largura útil, não `100vw`.** `inset: 0` + `width: 100%`; nada de `100vw` ou
  `100vh`, que podem gerar overflow horizontal/vertical.
- **Scroll lock por classe, não por estilo inline.** `body.drawer-locked` com
  `overflow: hidden`; a classe entra quando o drawer está aberto e a viewport é
  estreita, e sai no fecho, na navegação, ao cruzar para a faixa parcial e no
  unmount.
- **Preservar o que já funciona.** `inert` do conteúdo, `role="dialog"`,
  `aria-modal`, focus trap, Escape, retorno de foco ao `menu-toggle` e fecho
  pelo `router.afterEach` continuam intocados.
- **Sem comentários no código.** O porquê fica neste guia e nas mensagens de
  commit.

## Contrato de nomes

| Item | Valor acordado |
|---|---|
| Token SCSS | `$mobile-breakpoint: 768px` |
| Token CSS | `--breakpoint-mobile: #{$mobile-breakpoint}` no `:root` |
| Media query | `@media (max-width: $mobile-breakpoint - 1)` dentro do bloco de 1024px |
| Estado no `App.vue` | `isNarrow`, `sidebarQuery`, `narrowQuery` |
| Helper de mídia | `mediaQueryFor(property, offset = 0)`; `--breakpoint-mobile` usa `offset = -1` |
| Prop do sidebar | `narrow: { type: Boolean, default: false }` |
| Botão de fechar | `button.icon-btn.sidebar-close`, `type="button"`, `aria-label="Fechar menu"`, `title="Fechar menu"`, `AppIcon name="close" :size="20"` |
| Visibilidade do botão | `v-if="narrow && isMobile"` |
| Classe do body | `drawer-locked` |
| Largura em tela cheia | `.sidebar { inset: 0; width: 100%; flex: none; }` |
| Alvo de toque | `min-height: 44px` em `.nav-entry` e `.leaf`; `44×44` em `.sidebar .icon-btn` |

## Exemplo de referência — `App.vue`

O helper substitui o `sidebarMediaQuery()` atual e passa a servir os dois
tokens. O trecho abaixo é o contrato; ajuste apenas o necessário para conviver
com o restante do componente.

```js
function mediaQueryFor(property, offset = 0) {
  if (typeof document === 'undefined') {
    return null
  }
  const value = getComputedStyle(document.documentElement).getPropertyValue(property).trim()
  const declared = Number.parseFloat(value)
  const limit = Number.isFinite(declared) ? declared + offset : 0
  return window.matchMedia(`(max-width: ${limit}px)`)
}

const isNarrow = ref(false)
let sidebarQuery = null
let narrowQuery = null

function updateIsNarrow(event) {
  isNarrow.value = event.matches
}

onMounted(() => {
  boardStore.load()
  session.load()
  window.addEventListener('keydown', handleKeyboardShortcut)
  sidebarQuery = mediaQueryFor('--breakpoint-sidebar')
  narrowQuery = mediaQueryFor('--breakpoint-mobile', -1)
  if (sidebarQuery) {
    isMobile.value = sidebarQuery.matches
    sidebarQuery.addEventListener('change', updateIsMobile)
  }
  if (narrowQuery) {
    isNarrow.value = narrowQuery.matches
    narrowQuery.addEventListener('change', updateIsNarrow)
  }
})

watch([sidebarOpen, isNarrow], ([isOpen, narrow]) => {
  document.body.classList.toggle('drawer-locked', isOpen && narrow)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyboardShortcut)
  sidebarQuery?.removeEventListener('change', updateIsMobile)
  narrowQuery?.removeEventListener('change', updateIsNarrow)
  document.body.classList.remove('drawer-locked')
})
```

O `menu-toggle` continua sendo o único gatilho; o `watch` de retorno de foco
existente não muda. A prop nova entra na chamada:

```html
<AppSidebar
  :open="sidebarOpen"
  :is-mobile="isMobile"
  :narrow="isNarrow"
  :collapsed="sidebarCollapsed"
  @close="sidebarOpen = false"
/>
```

## Exemplo de referência — `AppSidebar.vue`

Adicionar `narrow` às props e o botão no header, antes do `<nav>`:

```html
<div class="brand">
  <span class="brand-icon"><AppIcon name="smartboard" size="18" /></span>
  <span>Multi Agent</span>
  <button
    v-if="narrow && isMobile"
    class="icon-btn sidebar-close"
    type="button"
    title="Fechar menu"
    aria-label="Fechar menu"
    @click="emit('close')"
  >
    <AppIcon name="close" size="20" />
  </button>
</div>
```

O botão entra no início da ordem de foco; o `focusFirst()` existente passa a
focar o fechar ao abrir. O trap, o Escape e o `aria-modal` continuam iguais.

## Exemplo de referência — `main.scss`

```scss
$sidebar-breakpoint: 1024px;
$mobile-breakpoint: 768px;

:root {
  --breakpoint-sidebar: #{$sidebar-breakpoint};
  --breakpoint-mobile: #{$mobile-breakpoint};
}

body.drawer-locked {
  overflow: hidden;
}

@media (max-width: $sidebar-breakpoint) {
  .sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    transform: translateX(-100%);
    box-shadow: var(--shadow);
  }

  .sidebar.open {
    transform: translateX(0);
  }

  .menu-toggle {
    display: inline-flex;
  }

  .scrim {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 30;
  }

  @media (max-width: $mobile-breakpoint - 1) {
    .sidebar {
      inset: 0;
      width: 100%;
      flex: none;
    }

    .sidebar .nav-entry,
    .sidebar .leaf {
      min-height: 44px;
    }

    .sidebar .icon-btn {
      width: 44px;
      height: 44px;
    }

    .sidebar-close {
      margin-left: auto;
    }
  }
}
```

A regra de 44px em `.sidebar .icon-btn` também alcança as ações de edição;
isso é intencional para o toque. O visual do colapsado, o topbar, o scrim e as
demais regras de 1024px não mudam.

## Mapa de alterações

```
frontend/src/styles/main.scss                 → token, tela cheia, 44px, body travado
frontend/src/App.vue                          → isNarrow, matchMedia, scroll lock
frontend/src/components/AppSidebar.vue        → prop narrow e botão de fechar
frontend/cypress/e2e/mobile-drawer.cy.js      → ajuste de viewport do cenário de scrim
frontend/tests/mobile-drawer-fullscreen.feature   → BDD (QA)
frontend/tests/mobile-drawer-fullscreen.test.mjs  → contrato estático (QA)
frontend/tests/mobile-drawer-fullscreen.spec.js   → integração Vue (QA)
frontend/cypress/e2e/mobile-drawer-fullscreen.cy.js → E2E 320/767/768/1023/1024/1025 (QA)
```

O ajuste no spec existente é uma exceção de compatibilidade e pertence à
produção (KAN-95): ele roda em 760px e clica no centro do `.scrim`; com a tela
cheia, o scrim fica coberto. Mude **apenas** o cenário do scrim para 1000px
(ou 1023px) e mantenha os cenários de Escape e navegação em 760px, que agora
provam a regressão em tela cheia. A prova nova de tela cheia continua na
KAN-96.

## Casos de borda

- **768px exato:** drawer parcial de 200px, sem botão de fechar, body sem lock.
- **1024px exato:** drawer parcial de 200px, com scrim; 1025px é desktop.
- **320/375/767px:** tela cheia, `scrollWidth <= clientWidth`, sem overflow
  horizontal; rótulos longos continuam com ellipsis.
- **Telefone na horizontal com largura >= 768px:** permanece parcial; o corte é
  por largura, como registrado na RFC.
- **Abrir/fechar e navegar:** a classe `drawer-locked` sai no fecho, no
  `router.afterEach`, no unmount e ao cruzar para a faixa parcial.
- **Redimensionar com o drawer aberto:** ao entrar em 768–1024px o lock sai e o
  botão de fechar deixa de existir; o scrim volta a fechar.
- **Foco:** abre no botão de fechar; Tab/Shift+Tab ciclam dentro do aside;
  Escape e o fecho devolvem o foco ao `menu-toggle`.
- **Editores:** continuam teleportados para `body`; abrir editor em mobile
  emite `close` como hoje.
- **Desktop:** sidebar em fluxo, 200px, sem `.scrim`, sem `menu-toggle` e sem
  `inert`.

## Armadilhas de compatibilidade

- `frontend/tests/mobile-drawer.test.mjs` e
  `frontend/tests/sidebar-collapse.test.mjs` continuam válidos; não remover a
  regra `@media (max-width: $sidebar-breakpoint)` nem o valor 1024.
- `frontend/cypress/e2e/mobile-drawer.cy.js` precisa do ajuste de viewport do
  cenário de scrim descrito acima; os demais cenários ficam como estão.
- `frontend/tests/App.spec.js` injeta `matchMedia` mockado; o novo estado não
  pode quebrar os testes existentes. Testes novos devem simular as duas queries
  de forma independente.
- Não usar `100vw`/`100vh`; não colocar `overflow: hidden` no `html`; não mexer
  no `z-index` do topbar nem no visual do colapsado.
- O `--breakpoint-mobile` precisa estar no `:root` antes do primeiro
  `getComputedStyle`; ele já é servido pelo CSS global no boot.

## Barra de qualidade

Produção:

- `cd frontend && npm run build`.
- `npm run test:unit`.
- `node --test tests/mobile-drawer.test.mjs`.
- `npm run test:e2e` (o ajuste do spec de scrim precisa passar no harness).
- Manual em 767×900 e 1000×900: largura, botão, scroll do body, quatro fechos,
  foco de volta e ausência de erro de console.

Testes de aceitação:

- BDD em português cobrindo as faixas 767/768/1023/1024/1025, os fechos, o lock
  do body, os alvos de 44px e a ausência de overflow.
- Integração Vue: em `< 768px` o body trava e o botão emite `close`; em
  768–1024px não há botão nem lock; o unmount remove a classe.
- E2E em navegador real: geometria (`width`, `left`, `top`, `height`), 200px em
  768/1023/1024, desktop em 1025, `scrollWidth <= clientWidth` em 320/375/767,
  botão de fechar 44×44, linhas >= 44px, scrim fechando em 1000/1023, foco de
  volta e ausência de erros de runtime/console.
- `npm run test:e2e` verde com o harness KAN-57/KAN-60; usar `E2E_PORT`
  exclusiva se houver worktrees concorrentes.

## Referências consultadas

- Vue 3.5 — `watch()` com múltiplas fontes e `onUnmounted`:
  <https://vuejs.org/api/reactivity-core.html#watch>.
- MDN — `@media/width` e consultas por largura:
  <https://developer.mozilla.org/en-US/docs/Web/CSS/@media/width>.
- Projeto — `docs/KAN-10-guide.md` (breakpoint único e colapso suspenso no
  mobile) e `docs/KAN-83-guide.md` (precedente de guia, ondas e barra de
  qualidade).
