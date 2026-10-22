# ADR-001 — Tenancy, PostgreSQL and Doctrine

Status: **Accepted** (P0 foundation, branch `feat/p0-tenancy-api`).

## Context

The board is becoming a multi-tenant product: a user can belong to one or more
workspaces and every configuration (boards, workflows, statuses, transitions,
issue types, fields, teams, agents, roles, profiles, sources) lives inside a
workspace. Workspaces are fully isolated: no data is shared between them.
Production will run on Cloudflare Containers with an external database, so the
local SQLite file cannot be the production store.

## Decisions

1. **PostgreSQL is the database.** Doctrine DBAL 4 and ORM 3 manage the schema
   and persistence. The application does not execute hand-written SQL; queries
   use the EntityManager, QueryBuilder or DQL.
2. **Doctrine stays in Infrastructure.** Domain entities and value objects do
   not carry Doctrine attributes. `Infrastructure/Persistence/Doctrine/Entity`
   holds the mapped records and `Mapper` translates them to/from the domain.
3. **Workspace is the tenant boundary.** Every future aggregate carries a
   `workspace_id`; repositories are always called with the workspace context.
   Access is checked by `WorkspaceGuard`; non-members receive `404` so the
   existence of another workspace is not leaked.
4. **Users and workspaces are N:N** through `workspace_members`
   (`owner`/`member`). A global admin flag is kept for the master account.
5. **Authentication is user + password.** Passwords use Argon2id; sessions are
   opaque random tokens stored as SHA-256 hashes with a 30-day expiry. The
   master admin is bootstrapped from environment variables only when the
   database has no users; the admin then creates other users through the API.
   Invite-by-email, password reset and multi-factor login are out of scope for
   now.
6. **HTTP contract:** `GET`, `POST`, `PATCH`, `DELETE`; no `PUT`. Errors are
   `{ "error": "...", "field": "..." }` with `400/401/403/404/409/422`.
   Tenant-scoped resources will be nested under
   `/api/workspaces/{workspace}`.
7. **No Laravel.** Symfony components are used only when a concrete need
   appears; today `symfony/cache` is required by Doctrine's setup.
8. **Redis + Doctrine Level 2 Cache is a later step.** The Doctrine
   configuration is the place to add the PSR-6 cache pool; no Redis dependency
   is added now.
9. **Legacy surfaces are temporary.** The old SQLite board, agent-watcher
   runner, static UI and fake frontend remain only until the corresponding
   productization phase replaces them. New code must not depend on them.

## Consequences

- A local PostgreSQL is required for development (`docker compose -f
  docker-compose.dev.yml up -d`, then `bin/dev-up`). Compose is dev-only.
- Schema changes currently use Doctrine's `SchemaTool` through `bin/install`.
  Production migrations will use `doctrine/migrations` before deployment.
- Doctrine entities are infrastructure records, not domain entities; the
  mapping layer is intentionally explicit.
- The old SQLite schema and repositories are not migrated. They will be removed
  when the workflow/board API (P1) and the frontend cutover (P4) land.

## P0 API (first slice)

```
POST /api/auth/login
POST /api/auth/logout
GET  /api/me

GET/POST /api/users              (admin only)
GET/POST /api/workspaces
GET/PATCH /api/workspaces/{id}
GET/POST /api/workspaces/{id}/members
DELETE   /api/workspaces/{id}/members/{userId}
```

The next slice (P1) adds the workspace-scoped workflow chain: boards, columns,
statuses, transitions, issue types and fields.
