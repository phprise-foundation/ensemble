# ADR-003 — Execution targets: local sources vs remote repositories

Status: **Proposed** — not implemented. Decisions pending in
`docs/STATE.md` (“WIP — P2 decisions”).

## Context

The production target is Cloudflare Containers with an external PostgreSQL.
Container disk is ephemeral: a container that sleeps or is replaced starts with
a fresh filesystem. Agents must edit a project and leave a durable result, so
the project cannot live only in the container.

There are two kinds of execution target:

- **Source** — a local directory mounted into the container. Useful for
  development and self-hosted single-container installs; not viable on
  Cloudflare.
- **Repository** — a remote git repository on GitHub, GitLab or Bitbucket.
  The runner clones it into an ephemeral directory, works on a branch and pushes
  the result. This is the production path.

## Proposed decision

1. **One Workdir abstraction.** A workspace-scoped execution target has a
   `kind` (`source` or `repository`). The API exposes `/sources` for local
   folders and `/repositories` for provider connections; an issue references one
   `workdirId`. The runner only sees the resolved workdir.
2. **The issue owns the target.** `Issue.workdirId` replaces any per-agent
   source field. The watcher selects issues by the status roles and resolves the
   issue's workdir before starting the agent.
3. **Local sources** store a path that must exist and be a directory inside the
   container. They are accepted only where a volume is available (dev or
   self-hosted).
4. **Repositories** store `provider`, `url`, `branch` and a `secretId`. The
   clone is shallow and ephemeral:
   - `git clone --depth 1 --branch <base> <url> /tmp/<workspace>/<issue>`;
   - branch `agent/<issue-key>-<run>`;
   - commit and push; open a pull/merge request through the provider adapter;
   - discard the clone; write reports/artifacts to R2.
   The token must not appear in process arguments or logs: use a temporary
   `0600` credential helper or `GIT_ASKPASS`, with
   `GIT_TERMINAL_PROMPT=0`.
5. **Per-workspace secrets.** `workspace_secrets` stores a name, ciphertext,
   nonce and provider metadata; values are encrypted with libsodium
   (`sodium_crypto_secretbox`) using `APP_ENCRYPTION_KEY` from the environment.
   The API returns only names/metadata. Repositories reference a `secretId`.
   The runner decrypts in memory when cloning or deploying.
6. **Provider authentication.** Start with per-workspace fine-grained PATs or
   project access tokens. Keep a `RepositoryProvider` contract so GitHub App
   installation tokens, GitLab OAuth and Bitbucket OAuth can replace PATs
   without changing the runner.
7. **Status ownership.** A status is owned by one or more roles through a
   `status_roles` join table, so the runner can query statuses by role. The role
   keeps only `unblock`/`interactive`.
8. **Executable-stage rule (proposed).** An issue may be created without a
   workdir (ideas/RFCs), but it cannot enter a status with `roleIds` unless it
   has a workdir. The runner skips issues without one and records a visible
   reason.

## Open decisions

1. Unified `Workdir`/`issue.workdirId` versus separate
   `sourceId`/`repositoryId` fields.
2. Whether `workdirId` is required for every task/subtask or only when entering
   an agent-owned status.
3. Whether to implement the encrypted secret vault in P2 or start with one
   environment-level provider token for the first runner slice.
4. Whether to start the frontend cutover before or after P2.

## Consequences

- Cloudflare production uses repositories; local sources are documented as a
  dev/self-hosted feature.
- The agent never stores a source; the issue is the source of truth for the
  execution target.
- Deployment of the source application is a separate step: push a branch/PR and
  let the provider CI deploy, or use a workspace secret for a Cloudflare/GCP
  deploy token. That belongs to P3/P5.
