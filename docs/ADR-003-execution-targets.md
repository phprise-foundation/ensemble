# ADR-003 — Execution targets: an internal workdir bound to a remote repository

Status: **Accepted** — P2b implements the model; the runner work is P3/P5.

## Context

The production target is Cloudflare Containers with an external PostgreSQL.
Container disk is ephemeral: a container that sleeps or is replaced starts with a
fresh filesystem. Agents must edit a project and leave a durable result, so the
project cannot live only in the container.

There are two possible execution targets:

- **Source** — a local directory mounted into the container. Useful for
  development and self-hosted single-container installs; not viable on
  Cloudflare.
- **Repository** — a remote git repository on GitHub, GitLab or Bitbucket. The
  runner clones it into an ephemeral directory, works on a branch and pushes the
  result. This is the production path.

The product owner decided that the product is built around **remote
repositories**. "Source" is not a product resource and has no screen; it remains
a possible dev/self-hosted adapter behind the same internal abstraction.

## Decision

1. **One internal execution target: `Workdir`.** A workspace-scoped `workdir`
   has a `kind`:
   - `scratch` — a directory owned by an issue and shared by its derived issues;
   - `repository` — a checkout of a remote repository on a branch.
   The API never exposes the workdir. The user sees a **repository**.
2. **`issues.workdir_id` is the single persisted reference and is always set.**
   The API exposes `repositoryId` (nullable), derived from the workdir by the
   presenter. There is no second `issues.repository_id` column, so the two views
   cannot diverge.
3. **Creation rules.**
   - with a parent → inherit the parent's workdir (`parentKey` is added in
     P2b);
   - with a repository → a `repository` workdir;
   - otherwise → a new `scratch` workdir owned by the issue.
   All of it happens in the issue-creation transaction.
4. **Binding a repository is a workdir operation reached through an issue:**
   `POST /issues/{key}/repository`. It affects the whole family, because derived
   issues share the workdir. The branch is `agent/<root-issue-key>`, one per
   workdir, so the feature and its subtasks work on the same branch.
5. **Binding state machine:** `scratch → pending → bound | conflict`. P2 stores
   the state and accepts the user's choice (`remote_wins`, `local_wins`,
   `merge`). The P3 runner detects the conflict (git and network) and performs
   the merge; the web container never clones.
6. **A `scratch` workdir is run-scoped.** Its content is not durable until a
   repository is bound, and the UI warns about it. `WorkdirStorage` lands with P3
   (local volume) and R2 with P5.
7. **Per-workspace secrets.** `workspace_secrets` stores a name, ciphertext and
   nonce encrypted with libsodium (`sodium_crypto_secretbox`) using
   `APP_ENCRYPTION_KEY` from the environment, plus provider metadata. The API
   returns only names/metadata; repositories reference a `secretId`. Start with
   per-workspace fine-grained PATs.
8. **Provider authentication.** Keep a `RepositoryProvider` contract so GitHub
   App installation tokens, GitLab OAuth and Bitbucket OAuth can replace PATs
   without changing the runner.
9. **Repositories** store provider, URL, base branch and `secretId`.
10. **Status ownership.** A status is owned by one or more roles through the
    `status_roles` join table, so the runner can query statuses by role. The role
    keeps only `unblock`/`interactive`.
11. **Executable stage rule (for P3).** An issue with an agent-owned status
    runs only when its workdir is bound; otherwise the runner records a visible
    reason and skips it. Creating an issue never requires a repository.

## Consequences

- Cloudflare production uses repositories; local sources are a dev/self-hosted
  adapter, not a product feature.
- The agent never stores a source; the issue references the execution target and
  the runner resolves it.
- A family of issues shares one workdir and one branch; concurrent agents on the
  same workdir need P3 worktrees.
- Deployment is a separate step: push a branch/PR and let the provider CI
  deploy, or use a workspace secret for a Cloudflare/GCP deploy token (P3/P5).
- P2b adds `repositories`, `workspace_secrets`, `workdirs`, `issues.workdir_id`
  and `issues.parent_key`; P2c adds agents without `sourceId`.
