# multi-agent frontend

Reactive SPA for the multi-agent board (Vue 3 + Vite). It is **independent from
the backend**: it talks to a REST API, and for now it runs against an in-memory
**simulated** backend so the whole user experience can be mapped and tested before
the real endpoints exist.

## Simulated mode

Every feature has a simulation flag (`src/api/simulation.js`). While a feature is
simulated it uses `src/api/mock.js` (demo data) instead of `src/api/real.js`
(the REST client). This makes it explicit which parts of the UI are real.

- The **orange banner** at the top means at least one screen is simulated.
- An **orange dot** marks each simulated menu entry.
- Toggle everything with the **Sim** button in the top bar, or `?sim=0` / `?sim=1`
  in the URL. Individual flags are persisted in `localStorage`.

As the backend implements each feature, flip its flag to `false` (or edit
`simulation.js`) so the screen starts using `realApi`.

## Run

```bash
npm install
npm run dev          # http://localhost:5173 (simulated by default)
```

The dev server proxies `/api` to `VITE_API_TARGET` (default
`http://localhost:8080`) so that, once real endpoints exist, you can point at the
PHP board.

## Build (static hosting)

```bash
npm run build        # outputs dist/
npm run preview      # serve the build locally
```

The output is a static bundle — deploy `dist/` to Cloudflare Pages (or any static
host) with no VPC. The app uses a hash router, so no server rewrite is required.
Set `VITE_API_URL` at build time to point at the REST API (default `/api`).

## Layout

```
src/api/        simulation flags, mock backend, real REST client, feature router
src/data/       seed data for the simulated backend
src/store/      reactive board store (issues, columns, statuses, agents)
src/components/ sidebar, icons, board column, issue card, issue panel
src/views/      board page + placeholder admin pages
src/styles/     theme variables (dark default) and layout
```

## Scope of the current increment

Implemented: app shell (sidebar with dropdowns, dark/light theme) and the board
(responsive columns, cards with status colour and aging flag, the right-hand issue
panel, and drag & drop that respects the configured transitions).

Placeholder: Columns, Statuses, Transitions, Issues and the Agents
Roles/Profiles/Experiences screens — they are wired in the router/menu and are
built next.
