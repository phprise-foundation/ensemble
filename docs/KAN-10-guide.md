# Guia de Implementação — KAN-10: Colapsar o menu lateral (estilo activity bar)

Status: RFC aprovada + item refinado. Escopo: **Fase 1 (MVE)**.
Autor da etapa: Framework Architect.

## Objetivo

Um botão de grade "esquerda" no menu superior (padrão do VSCode) alterna o menu
lateral entre:

- **Expandido (padrão a cada carregamento):** largura completa (~200px), como já
  aprovado para o desktop.
- **Colapsado (activity bar):** ~48px, apenas ícones visíveis, com a mesma
  navegação preservada e identificação no hover (tooltip).

Exclusivo de **desktop**. No mobile, o drawer do KAN-9 permanece intocado.
Totalmente **client-side**: sem novo endpoint, sem nova tabela, sem storage
(lembrar a preferência é Fase 2, via `localStorage`).

## Decisões que já governam a implementação

- **Breakpoint desktop/mobile:** a fonte única é o **KAN-9/KAN-12**. Não
  duplicar o literal; consumir a variável SCSS `$sidebar-breakpoint` (1024px).
  O controle de colapso é desktop-only e usa `@media (min-width: $sidebar-breakpoint + 1)`.
- **Grupo fixo no topo:** as grades de espacialidade ficam de um lado e o menu
  de perfil (KAN-3) do outro. O toggle de colapso vai **à esquerda do `<h1>`**,
  logo após o `menu-toggle` (que só existe no mobile).
- **Acessibilidade dentro do MVE:** rótulo acessível, `aria-expanded`, foco
  visível e atalho `Ctrl+B`/`Cmd+B`.
- **Sem comentários no código.** O "porquê" vive neste guia e na mensagem de
  commit.
- **Estado convergente:** acionamentos repetidos terminam sempre em expandido
  ou colapsado estável.

## Contrato de nomes (fonte única — guia e testes mudam juntos)

| Item | Valor acordado |
|---|---|
| Chave do ícone em `AppIcon` | `sidebar` → `['M3 3h4v18H3z', 'M10 3h11v18H10z']` |
| Botão de topo | `.icon-btn.sidebar-toggle` |
| Rótulo do botão | `aria-label="Toggle sidebar"` (dinâmico: `Collapse`/`Expand` quando a implementação permitir) |
| Reflexo de estado | `aria-expanded="!sidebarCollapsed"` |
| Estado | `sidebarCollapsed` (`ref(false)`) em `App.vue` |
| Prop do componente | `AppSidebar` aceita `collapsed: Boolean`, `default: false` |
| Classe CSS de colapso | `.sidebar.collapsed` |
| Token de largura | `--activity-width: 48px` no `:root` |
| Atalho | `Ctrl+B` (Linux/Windows) e `Cmd+B` (macOS) |

## Mapa de alterações

```
frontend/src/App.vue                        → estado + botão + atalho + a11y
frontend/src/components/AppSidebar.vue      → prop collapsed + classe + :title
frontend/src/components/AppIcon.vue         → entrada sidebar (KAN-17)
frontend/src/styles/main.scss               → --activity-width + .sidebar.collapsed + .sidebar-toggle
frontend/tests/sidebar-collapse.feature     → cenários BDD (KAN-16)
frontend/tests/sidebar-collapse.test.mjs    → integração Red phase (KAN-16)
```

## Sub-tasks

1. **Ícone `sidebar` em `AppIcon.vue`** — KAN-17 (já criada).
2. **CSS do estado colapsado (activity bar)** — `main.scss`: token
   `--activity-width`, `.sidebar.collapsed`, ocultar textos/chevrons/badges/ações
   (incluindo os rótulos das folhas, que vivem dentro de `RouterLink.leaf`),
   centralizar ícones e controlar a visibilidade desktop-only do `.sidebar-toggle`.
3. **AppSidebar: prop `collapsed`, classe e tooltips** — aceitar a prop, aplicar a
   classe condicional e adicionar `:title` aos links folha.
4. **App.vue: estado, botão de grade e atalho `Ctrl+B`** — estado, botão no topo,
   prop para o sidebar, atalho global com cleanup e atributos de acessibilidade.
5. **BDD + teste de integração** — KAN-16 (já criada).

As sub-tasks 2, 3 e 4 tocam arquivos distintos e podem correr **em paralelo**,
desde que respeitem o contrato de nomes acima. A sub-task 5 (testes) consome
todos os contratos; serve de crítica de aceitação.

## Ponto de atenção — breakpoint compartilhado

A fonte única é a variável SCSS `$sidebar-breakpoint: 1024px`, definida no topo de
`frontend/src/styles/main.scss` e exportada como `--breakpoint-sidebar`. KAN-9
(drawer) e KAN-10 (activity bar) consomem o **mesmo** endereço
(`@media (max-width: $sidebar-breakpoint)` e `@media (min-width: $sidebar-breakpoint + 1)`).
Não inventar um segundo valor.

## Observações para quem codar

- **Persistência** (`localStorage`) é Fase 2 e não bloqueia o MVE; o estado
  recomeça expandido a cada carregamento.
- **Coabitação** com KAN-3/KAN-5/KAN-6: a estrutura de grupos no topo deve
  existir desde o MVE; a ordem final fica para a Fase 2.
- **Código limpo:** sem `//`, sem imports/variáveis mortos, `declare` de estilo
  do projeto onde couber.
