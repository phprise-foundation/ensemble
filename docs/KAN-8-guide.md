# Guia de Implementação KAN-8 — Tema escuro alinhado à paleta do VSCode Dark+

Endereço único deste item. Fontes de verdade complementares:
`frontend/tests/dark-theme.feature` (BDD em pt-BR) e
`frontend/tests/dark-theme.test.mjs` (contrato de integração `node:test`,
8 testes). O consenso selado está no comentário #16 do KAN-8 (Vivy) e o ADR do
botão no comentário #10 (David). O `GUIDE.md` não cobre tema/CSS, por isso este
guia é o exemplo local.

## 1. Estado da referência (leia antes de codar)

- O stylesheet é **`frontend/src/styles/main.scss`** (renomeado com a KAN-12,
  `$sidebar-breakpoint` centralizado). Os primeiros pareceres citam
  `main.css` — referência velha, ignore o nome, não a decisão.
- O tema é aplicado como `document.documentElement.dataset.theme`
  (`frontend/src/theme.js`); `:root` é o `<html>` e persiste em
  `localStorage` (`multi-agent-theme`).
- **O arnés `dark-theme.test.mjs:8` ainda lê `main.css`** e quebra com ENOENT
  (estado comprovado em 2026-10-19: `node --test tests/dark-theme.test.mjs` →
  1 fail antes de avaliar qualquer token). Os demais arnéses
  (`mobile-drawer`, `sidebar-collapse`, `profile-menu`) já leem `main.scss` —
  alinhe a uma única linha, sem tocar em mais nada do contrato.
- Os dois artefatos de teste estão **untracked**: o PR que implementa precisa
  commitá-los (padrão KAN-57/KAN-60: contrato da QA entra na mesma fatia).

## 2. Fidelidade selada (consenso do board)

- Valores **exatos** do Dark+ onde há correspondência direta; desvios de
  contraste (`--muted`, `--border`) pertencem à própria referência e **não**
  devem ser "corrigidos" aqui — a palavra final é a comparação lado a lado do
  usuário.
- Tema claro **congelado**: nenhum byte em `:root[data-theme='light']`.
- Tokens de estado `--danger`, `--warn`, `--ok` continuam declarados no escuro.
- Cores `#4c9aff` no `seed.js`, `StatusesPage.vue` e `AgentsPage.vue`
  são cores **de dados** (status/agentes, editáveis pelo usuário), não tema —
  fora de escopo.

## 3. Mudanças em `frontend/src/styles/main.scss`

### 3.1 Bloco `:root[data-theme='dark']` — substitua os valores

| Token | Antes | Novo (Dark+) |
|---|---|---|
| `--bg` | `#0b1220` | `#1e1e1e` |
| `--surface` | `#111a2b` | `#252526` |
| `--surface-2` | `#17223a` | `#2d2d2d` |
| `--surface-3` | `#1e2c49` | `#333333` |
| `--border` | `#24324f` | `#454545` |
| `--text` | `#e6edf7` | `#d4d4d4` |
| `--muted` | `#8ea0bd` | `#858585` |
| `--accent` | `#4c9aff` | `#3794ff` |
| `--accent-contrast` | `#04101f` | `#ffffff` |

### 3.2 Novos tokens — declare no MESMO bloco escuro

```scss
--selection: #264f78;      /* destaque de seleção (editor e itens) */
--button-bg: #0e639c;      /* botão primário — ADR do David, ~6.4:1 */
--syntax-string: #ce9178;
--syntax-keyword: #c586c0;
--syntax-function: #dcdcaa;
--syntax-type: #4ec9b0;
--syntax-comment: #6a9955;
--syntax-number: #b5cea8;
```

Tokens de syntax **nascem na reserva**: nenhum elemento os consome hoje; serão
consumidos quando a renderização de blocos de código existir. Não crie regras
órfãs para "usar" os tokens agora.

### 3.3 `::selection` escopada ao tema escuro

```scss
:root[data-theme='dark'] ::selection {
  background: var(--selection);
  color: var(--text);
}
```

- Escopo **obrigatório** (o teste aceita escopo no escuro OU `--selection` no
  claro; escolhemos o escopo — o claro está congelado).
- Nunca uma `::selection` global solta: sem o escopo, o tema claro ficaria com
  fundo indefinido (declaração inválida em tempo de compute).
- Posição sugerida: logo após o bloco do tema claro, antes de `*`.

### 3.4 Destaque de item — segundo uso do token (contrato do teste)

```scss
.leaf.router-link-active {
  background: var(--selection);
  color: var(--text);
}
```

Substitui `background: color-mix(in srgb, var(--accent) 18%, transparent)` e
`color: var(--accent)` — o azul de link sobre a seleção azul daria ~2.76:1;
`--text` sobre `--selection` dá ~5.73:1. Este é o "item selecionado/em
destaque" do contrato, sem cor ad hoc fora da paleta. `.card.drop-target` e
`.column.drag-over` **continuam** com `--accent`/`--danger` (semântica de drag,
não de seleção).

### 3.5 Botão primário

```scss
.button {
  /* ... */
  background: var(--button-bg, var(--accent));
  /* ... */
}
```

O fallback preserva o claro congelado (`--button-bg` não existe lá →
`#0052cc`); no escuro o botão vira `#0e639c` com `--accent-contrast: #ffffff`
(~6.4:1, ADR do David). `--accent` fica para links/foco/ativo, fiel ao Dark+.

## 4. Ajuste do arnés (uma linha)

```diff
-const css = readFileSync(join(here, '..', 'src', 'styles', 'main.css'), 'utf8')
+const css = readFileSync(join(here, '..', 'src', 'styles', 'main.scss'), 'utf8')
```

Nenhuma outra alteração em `dark-theme.feature`/`dark-theme.test.mjs`.

## 5. Barra de qualidade

- `cd frontend && node --test tests/*.test.mjs` — `dark-theme` 8/8 verde e a
  suíte `node:test` inteira verde.
- `npx vitest run` — sem regressão (32/32 na referência atual).
- `npx vite build` ou `npm run build` — SCSS compila (sass 1.105.1 já é
  devDependency).
- Proibições: tocar no bloco claro; tirar tokens de estado; introduzir cor de
  seleção ad hoc; alterar `seed.js`/views; comentários no código (PHILOSOPHY).
- Git: branch `feat/KAN-8-dark-plus-tokens`, Conventional Commits, commit
  atômico (scss + 2 contratos de teste + este guia) conforme `docs/GITFLOW.md`.

## 6. Desvios conscientes (não consertar nesta fatia)

Medidos pela fórmula WCAG em 2026-10-19, pertencem à referência:

- `--border #454545` sobre `--bg #1e1e1e` → ~1.74:1 (1.4.11 não-texto pede 3:1);
- `--muted #858585` sobre `--surface #252526` → ~4.15:1 e sobre
  `--surface-2 #2d2d2d` → ~3.73:1;
- cumprem o piso do contrato: `--text/--bg` ~11.25:1, `--text/--selection`
  ~5.73:1, `--accent/--bg` ~5.43:1, `--accent/--surface` ~4.99:1,
  `--muted/--bg` ~4.52:1, botão `#ffffff`/`#0e639c` ~6.40:1.

O critério de aceitação é a comparação lado a lado validada pelo usuário.
