# Guia de Implementação — KAN-15

Menu de perfil do usuário no topo direito (avatar, nome, profile e logout).

Status: RFC aprovada (design aprovado por David em 14:47; leitura de produto
fechada por Vivy em 15:06). Escopo desta rodada: **Fase 1 — MVE**. Autor da
etapa: Framework Architect. Interface: `frontend/` (SPA Vue 3 + Vite, backend
fake). O backend PHP real **não** é tocado nesta rodada.

Fonte única do aceite: `frontend/tests/profile-menu.{feature,test.mjs}` +
`frontend/cypress/e2e/profile-menu.cy.js` (sub-tarefa KAN-37).

## 1. Decisões herdadas (não reabrir)

- **Foto:** placeholder fixo e determinístico (inicial do usuário em círculo de
  38px, contorno neutro). Sem upload, sem presets, sem troca de foto.
- **Perfil:** edita **apenas** o nome de exibição. O nome é rótulo; **jamais**
  altera a credencial de login. Teto de **40 caracteres** na entrada; aceita
  apelido ou nome composto.
- **Nome de exibição:** na tela, **linha única com reticências** e largura
  máxima fixa (≈14 caracteres no desktop, ≈11 em tela pequena). O nome completo
  permanece no dado, no rótulo acessível (`title`/`aria-label`) e por inteiro na
  tela de profile. Sem nome definido → mostra o **identificador de login**.
- **Logout:** direto, sem confirmação. Encerra a sessão e sai da área
  autenticada. Com Bearer sem estado, nada há para invalidar no servidor; a
  revogação server-side pertence ao trilho de Hardening do ROADMAP.
- **Persistência do nome:** vive no **protótipo** (backend fake). Nenhum campo
  ou rota de nome de exibição nasce no backend PHP real até existir identidade
  de usuário (Fase 2).
- **"profile" ≠ "Profiles":** o menu do usuário é conceito distinto dos perfis
  de papel do Time (`/teams/:teamId/profiles`). Rotas e rótulos nascem
  distintos.

## 2. Identidade no protótipo (contrato congelado)

Não existe sessão/`login` no frontend novo. Introduz-se um **usuário corrente
singleton** no backend fake, distinto da coleção `profiles`:

- `GET /api/user` → `200 { login, displayName }`.
- `PATCH /api/user` (corpo `{ displayName }`):
  - após `trim`, `<= 40` caracteres → `200 { login, displayName }`;
  - `> 40` caracteres → `422 { error: "display name must be at most 40 characters" }`;
  - `login` é **sempre ignorado** (nunca muda a credencial).
- Seed: `user: { login: "admin", displayName: "" }`.
- `user` **não** entra em `COLLECTIONS`; é tratado antes do ramo de coleções.

Regra do dado (teto 40) e regra da tela (truncagem) vivem em camadas distintas:
o teto é validado no formulário **e** no host; a truncagem é só CSS.

## 3. Store de sessão (`frontend/src/store/session.js`)

Estado reativo: `{ login: '', displayName: '', signedIn: true }`.

- `label` (computado) = `displayName || login` — a fonte do texto exibido (M4).
- `initial` (computado) = primeiro caractere de `label` em maiúscula, ou `?`.
- `load()` → `GET /api/user` e hidrata `login`/`displayName`.
- `rename(name)` → `PATCH /api/user { displayName: name }` e atualiza o estado.
- `signOut()` → `signedIn = false`. `signIn()` → `signedIn = true`.
- `signedIn` **não** é persistido: recarregar a página restaura a sessão de
  demonstração. A sessão real é dona da Fase 2.

Bindings em `frontend/src/api/index.js`:
`fetchUser: () => request('GET', '/api/user')` e
`updateUser: (data) => request('PATCH', '/api/user', data)`.

## 4. Componente `ProfileMenu.vue`

Raiz `.profile-menu` (filho **único** acrescentado ao fim do `.topbar`).

- Acionador `<button class="profile-trigger">` com `aria-haspopup="menu"`,
  `aria-expanded="open"` e `aria-label` com o **nome completo** +
  identificador de conta (M3).
  - `.profile-avatar`: círculo de **38px**, contorno neutro
    (`background: var(--surface-3); border: 1px solid var(--border)`), a inicial.
  - `.profile-name`: **abaixo** do avatar, linha única:
    `max-width: 14ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;`
    (M3). `@media (max-width: $sidebar-breakpoint) { max-width: 11ch; }`.
    `:title` com o nome completo.
- Dropdown `.profile-dropdown` (`role="menu"`, `v-if="open"`):
  - `profile` (`role="menuitem"`) → `router.push('/profile')`.
  - `logout` (`role="menuitem"`) → `session.signOut()`.
- Acessibilidade e fechamento: abre no clique; fecha com `Escape`; fecha ao
  clicar fora; devolve o foco ao acionador ao fechar. Reaproveitar o padrão de
  teclado já existente em `AppSidebar.vue` (`keydown`/`Escape`).

## 5. Tela de profile e rota

Rota: `{ path: '/profile', name: 'profile', component: ProfilePage, meta: { title: 'Profile' } }`.

`frontend/src/views/ProfilePage.vue`:

- Avatar no mesmo placeholder (maior), o identificador de login em modo
  **somente leitura** e um formulário com o nome de exibição **por inteiro**
  (sem truncagem).
- `maxlength`/validação de 40; ao enviar, `await session.rename(name)`; sucesso
  visível; erro do host exibido (via `Error.message`).
- Nunca envia nem altera `login`.

## 6. Logout / fim de sessão (M5) — observável escolhido

Substância congelada pela PO (15:06): **sessão encerrada e área protegida
inacessível**. A face visual pertence ao refinamento; o Framework Architect
propõe e congela a costura:

- Em `App.vue`, com `v-if="session.signedIn"` para a casca (sidebar, scrim,
  banner, topbar, main) e `v-else` para uma **vista neutra de fim de sessão**
  (`.session-ended`): nome do produto + "Session ended" + ação "Sign in again"
  que chama `session.signIn()`.
- Consequência: com `signedIn = false`, **nenhum** conteúdo protegido renderiza.
- O refinamento pode ajustar a face visual; a costura (`signedIn` gate) e a
  substância não mudam.

## 7. Convivência no topo (M6)

- Grupos fixos: à esquerda o acionador do drawer (KAN-9) e a grade de colapso
  (KAN-10); à direita o menu de perfil (esta rodada) e a grade direita (KAN-5,
  quando chegar).
- Âncoras (`flex: 0 0 auto`) **não encolhem**; quem cede é o título central:
  `.topbar h1 { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }`.
- O nome tem largura máxima fixa (M3), então nunca quebra em duas linhas nem
  empurra o layout, em desktop ou tela pequena.

## 8. Mapa de alterações e sub-tarefas

```
frontend/src/data/seed.js                     → user singleton                       (KAN-32)
frontend/plugin/fake-backend.js               → GET/PATCH /api/user + export handle  (KAN-32)
frontend/src/store/session.js                 → novo estado de sessão                (KAN-33)
frontend/src/api/index.js                     → fetchUser / updateUser               (KAN-33)
frontend/src/components/ProfileMenu.vue       → novo componente                      (KAN-34)
frontend/src/styles/main.scss                 → .profile-* , .session-ended, h1 yield (KAN-34, KAN-35)
frontend/src/App.vue                          → session.load() + ProfileMenu + gate   (KAN-35)
frontend/src/views/ProfilePage.vue            → nova tela                            (KAN-36)
frontend/src/router.js                        → rota /profile                        (KAN-36)
frontend/tests/profile-menu.{feature,test.mjs}→ BDD + integração                    (KAN-37)
frontend/cypress/e2e/profile-menu.cy.js       → E2E de navegador                     (KAN-37)
```

Ordem de dependência (imports reais entre módulos):

1. **KAN-32** e **KAN-33** podem correr em paralelo (contrato congelado).
2. **KAN-34** (ProfileMenu) e **KAN-36** (ProfilePage) dependem de
   **KAN-33** (importam `store/session.js`).
3. **KAN-35** (App.vue) depende de **KAN-34** (importa o componente).
4. **KAN-37** (testes) é vermelho-fase enquanto KAN-32/KAN-33 não chegarem.

Aqueles que dependem de um módulo ainda não mesclado em `main` devem ser
bloqueados pelo próprio codador ao assumi-los
(`kanban block <n> --reason "waiting on <m>"`) — o autor do item (Framework
Architect) não consegue pré-marcar `blocked_by`: o guard do quadro exige que o
`worker` seja o autor, e itens recém-criados nascem com `worker` vazio. Registro
para o mantenedor do quadro.

`App.vue` e `main.scss` são arquivos quentes (KAN-10/KAN-11 em voo): rebasar
sobre `origin/main` na execução. Merge ideal: 1 e 2 primeiro; 3 e 5 depois; 4
por último; 6 quando o restante estiver em `main`.

## 9. Barra de qualidade e aceite

- `node --test frontend/tests/*.test.mjs` com a suíte nova **verde**. As
  falhas vermelho-fase herdadas de KAN-10/KAN-16 e KAN-11 (testes que já
  importam `{ handle }` e aguardam implementação em voo) **não** contam como
  regressão desta rodada.
- E2E de navegador (`npm run test:e2e`) sem erro de runtime/console, cobrindo:
  avatar 38px + nome + dropdown; truncagem em linha única; profile edita o nome
  sem tocar no login; logout encerra a sessão e a área protegida fica
  inacessível.
- Sem comentários no código; sem imports/variáveis mortos; sem `switch/else`
  evitável. Consistente com o estilo do `frontend/`.
- `docs/GUIDE.md` existe; não há exemplo de "identidade de sessão no
  protótipo" — este guia o estabelece (seções 2–6) para reuso futuro.
- Mensagens de erro em **inglês** (a UI é inglesa; contrasta com as mensagens
  PT congeladas para sources em KAN-11 — decisão registrada aqui).
- Commitar este guia em `docs/` no mesmo PR que o implementa.

## 10. Riscos e notas para o codador

- `frontend/plugin/fake-backend.js` é compartilhado com KAN-11 (KAN-27 introduz
  `export function handle`). Se o export já existir, **não** duplicar; apenas
  adicionar o ramo `user` antes de `COLLECTIONS`.
- `frontend/src/App.vue` é editado por KAN-24 (colapso do sidebar, em Coding).
  Inserir `<ProfileMenu />` como **último** filho do `.topbar`; não reordenar os
  controles existentes.
- Colisão conceitual "profile" (menu) ≠ "Profiles" (papéis do Time): rota
  `/profile` distinta de `/teams/:teamId/profiles`.
- Idiomas de largura distinta podem exigir revisão da truncagem — o caso volta
  ao registro de KAN-3, não a uma nova discussão.

## 11. Barra de aceite completa (fonte única fecha os nove itens)

O green da KAN-37 prova o checklist da PO (Vivy 20:05) **por inteiro**, não só
os itens literais da seção 9. Três grãos não estavam na fonte única; entram aqui
como asserções obrigatórias, na forma operacionalizada pela QA (Alexya 00:32):

1. **38px medidos no navegador (E2E).** `getBoundingClientRect()` do
   `.profile-avatar`: largura e altura arredondadas = **38** (tolerância ±1px de
   subpixel). A integração já afirma o `38px` no CSS de origem; o grão é a
   medição no navegador, não a asserção em si.
2. **Caso de tela pequena (E2E).** `cy.viewport` logo abaixo do ponto de corte
   **lido da variável CSS `--breakpoint-sidebar` da KAN-12** — nunca o literal
   `1024` — provando: formulário de profile interagível, nome em linha única,
   topo sem estourar e sem overflow ao lado do acionador do drawer (KAN-9).
3. **Login imutável na tela (E2E).** Após salvar o nome composto, re-afirmar que
   o `input[readonly]` do login mantém o valor de antes do save.

Correção da fonte única (ponto 4 do coder Kaio, 20:20 — decisão do Framework
Architect): a asserção `#app-sidebar` `be.visible` após "Sign in again" acopla o
aceite ao estado do drawer, que é dependente de viewport. No viewport padrão do
Cypress (`1000px`, abaixo de `$sidebar-breakpoint: 1024px`), o drawer correto
nasce fechado e fora da tela (`position: fixed; transform: translateX(-100%)`) —
Cypress o considera `hidden`. A restauração da sessão deve ser provada por um
observável independente de viewport: `.topbar` (ou `.profile-trigger`) **visível**
e `#app-sidebar` **existente** (`should('exist')`), não visível. O KAN-37 aplica
esta correção à spec ao ajustar os seletores.
