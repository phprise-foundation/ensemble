# Guia de Implementação — KAN-89: Avatar circular para agentes

Status: RFC aprovada (KAN-89). Autora da etapa: Framework Architect (Lucy).
Este guia é a fonte única do contrato do `Avatar` e da guarda de upload desta
entrega; produção e testes mudam juntos. Base: RFC KAN-89, viabilidade de
Jarvis, ADR de David, `docs/PHILOSOPHY.md`, `docs/GUIDE.md`,
`docs/ARCHITECTURE.md` e os guias KAN-15/KAN-62/KAN-79/KAN-88.

## 1. Objetivo e limites

Entregar um único componente `Avatar`, circular, com recorte central sem
distorção, fallback por inicial e três tamanhos, aplicado nesta entrega às três
superfícies do agente: lista de agentes, preview do formulário e assignee do
painel da issue. A entrega inclui o retrato de exemplo no seed, o preview ao
vivo, a ação “Remove photo” e a guarda de tipo/tamanho do upload.

Fora de escopo, por decisão da RFC:

- cards e comentários do board (KAN-79);
- histórico (KAN-80);
- foto do usuário (KAN-75);
- cropper, upload real, endpoint, CSP e mudança do modelo ou do formato de
  `portrait` (continua data URL no `db.json` do fake backend);
- validação no fake backend: a guarda é de UI e não impede um `PATCH` direto
  pela API;
- i18n da SPA: os rótulos visíveis continuam em inglês, como o restante da UI.

## 2. Estado de partida e frentes em voo

- `frontend/src/components/IssuePanel.vue` usa `.portrait` (42×56, raio 8) e
  ignora `agent.portrait`.
- `frontend/src/views/AgentsPage.vue` usa `.portrait.lg` (64×84) na lista e no
  preview, com `background-image`; o formulário tem o rótulo
  “Portrait (3x4)” e nenhuma validação de tipo/tamanho.
- `frontend/src/components/IssueCard.vue` usa `.avatar.sm` (22×22, raio 6) e
  também ignora `portrait`.
- `frontend/src/styles/main.scss` define `.avatar` em `~776` e `.portrait` em
  `~816`, além de `.portrait.lg` em `~1069`.
- `frontend/src/data/seed.js` não tem `portrait` em nenhum agente; só
  `avatarColor`.
- O fake backend persiste `portrait` como data URL e reescreve o `db.json`
  inteiro a cada mutação. O `accept` do `<input>` não é barreira.
- KAN-104 (varredura de ícones da KAN-88) também edita `AgentsPage.vue` e
  `IssuePanel.vue`: a aplicação (KAN-111) fica bloqueada até ela aterrissar.
  KAN-108 e KAN-97 tocam `main.scss` em seções diferentes: rebase antes de
  começar, sem reordenar o que já estiver na árvore.
- KAN-102 (CI roda `test:unit`) está em voo. Não criar outra sub-task de CI;
  se KAN-102 ainda não estiver na `main`, rodar `npm run test:unit`
  explicitamente no PR e anexar a saída.

## 3. Contrato do componente `Avatar`

### 3.1 API

`frontend/src/components/Avatar.vue`, sem dependência nova.

| Prop | Tipo | Default | Contrato |
|---|---|---|---|
| `name` | String | obrigatória | nome do agente/usuário; define a inicial e o rótulo |
| `image` | String | `''` | data URL do retrato; vazio cai no fallback |
| `color` | String | `'#333333'` | hex de fundo do fallback; consumidores passam `avatarColor` |
| `size` | String | `'md'` | `sm` 22px, `md` 42px, `lg` 64px; validar no `defineProps` |
| `ring` | Boolean | `false` | anel de 2px; desligado por padrão |
| `decorative` | Boolean | `false` | `true` quando o nome está visível ao lado |

O componente recebe dados neutros: nome, imagem e cor. Não importa
`boardStore`, não conhece agente nem usuário e não faz chamada de rede.

### 3.2 DOM e ARIA

Com imagem, quando o avatar é a única indicação de identidade (preview):

```html
<span class="avatar lg" style="background-color: #4c9aff; color: #000000"
      role="img" aria-label="Avatar de Vivy">
  <img src="data:image/png;base64,..." alt="">
</span>
```

Com imagem e nome visível ao lado (lista e painel), `decorative` é `true` e o
nó não é anunciado duas vezes:

```html
<span class="avatar lg" style="background-color: #4c9aff; color: #000000"
      aria-hidden="true">
  <img src="data:image/png;base64,..." alt="">
</span>
```

Sem imagem, o fallback é a inicial maiúscula no lugar do `<img>`:

```html
<span class="avatar md" style="background-color: #4c9aff; color: #000000"
      role="img" aria-label="Avatar de Vivy">V</span>
```

Regras:

1. `decorative=false` (default): `role="img"` e
   `aria-label="Avatar de {nome}"`; o `<img>` interno tem `alt=""` para não
   duplicar a leitura.
2. `decorative=true`: `aria-hidden="true"` e **sem** `role`/`aria-label`.
3. O fallback usa `(name.trim().charAt(0) || '?').toUpperCase()`; nome vazio
   vira `?`.
4. `image` que falha no carregamento cai no fallback via `@error`; nenhum
   ícone de imagem quebrada. Um `watch` em `image` zera a falha quando o valor
   muda.
5. O rótulo acessível segue a RFC e fica em português; o resto da UI continua
   em inglês.

### 3.3 Tamanhos e CSS

Substituir o bloco `.avatar` atual por esta base (o card herda a forma
circular; ver §4.3) e acrescentar os modificadores:

```scss
.avatar {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  flex: 0 0 auto;
  font-size: 11px;
  font-weight: 700;
  color: #04101f;
}

.avatar.sm {
  width: 22px;
  height: 22px;
  font-size: 11px;
}

.avatar.md {
  width: 42px;
  height: 42px;
  font-size: 18px;
}

.avatar.lg {
  width: 64px;
  height: 64px;
  font-size: 24px;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
  display: block;
}

.avatar.ring {
  box-shadow: 0 0 0 2px var(--accent);
}
```

O componente sempre aplica uma classe de tamanho (`sm`, `md` ou `lg`) e
`ring` só quando a prop é `true`. Nenhuma superfície desta entrega usa anel.
O `color: #04101f` da base fica apenas para o markup legado do card; o
componente sempre define a cor inline.

### 3.4 Fallback e contraste

O texto do fallback é preto (`#000000`) ou branco (`#ffffff`), escolhido pela
luminância relativa do `color`. O limiar `0,179` é o ponto em que os dois
contrastes se cruzam; com preto/branco o pior caso é ≈4,58:1, acima de 4,5:1.
Não usar `#04101f`: em cores de luminância intermediária ele cai para ≈4,37:1.

```js
const INK_DARK = '#000000'
const INK_LIGHT = '#ffffff'
const INK_THRESHOLD = 0.179

function channel(value) {
  const normalized = value / 255
  return normalized <= 0.03928
    ? normalized / 12.92
    : ((normalized + 0.055) / 1.055) ** 2.4
}

function readableInk(background) {
  const value = background.replace('#', '')
  if (!/^[0-9a-f]{6}$/i.test(value)) {
    return INK_LIGHT
  }
  const channels = [0, 2, 4].map((offset) =>
    channel(parseInt(value.slice(offset, offset + 2), 16)),
  )
  const luminance =
    0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]
  return luminance > INK_THRESHOLD ? INK_DARK : INK_LIGHT
}
```

Cores fora do formato `#rrggbb` caem em branco. Os consumidores devem passar
um hex; `avatarColor` já é hex no seed.

## 4. Aplicação nas superfícies

### 4.1 `AgentsPage.vue`

Lista (o nome está visível no card, então `decorative`):

```html
<Avatar
  size="lg"
  :name="agent.name"
  :image="agent.portrait"
  :color="agent.avatarColor"
  decorative
/>
```

Preview do formulário (o avatar é a única indicação visual naquele bloco):

```html
<Avatar
  size="lg"
  :name="draft.name || '?'"
  :image="draft.portrait"
  :color="draft.avatarColor"
/>
```

O preview continua dentro de `.agent-preview`; não redesenhar o layout.

### 4.2 `IssuePanel.vue`

No `.panel-meta`, trocar o `.portrait` por:

```html
<Avatar
  size="md"
  :name="agent?.name || '?'"
  :image="agent?.portrait || ''"
  :color="agent?.avatarColor || '#333333'"
  decorative
/>
```

O nome continua em `.panel-assignee`; por isso `decorative`. “Unassigned”
permanece, com o fallback `?`.

### 4.3 CSS e card

Depois que `AgentsPage.vue` e `IssuePanel.vue` não usarem mais `.portrait`,
remover `.portrait` e `.portrait.lg` de `main.scss`. Não editar
`IssueCard.vue`: ele continua exibindo só a inicial e a foto no card é
aceite da KAN-79. Como o card usa `.avatar.sm`, ele passa a ser circular e
22px por herdar a base; é um efeito colateral aceito e não uma entrega da
KAN-79.

## 5. Upload e remoção

### 5.1 Módulo reutilizável

Criar `frontend/src/lib/image-upload.js`, sem Vue e sem dependência nova:

```js
export const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp']
export const IMAGE_MAX_BYTES = 2 * 1024 * 1024

export function imageFileError(file) {
  if (!file) {
    return 'Choose an image file.'
  }
  if (!IMAGE_TYPES.includes(file.type)) {
    return 'Use a PNG, JPEG or WebP image.'
  }
  if (file.size > IMAGE_MAX_BYTES) {
    return 'The image must be at most 2 MB.'
  }
  return ''
}

export function readImageFile(file) {
  const problem = imageFileError(file)
  if (problem) {
    return Promise.reject(new Error(problem))
  }
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result))
    reader.onerror = () => reject(new Error('Could not read the image.'))
    reader.readAsDataURL(file)
  })
}
```

Este é o único lugar da SPA que decide tipo e tamanho. KAN-75 deve importar
`readImageFile` em vez de repetir a política.

### 5.2 `AgentsPage.vue`

- `accept="image/png,image/jpeg,image/webp"`.
- `onPortrait` chama `readImageFile`; em erro, mostra a mensagem e **mantém** a
  foto atual do draft.
- Limpar `event.target.value` no `finally` para o mesmo arquivo poder ser
  escolhido de novo.
- `<p v-if="portraitError" class="error">{{ portraitError }}</p>` logo abaixo
  do input.
- `<button v-if="draft.portrait" type="button" class="button ghost"
  @click="removePortrait">Remove photo</button>`. A ação limpa
  `draft.portrait`, `portraitError` e o valor do input.
- `openCreate` e `openEdit` zeram `portraitError`.
- O rótulo visível passa de “Portrait (3x4)” para “Portrait”.
- Nada de cropper, drag-and-drop ou redimensionamento no cliente.

## 6. Seed e fixture

- `frontend/src/data/seed-portrait.js` exporta `VIVY_PORTRAIT`, um PNG 60×80
  (3:4) em data URL, 748 bytes, com o conteúdo do apêndice A.
- `frontend/src/data/seed.js` importa `VIVY_PORTRAIT` e adiciona
  `portrait: VIVY_PORTRAIT` ao agente `vivy`. Nenhuma URL externa.
- O E2E usa um fixture versionado distinto:
  `frontend/cypress/fixtures/agent-portrait-3x4.png`, PNG pequeno com
  proporção 3:4 (por exemplo 90×120), sem data URL inline no teste.
- O seed continua criando o `db.json` pelo `run-e2e.sh`; o retrato de Vivy
  aparece na lista e no painel sem nenhuma chamada extra.

## 7. Contrato de testes

### 7.1 BDD — `frontend/tests/avatar.feature`

Cenários em português do Brasil, com a origem “KAN-89” e o guia como
autoridade:

1. Três tamanhos circulares: `sm` 22, `md` 42, `lg` 64, raio 50%, `object-fit:
   cover` e recorte central.
2. Fallback: inicial maiúscula sobre `avatarColor`, contraste ≥ 4,5:1.
3. Imagem quebrada cai no fallback, sem ícone de imagem quebrada.
4. `aria-label="Avatar de {nome}"` quando o avatar é a única identidade;
   `aria-hidden="true"` quando o nome está visível ao lado.
5. Anel de 2px opcional, desligado por padrão.
6. Upload válido atualiza o preview antes de salvar.
7. Salvar reflete na lista e no painel.
8. “Remove photo” limpa o draft e devolve o fallback.
9. Tipo inválido ou arquivo acima de 2 MB é recusado com mensagem e não
   altera a foto atual.
10. O rótulo não contém “(3x4)” e qualquer proporção é aceita.

A QA deixou `frontend/tests/avatar.feature` (11 cenários) e
`frontend/tests/avatar.test.mjs` (10 guardas) prontos e **untracked** no
workspace principal. A KAN-110 estende esses arquivos, não os recria; em
worktree, copie de `/home/esdras/src/phprise/multi-agent/frontend/tests/` antes
de começar. O `.test.mjs` fica verde com a fundação da KAN-108 e entra no mesmo
PR dela ou da KAN-110, nunca sozinho.

### 7.2 Unitário e integração (vitest)

`frontend/tests/avatar.spec.js` monta `Avatar` e prova classes `sm/md/lg` e
`ring`, `<img>` com `src`, inicial maiúscula, `?`, ARIA decorativo/não
decorativo, queda para o fallback no `@error` e reset ao trocar `image`. O
contraste é calculado no teste a partir da cor inline para cores claras,
escuras e médias. Não assertar dimensões computadas no jsdom: o CSS é global e
as medidas reais ficam no E2E.

`frontend/tests/image-upload.spec.js` prova `imageFileError` para PNG/JPEG/
WebP, tipo inválido e acima de 2 MB; `readImageFile` devolve data URL para um
`File` válido e rejeita o inválido.

### 7.3 Guardas estáticas (node:test)

`frontend/tests/avatar.test.mjs` lê `main.scss`, `Avatar.vue`, `seed.js` e
`seed-portrait.js` e falha se: o raio não for 50%, faltar `object-fit: cover`
ou `object-position: center`, os tamanhos 22/42/64 sumirem, o seed perder o
`portrait` de Vivy, o data URL não for PNG ou uma dependência nova aparecer no
`package.json`. As guardas não devem depender da aplicação nas páginas; isso
é da sub-task de E2E.

### 7.4 E2E — `frontend/cypress/e2e/avatar.cy.js`

Medir no navegador real, com `collectErrors` e sem `vite-error-overlay`:

- lista: `.agent-card .avatar.lg` com `<img>`, 64×64, `border-radius: 50%` e
  `object-fit: cover`; o PNG 3:4 não é distorcido;
- edição: abrir Vivy, o preview mostra o retrato do seed; `selectFile` do
  fixture atualiza o preview antes de salvar; salvar reflete na lista;
- painel: abrir KANB-1 e provar `.panel-meta .avatar.md` 42×42 circular com a
  foto salva;
- remover: “Remove photo” limpa o preview; salvar mostra a inicial `V` com
  contraste ≥ 4,5:1 na lista e no painel;
- inválido: `selectFile` com `text/plain` e com mais de 2 MB mostra a mensagem
  e preserva a foto;
- rótulo sem “(3x4)”;
- `afterEach` restaura `vivy` via `cy.request('PATCH', '/api/agents/vivy',
  { portrait: original })`; o `db.json` não pode ficar alterado para as outras
  suítes.

`frontend/tests/avatar-surfaces.test.mjs` guarda o fechamento: `AgentsPage` e
`IssuePanel` importam `Avatar`, `frontend/src` não tem `.portrait` nem
“(3x4)”, o fixture existe e o PNG tem proporção 3:4. A leitura do IHDR
(bytes 16–23) evita depender de metadados externos.

### 7.5 Barra de qualidade

Executar, na ordem, a partir de `frontend/`:

```bash
npm ci
node --test tests/*.test.mjs
npm run build
npm run test:unit
npm run test:e2e
```

Sem erro de runtime/console e sem regressão nas suítes de board, agentes,
perfil, copilot, sidebar, drawer e topbar. Em múltiplos worktrees, usar
`E2E_PORT` exclusiva porque o dev server é `--strictPort`. Se KAN-102 ainda
não estiver na `main`, `npm run test:unit` não roda no CI: anexar a saída
desse comando ao PR e não considerar a entrega concluída sem ela.

## 8. Ondas e sub-tasks

| Onda | Item | Assignee | Depende de | Entrega |
|---|---|---|---|---|
| 1 | KAN-108 — fundação do Avatar e da guarda | Coder | — | `Avatar.vue`, `image-upload.js`, CSS, seed e este guia |
| 2 | KAN-110 — BDD e testes do contrato | Kaio | KAN-108 | feature, vitest e guardas estáticas |
| 2 | KAN-111 — aplicação nas superfícies | Coder | KAN-108 e KAN-104 | `AgentsPage.vue`, `IssuePanel.vue`, remoção de `.portrait` |
| 3 | KAN-109 — E2E e fixture 3x4 | Mai | KAN-110 e KAN-111 | Cypress, fixture e guarda das superfícies |

KAN-110 e KAN-111 podem correr em paralelo quando KAN-108 e KAN-104 estiverem
na árvore; mexem em arquivos diferentes. KAN-109 fecha a prova de aceitação
depois dos dois. A fundação commita este guia; as demais referenciam-no.

## 9. Riscos e notas para quem codar

- **KAN-104 em voo:** não começar a aplicação antes dela; o conflito seria em
  `AgentsPage.vue` e `IssuePanel.vue`. Rebase em `origin/main` e preserve a
  varredura de ícones (títulos/aria-labels) da KAN-88.
- **KAN-97 em voo:** `main.scss` também recebe a topbar; rebase e não
  reordenar regras alheias.
- **KAN-79/80/75:** consomem o componente depois. Não editar `IssueCard.vue`
  nesta entrega e não duplicar `.avatar` no futuro. O tamanho 38px do
  `ProfileMenu` não está na escala sm/md/lg; a KAN-75 decide se estende a
  escala, sem criar outra classe de avatar.
- **Contraste:** não voltar ao `#04101f` fixo; o limiar preto/branco é o que
  garante 4,5:1 em qualquer `avatarColor` hex.
- **Imagem quebrada:** o `@error` é obrigatório; `background-image` não
  permite o fallback confiável e sai do contrato.
- **Data URL:** a guarda de 2 MB é de UI; o fake backend continua sem
  validação. Não adicionar endpoint nem mudar o modelo.
- **Idioma:** guia, BDD, comentários de board e commits em pt-BR; rótulos da
  UI em inglês; `aria-label` do avatar em pt-BR por contrato da RFC.
- **Sem comentários no código.** O porquê vive neste guia e no commit.
- **Commits:** Conventional Commits com o ID da sub-task; branch
  `feat/kan-89-...`; PR com o guia e a evidência das suítes.

## Apêndice A — retrato do seed

`frontend/src/data/seed-portrait.js`:

```js
export const VIVY_PORTRAIT = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADwAAABQCAMAAAByFOZhAAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAABLUExURUeR8EGE2z+A1Dx6yTRrsbbH3P///5Gry3KYx9Db6f7+/kx4sKK61y9gnluEuW6NtClWjkVplYCauiJIdh5Bahw9YxYxTw8jOAwdLm+ST2UAAAABYktHRAZhZrh9AAAAB3RJTUUH6goTCwIimBS+EwAAACV0RVh0ZGF0ZTpjcmVhdGUAMjAyNi0xMC0xOVQxMTowMjozMiswMDowMByrPK0AAAAldEVYdGRhdGU6bW9kaWZ5ADIwMjYtMTAtMTlUMTE6MDI6MzIrMDA6MDBt9oQRAAAAKHRFWHRkYXRlOnRpbWVzdGFtcAAyMDI2LTEwLTE5VDExOjAyOjM0KzAwOjAwWTOQ9AAAAXpJREFUWMPtketygyAQhTFgvAXFoo3v/6RVdhFQMKjTaTPx/DC7J+eDBQi5dOmlkiTRhW0a3/KcPNSHdcF7dBvhW5Qo9bmEUviDUruyeqod7WkhfFQfCTPmGsv+BczmPFOwwdl6Maa3mHyAD+o/wel9UrbpBOFcRQvLKSejrKLGVtl7tVgtjTvzYzFlofo8Dq7cuSuY2n9hHKR/R8FWNXaZ6h46xSyA8LWcOC5VeXJeGK8XGjwEj4U5PGyjary+eLiBxzFTlywe5mbu2lrHD4tRnivL3ROYlBBQE+ET3tIYgBmEX35YwPu0Ak6f7YM1lOIiu2AYt+B6/F0wbgnfr70wPNF967rCMF6Z2n4LllIK9VWCXspshmsBPmbm7AhLaTB7idaa2oVMhsiQ9NxZMCHDcINwewSO0K/DXRDuDkt2J+AO4L43Rm81U9kHfAX3J/SR8PcJXfB7w89AeOmTpyMV8Wjte+B9emN4GOZ2LgdjDpbphshwQn8H/wBnyd1fSINwTQAAAABJRU5ErkJggg=='
```

O PNG tem 60×80, proporção 3:4, e é usado apenas como exemplo no seed; o
fixture 3x4 do E2E é um arquivo separado.
