# Changelog

Notable user-facing changes of `multi-agent`. Versions follow SemVer while the
API is in `0.y.z` (development); see `docs/GITFLOW.md` for the tagging and
release policy.

History before this file existed lives in the GitHub releases:
https://github.com/phprise-foundation/multi-agent/releases

## v0.11.5 — 2026-10-18

### Changed

- **KAN-54** — o spec E2E do drawer (`frontend/cypress/e2e/mobile-drawer.cy.js`)
  foi pousado na árvore: vivia apenas não rastreado no worktree e morria num
  `git clean` — a lição do dark-theme repetida na camada de prova: artefato bom
  em endereço fraco. O spec prova o gesto no navegador: nasce sem `.open`,
  abre pelo acionador, fecha por scrim, ESC e troca de rota, foco devolvido ao
  acionador, viewport pinada em `760x900` e `screenshotOnRunFailure` desligado;
  comentários, títulos e mensagens de falha em pt-BR, sem tocar em asserção ou
  seletor. Mudança só de teste, sem código de produção; 3/3 verde reproduzido
  na revisão contra a `main` pós-merge. PR #57, release `v0.11.5`.

## v0.11.4 — 2026-10-18

### Fixed

- **KAN-10** — o colapso do menu lateral deixou de vazar para o drawer mobile:
  as regras `.sidebar.collapsed` passaram a viver dentro de
  `@media (min-width: $sidebar-breakpoint + 1)` e o atalho `Ctrl+B`/`Cmd+B`
  ficou inerte em origem mobile (guarda `isMobile.value` em `App.vue`), fiel à
  decisão E1 — o estado colapsado é suspenso no mobile (drawer em largura
  plena, rótulos visíveis) e restaurado ao voltar ao desktop, sem
  `localStorage` (recarregar continua expandido). Duas cercas de regressão de
  fonte novas em `frontend/tests/sidebar-collapse.test.mjs` (escopo do CSS no
  bloco desktop; guarda do atalho consultando o viewport). PR #59,
  release `v0.11.4`.

## v0.11.3 — 2026-10-18

### Changed

- **KAN-37** — suíte de aceite do menu de perfil fechada por inteiro contra a
  seção 11 de `docs/KAN-15-guide.md`, num diff só de teste (três arquivos, sem
  código de produção): o par `frontend/tests/profile-menu.{feature,test.mjs}`
  ganha os cenários de recarga de `#/profile` com o nome persistido e de
  limitação de 40 caracteres na entrada; o E2E
  (`frontend/cypress/e2e/profile-menu.cy.js`) passa a operacionalizar os
  grãos — avatar de 38px medido com `getBoundingClientRect`, viewport derivado
  de `--breakpoint-sidebar` (nunca o literal `1024`), credencial de login
  imutável reafirmada antes/depois do save e após o recarregamento, e sucesso
  afirmado pela região viva `[role="status"]`, não por string. PR #58,
  release `v0.11.3`.

## v0.11.2 — 2026-10-18

### Changed

- **KAN-53** — endurecimento do artefato de teste do drawer: a guarda em
  `frontend/tests/mobile-drawer.test.mjs:13` deixou de casar o valor literal
  `1024px` e passou a afirmar o contrato — existe uma declaração
  `$sidebar-breakpoint: <valor>;` no SCSS. `:14` e `:15` permanecem inalterados.
  Mudança só de teste, sem código de produção.

## v0.11.1 — 2026-10-18

### Changed

- **Contrato de aceite estendido (KAN-10)** — o par
  `frontend/tests/sidebar-collapse.feature` e `frontend/tests/sidebar-collapse.test.mjs`
  passou de 9 para 11 cenários e de 4 para 8 testes (foco visível, rótulos das
  folhas ocultos no estado colapsado, estado determinístico sem `localStorage` e
  breakpoint desktop consumindo `$sidebar-breakpoint + 1`); o guia
  `docs/KAN-10-guide.md` passou a ter endereço único. Mudança só de teste e
  documentação — nenhum código de produção.

## v0.11.0 — 2026-10-18

### Added

- **Profile menu + session gate (KAN-15, MVE Phase 1)** — the top bar now carries
  a user profile menu: a 38px initial avatar and a single-line truncated display
  name, with a dropdown (`profile` / `logout`). Backing it: the `session` store
  (`load`/`rename`/`signOut`/`signIn`) and the fake backend's `GET/PATCH
  /api/user` (login immutable; display name ≤40 chars). A `/profile` route edits
  only the display name. `App.vue` gates the whole shell on `session.signedIn`:
  logging out ends the session and renders a neutral "Session ended" view, and
  "Sign in again" restores the shell. Covered by vitest (`App session gate`) and
  the smoke E2E (§11 selector fix).
- **Sidebar icon glyph (KAN-41)** — the `sidebar` glyph in `AppIcon.vue`.

### Changed / Fixed

- **Id immutability (KAN-43/44/46/47)** — the persistence verb now derives from
  `__existing` (not `data.id`); the server rejects divergent ids with `409` and
  sanitises `__existing`.
- **Mobile drawer (KAN-39)** — the `open` prop no longer shadows the `open`
  function, so the drawer opens correctly on mobile.
- **E2E harness (KAN-38/52)** — Cypress foundation, `test:e2e` script, and a
  deterministic clock (support file disables CSS transitions/animations).
- **CI PR fence (KAN-45)** — automated triple-green (node test + build + e2e).
- **Vitest collection (KAN-40)** — `node:test` (`*.test.mjs`) suites excluded
  from `vitest run`.

## v0.10.2 — 2026-10-18

### Changed

- **KAN-30** — test-artifact hardening: the drawer toggle assert in
  `frontend/tests/mobile-drawer.test.mjs` now matches the specific handler
  `@click="sidebarOpen = true"`, aligned with the scrim assert's specificity;
  the obsolete `(Red Phase)` label was verified as already removed (KAN-14).
  Test-only change, no production code touched.

## v0.10.1 — 2026-10-18

### Fixed

- **KAN-29** — sidebar editors: `team` and `workflow` no longer derive the id
  slug at creation (`AppSidebar.vue`). The derivation now requires the guard
  `current.draft.__existing` — the same pattern `source` received in KAN-20 —
  so a new draft reaches `saveEntity` with an empty id and the store sends
  `POST` on creation and `PATCH` on edit. Covered 1:1 by
  `frontend/tests/entity-creation-verb.{feature,spec.js}`.
