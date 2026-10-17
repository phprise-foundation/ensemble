# Changelog

Notable user-facing changes of `multi-agent`. Versions follow SemVer while the
API is in `0.y.z` (development); see `docs/GITFLOW.md` for the tagging and
release policy.

History before this file existed lives in the GitHub releases:
https://github.com/phprise-foundation/multi-agent/releases

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
