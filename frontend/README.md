# Ensemble frontend

Reactive SPA for the Ensemble board (Vue 3 + Vite). It is **independent from
the backend**: it talks to a REST API at `/api`. For this prototype the API is a
**fake backend** — a Vite middleware (`plugin/fake-backend.js`) that stores
everything in `frontend/.data/db.json`. Agents are **not** triggered yet.

## Run

```bash
npm install
npm run dev          # http://localhost:5173
```

A single command is enough: the same dev server serves the app and the fake API,
so every screen works and data persists between reloads (in `.data/db.json`).

```bash
npm run build        # static bundle in dist/
npm run preview      # serve the build (also with the fake API, for a local check)
```

## Tests

```bash
node --test          # static checks: read the source and assert its contracts
npm run test:e2e     # browser E2E (Cypress): boots the app and drives the UI
```

`npm run test:e2e` starts the dev server on `127.0.0.1:5173` (override with
`E2E_PORT`), waits for it, runs the suite in `cypress/e2e/`, and stops the
server. It fails on any runtime/console error or Vite error overlay — so it is
the check that proves the interface actually comes up, which the static
`node --test` suite cannot. Use `npm run test:e2e:open` for the interactive
runner. When `package.json` changes, run `npm install` first.

To point the app at the real PHP API later, build/serve with
`VITE_API_URL=https://backend` — the fake backend is then bypassed.

## Ícones

Os ícones da interface usam [`lucide-vue-next`](https://lucide.dev) na versão
exata `1.0.0` (em `dependencies`). Somente `src/components/AppIcon.vue` importa
o pacote, via imports nomeados — sem `import *` nem import dinâmico, para o
bundle não inflar.

O wrapper preserva a API `<AppIcon name="edit" size="14" />`, com `name`
obrigatório e `size` com default 18. Cada nome é mapeado a um componente Lucide
no próprio `AppIcon.vue`; todo ícone sai com `stroke-width="2"`,
`fill="none"`, `stroke="currentColor"`, pontas arredondadas e
`aria-hidden="true"`.

A escala de tamanhos é fechada: **14, 16, 18 e 20** (default 18), sempre pela
prop `size` — nunca redimensionar por CSS.

Exceção única: `name="smartboard"` é a marca custom da sidebar e mantém o
desenho próprio (traço 2) dentro do `AppIcon.vue`; não é migrado para Lucide.

## Prototype marking

Everything is backed by the fake backend for now, so an **orange banner** shows
"Prototype backend" and each admin screen carries an orange **Prototype** badge.
The per-feature flags in `src/api/simulation.js` control those markers: flip a
feature to `false` when its real endpoint lands.

## Navigation

The sidebar mirrors the workspace: **Workspaces → Boards / Workflows / Teams /
Sources**. A **workflow** owns its statuses, transitions and issue types; a
**team** owns its agents, roles and profiles; a **board** references a workflow
and owns its columns and filters. **Sources** are **local directories** on your
machine (e.g. `/home/you/src/my-project`); the worker will use one as the
agents' `workdir`. Editors for each node live in the menu (the pencil button).

## Screens

- **Board** — responsive columns, cards (status colour, aging flag, avatar), the
  right-hand issue panel (status + assignee + comments) and drag & drop that
  respects the configured transitions.
- **Workflow → Statuses / Transitions / Issue types** — full CRUD: statuses have
  a type, colour and roles; transitions are user/agent/both; issue types carry
  custom fields by level.
- **Team → Agents / Roles / Profiles** — agent creation (name, who am I, age,
  marital status, roles, profile, 3x4 portrait) and CRUD for roles and profiles.

## Layout

```
src/api/        REST client, prototype/real flags
src/store/      reactive board store (entities + issues)
src/components/ sidebar, board, cards, panel, generic CRUD screen
src/views/      board + admin screens
src/styles/     theme variables (dark default) and layout
plugin/         fake backend (JSON persistence) used in dev/preview
```

## Next (real backend)

Replace the fake backend with the PHP REST API feature by feature, flipping the
simulation flags as each endpoint is implemented. The frontend contract is
already the REST shape: `GET /api/workspace`, `/api/issues`,
`/api/{collection}` CRUD, `POST /api/issues/{id}/move` and `/comments`.
