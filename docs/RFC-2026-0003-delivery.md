---
id: RFC-2026-0003
title: Delivery capabilities for agents — PR review, merge and deploy under policy
status: draft
author: Esdras
createdAt: 2026-10-29 00:30:00
updatedAt: 2026-10-29 00:30:00
tags: [delivery, pull-request, merge, deploy, github, policy]
priority: high
estimated_effort: 5d
dependencies: [RFC-2026-0001, RFC-2026-0002]
---

# Context

The runner already clones a repository, creates `agent/<issue-key>`, commits and
pushes the branch. It cannot open a PR, review it, merge it or deploy, and the
UI has no repository credentials. The product target is full autonomy: agents
deliver, review, merge and deploy like employees.

The communication/orchestration RFCs provide the channel, the message types and
the scoped CLI. This RFC defines the privileged delivery actions, their
credentials and policies.

Decisions already taken:

- Merge/deploy are role permissions (`merge`, `deploy`) surfaced as toggles in
  the role editor (RFC-2026-0001).
- Users never create INSTRUCT; action requests come from agents, so a user
  cannot instruct a deploy by text.
- Board and PR comments coexist with equal Markdown content (≤1023 chars).
- Merge is manual by policy at first; full autonomy is the target, with
  guardrails.

Remaining decisions are listed at the end.

# Proposal

## Capabilities

| Capability | Typical role | Command |
|---|---|---|
| `pr.open` | Coder | runner post-step after a successful push (or `ens pr sync`) |
| `pr.review` | Tech Lead / QA | `ens pr review --approve\|--request-changes -m "..."` |
| `pr.merge` | Tech Lead | `ens pr merge` |
| `deploy.staging` | QA / Deployer | `ens deploy --environment staging` |
| `deploy.production` | Deployer / Tech Lead | `ens deploy --environment production` |
| `rollback` | Deployer | `ens deploy rollback --environment ...` |

- A capability is granted only when the role flag is on **and** the repository/
  environment policy allows it.
- An INSTRUCT cannot grant more than the target role (RFC-2026-0002).
- The CLI validates the run token; the runner performs the provider call with
  the workspace token. The agent never sees the GitHub token.

## Credentials

- **GitHub first**; the provider field already exists (`github`, `gitlab`,
  `bitbucket`) but the authentication path is GitHub-specific today.
- **PAT (fine-grained)** as the first implementation:
  - repository access, `contents: write`, `pull_requests: write`,
    `actions: write` for deploy, `metadata: read`;
  - stored as a workspace secret, selected on the repository, encrypted with
    `APP_ENCRYPTION_KEY`; never returned by the API.
- **GitHub App** recommended before production: installation tokens with the
  same scopes, short-lived, organisation-level; the `RepositoryProvider`
  contract from ADR-003 becomes real.
- **Secrets UI**: create/rotate/delete; select on the repository; “test
  connection” (clone dry-run).
- **Deploy secrets** live in GitHub environment secrets; the product triggers
  the workflow, it does not hold the deploy credentials.
- Private repositories work as soon as the secret is attached to the repository
  and the issue is bound to it.

## Repository policy

Per repository:

- provider, https URL, base branch, secret;
- merge method (`merge` \| `squash` \| `rebase`);
- required checks (list) and required approvals (number);
- `auto_merge` (off by default; on means the runner may merge when the policy
  passes);
- deploy workflow per environment, environment names, production approval mode
  (human first, auto later);
- mirror policy (all messages vs. only ASK/INSTRUCT/reviews).

Policy is enforced by the runner, never by the model. Branch protection is never
bypassed; a denied merge/deploy is a visible failure.

## Issue data

- Environment target and acceptance criteria: custom fields on the issue type
  (already supported) with conventions documented per workspace.
- The repository binding remains `issues.workdir_id` / `repositoryId` (derived).
- Deploy-specific fields (version, URL, rollback target) are artefacts of the
  run, not new issue columns.

## PR lifecycle

1. A successful coder run pushes `agent/<key>`. If the repository policy allows,
   the runner opens a PR (base = repository base branch, head = agent branch,
   title/body from the issue).
2. The PR URL/number is stored in the run and shown in the issue panel.
3. Review: `ens pr review --approve|--request-changes -m "..."` posts a formal
   PR review and mirrors the same Markdown as a board message/reply.
4. Merge: `ens pr merge` checks PR open, checks green, approvals ≥ policy, no
   conflict, branch up to date, merge method allowed; then merges and records
   the merge SHA.
5. If the policy denies, the run fails with the reason; the branch is preserved.

## Deploy lifecycle

1. `ens deploy --environment staging|production` resolves the configured
   workflow and environment.
2. The runner dispatches `workflow_dispatch` with the target ref/inputs.
3. It polls the run and records status, URL, version and duration.
4. Production follows the environment policy (human approval initially; auto
   later).
5. Health failure or deploy failure triggers `rollback` (last good version) and
   returns the issue to the previous stage with the reason (no automatic status
   change: only an explicit transition can move it).

## Board ↔ PR mirror

- Every message/reply in the issue channel is mirrored to the linked PR as a
  comment with the **same content**; formal reviews use the same body.
- A PR comment is not a new message type; the board is the source of truth.
- External identifiers are stored in `message.externalRefs`
  (`provider`, `kind`, `id`, `url`).
- Failures to mirror do not lose the board message; they are retried and shown.
- The mirror can be toggled per repository to avoid noise.

## Failure, retry and audit

- PR/merge/deploy failures mark the run `failed` with a reason; the branch,
  artefacts and logs remain; a manual retry is available.
- A deploy that started and failed triggers rollback; a failed rollback is a
  critical alert.
- Every privileged action is audited: actor (agent), run, trigger, PR, checks
  snapshot, merge SHA, workflow run, environment, approver.
- Pause per workspace stops delivery actions; running processes are not killed
  in the first slice (cancel is a later slice).

## Non-goals

- Merge/deploy for users (they use GitHub directly).
- GitLab/Bitbucket adapters (the `RepositoryProvider` contract stays ready).
- Webhook ingestion, issue sync, PR comments written outside the board.
- Automatic rollback without policy.
- Console/copilot/design.

# Acceptance Criteria

- [ ] Secrets/repository UI with provider, URL, base branch, secret and “test
      connection”; private repository works.
- [ ] Issue can be bound to a repository (UI) and runs are visible; PR opened
      after a successful push.
- [ ] `ens pr review` approves/requests changes and mirrors the same Markdown to
      the board and the PR.
- [ ] `ens pr merge` enforces policy (checks, approvals, conflict, method) and
      records the merge SHA; denial is a failed run with a reason.
- [ ] `ens deploy` dispatches the configured workflow, records status/URL/
      version, honours production policy and rolls back on failure.
- [ ] Role flags `review`/`merge`/`deploy` gate every action; INSTRUCT cannot
      elevate.
- [ ] PAT scopes documented; connector test; tokens never exposed to agents.
- [ ] Audit trail for all privileged actions.
- [ ] CI uses fake provider clients; a manual smoke uses a real private GitHub
      repository.

# Impact

- Provider client (`RepositoryProvider`), repository/environment settings,
  secrets UI, PR/deploy artefacts, runs UI, CLI commands, policies, audit,
  tests, docs/quickstart.
- Depends on RFC-2026-0001/0002 and the earlier foundation (secrets/repo UI,
  issue binding, runs panel, scheduler loop).
- Enables the autonomy gate and P6 dogfooding.

# Alternatives Considered

- **Push only; PR/merge/deploy manual**: rejected by the autonomy goal; still
  the fallback if policy blocks.
- **One global PAT**: rejected; tenant isolation and least privilege.
- **Deploy by arbitrary command**: rejected; workflow + environment secrets +
  policy is auditable and reversible.
- **Agent calls GitHub directly**: rejected; token exposure and no policy.
- **GitLab/Bitbucket now**: deferred; contract stays provider-agnostic.

# Open decisions

1. **GitHub App vs PAT** for the first delivery slice ([recommendation] PAT to
   ship, App before production).
2. **Deploy flag**: one `deploy` flag + environment policy (recommended) vs
   separate `deploy_staging`/`deploy_production`.
3. **Mirror scope**: all messages or only ASK/INSTRUCT/reviews (recommended all,
   with a per-repository toggle).
4. **Production approval**: human approval first, then automatic after a
   confidence period (recommended).
5. **Auto-merge**: off by default, per repository (recommended).

# References

- RFC-2026-0001 (communication) and RFC-2026-0002 (orchestration).
- `docs/ADR-003-execution-targets.md`.
- Current `GitWorkdirVcs`, `GitClient`, `repositories`, `workspace_secrets`.
- GitHub REST API (pull requests, reviews, merges, workflow dispatch).
