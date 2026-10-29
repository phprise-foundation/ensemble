---
id: RFC-2026-0002
title: Event-driven agent orchestration with triggers, scheduler and scoped CLI
status: accepted
author: Esdras
createdAt: 2026-10-29 00:30:00
updatedAt: 2026-10-29 00:30:00
tags: [orchestration, trigger, scheduler, queue, cli, permissions]
priority: critical
estimated_effort: 6d
dependencies: [RFC-2026-0001]
---

# Context

The current runner (`bin/runner`) is a single sweep: it matches active agents to
statuses their roles own, prepares the workdir, executes through
`AgentExecutor`, records a run and, on success, advances the issue through a
single agent transition. There is no immediate wake-up, no durable queue, no
manual retry, no CLI and no conversation.

With RFC-2026-0001, agents also need to be woken by ASK/INSTRUCT messages and by
replies to an ASK they authored. The product owner's concern is explicit: never
duplicate a running agent, never spawn a process per agent, and make immediate
actions reliable.

## Position: Option B, with a durable queue

Adopt **event-driven orchestration without watchers** (Option B in spirit), but
with a **central durable trigger/run queue and a scheduler**:

- Triggers are persisted; a central scheduler claims runs with a lease.
- **Single-flight per agent**: at most one active run per agent. New triggers
  wait in the queue; they are never lost.
- **Global concurrency limit** (configurable). No unbounded processes.
- No process per agent and no “sleeping” process: an agent is configuration.
  “Waking” means creating/claiming a run.
- Immediate wake: the API emits `pg_notify('ensemble_triggers', ...)` after a
  trigger is created; the scheduler listens; a short safety poll (15–30 s) and
  the status sweep back it up.
- Ceremonies are standalone runs from a `CEREMONY` trigger (channel + schedule),
  outside the delivery pipeline.
- Option A (one watcher + one queue per agent) is rejected as the primary model:
  it reintroduces process duplication, complicates observability and does not
  match the current runner. “Option B without any queue” is also rejected: it
  loses durability, retry, backpressure and restart safety.

# Proposal

## Trigger

`agent_triggers`:

| Field | Notes |
|---|---|
| `id` | |
| `workspaceId` | |
| `issueId` | nullable for ceremonies |
| `channelId` | nullable |
| `messageId` / `replyId` | nullable; source of MESSAGE/REPLY triggers |
| `type` | `STATUS` \| `MESSAGE` \| `REPLY` \| `CEREMONY` \| `MANUAL` |
| `agentId` | target agent |
| `permissions` | JSON capability set for the run |
| `dedupeKey` | unique; see below |
| `state` | `PENDING` \| `CLAIMED` \| `DONE` \| `FAILED` \| `CANCELLED` |
| `priority` | `HIGH` (message/reply/manual) \| `NORMAL` (status/ceremony) |
| `availableAt` | earliest execution |
| `claimedAt` / `leaseUntil` | lease |
| `attempts` | |
| timestamps | |

### Sources and dedupe

- **STATUS** — the periodic sweep enqueues one trigger per
  `(issue, status entry, agent)`:
  `status:<issueId>:<statusId>:<enteredAt>:<agentId>`.
  Re-entering the status changes `enteredAt`, which re-arms the trigger.
  It is never re-created while the issue stays in the same entry.
- **MESSAGE** — an ASK/INSTRUCT with a mention of an active agent:
  `message:<messageId>:<agentId>`. INFO and closed messages never enqueue.
- **REPLY** — a reply on an ASK whose author is an **agent**:
  `reply:<messageId>:<authorAgentId>`. One pending trigger per message+author:
  the first reply creates it; later replies re-arm it (`availableAt = now`) if
  the previous run is done and the message is still open. This is a
  **notification-run for the author**, not a trigger for the replier: the reply
  itself never mentions or wakes anyone. User authors get an in-app notification
  instead (RFC-2026-0001).
- **MANUAL** — “Run now”/retry; no dedupe (each run is a new trigger, linked to
  the same source).
- **CEREMONY** — `ceremony:<ceremonyId>`; standalone run (phase 2).

- A trigger whose message is closed is cancelled.
- If the target agent is inactive, the trigger is not created (or is cancelled);
  the message/notification remains visible.

## Scheduler and runs

- `runs` gains `triggerId`, `kind` (`DELIVERY` \| `CEREMONY`), `permissions`,
  `parentRunId` (nullable).
- One run per trigger; `(triggerId)` unique. The legacy “succeeded/failed never
  re-runs per agent+issue” is replaced by trigger-based idempotency.
- Claim with lease (30 min), reclaim, timeout, attempts, dead-letter after the
  ceiling.
- **Single-flight per agent**: a partial unique index / advisory lock guarantees
  at most one `CLAIMED`/`RUNNING` run per agent. The scheduler skips a busy
  agent and picks the next eligible trigger; the pending trigger survives.
- Ordering: HIGH before NORMAL, FIFO within a priority; per-agent fairness is a
  consequence of single-flight.
- Pause switch per workspace; the scheduler stops claiming for it.
- Wake-up: `NOTIFY` on insert; the scheduler `LISTEN`s; a 15–30 s safety poll.
  Target: <2 s local between message creation and run start.
- P5: the same trigger table can be drained by cron/queue on Cloudflare; no
  change to the domain contract.

## Run permissions

The capability set is the intersection of the trigger and the target role; an
INSTRUCT can never elevate a role.

| Trigger | Capabilities |
|---|---|
| STATUS | Role flags (and the actions the stage requires). A successful run may advance the issue **only** through an explicit `agent`/`both` transition. |
| MESSAGE / ASK | `reply` only, to the triggering message. The run must post a reply; otherwise it fails with “no reply”. |
| MESSAGE / INSTRUCT | `reply` (optional) + the actions granted by the target role flags (review/merge/deploy/code). Never more than the role and repository/environment policy allow. |
| REPLY | `close` the ASK + the author's role capabilities; never advances the issue status. |
| MANUAL | Same as the source trigger’s permissions (or the role’s for a manual run). |

- The run receives a short-lived, HMAC-signed **run token** (`runId`, `agentId`,
  permissions, expiry); the API validates it on every command.
- Provider tokens (GitHub PAT/App) never reach the agent; the CLI calls the
  product API, and the runner performs the provider call.
- Users never create INSTRUCT (RFC-2026-0001); agents do, via CLI.

## CLI (`ens`)

Agents act only through the CLI, installed in the runner/workdir and
authenticated by `ENSEMBLE_RUN_TOKEN`. No direct HTTP/DB.

| Command | Purpose |
|---|---|
| `ens issue show [--full]` | Compact issue/context; `--full` for details (economy of context) |
| `ens issue messages [--since]` | Thread/message history |
| `ens msg send --type info\|ask\|instruct --mention agent:<id> --content ...` | New message (ASK/INSTRUCT require the mention) |
| `ens reply --message <id> --content ...` | Reply (only when the run has `reply`) |
| `ens msg close --message <id>` | Author closes an ASK |
| `ens pr open\|sync\|review\|merge` | Delivery (RFC-2026-0003) |
| `ens deploy --environment staging\|production` | Delivery (RFC-2026-0003) |
| `ens run show` | Current run metadata/context |

- The prompt injects the compact issue + recent thread + trigger + allowed
  command list. The CLI rejects anything outside the run’s capabilities.
- A **STATUS/INSTRUCT** run whose agent posts no message has its final output
  posted by the runner as an INFO message (author = agent), unless the run is
  ASK. Duplicate auto-posting is avoided by run id.
- An **ASK** run must reply via `ens reply`; the runner verifies a reply exists
  from that agent on that message after execution.
- An **INSTRUCT** run may reply or send a new message; the reply is optional.

## Status sweep

- The periodic sweep no longer executes inline; it enqueues STATUS triggers
  (`status entry` dedupe).
- After a successful STATUS run, the existing `AdvanceIssue` logic applies:
  move only when there is exactly one `agent`/`both` transition from the current
  status; otherwise stay (the user moves it or defines the transition).
- A STATUS trigger is not re-created until the issue leaves and re-enters the
  status (or a new `enteredAt`).
- Mention/REPLY runs never move the issue.

## Reply notification flow

1. A reply is created on an ASK (RFC-2026-0001).
2. If the author is a user: an unread notification is stored; no run.
3. If the author is an agent: a REPLY trigger is created/re-armed with the
   author’s role capabilities + `close`; the run sees the full thread. This is
   the only run caused by a reply, and it exists because the author must
   evaluate and close the ASK; the replier is never triggered.
4. The agent evaluates and either closes the ASK (`ens msg close`), sends a new
   ASK/INSTRUCT, or leaves it open; the 5th reply auto-closes.
5. The run never advances the issue status.

## Guardrails

- One mention per message; replies never mention/trigger.
- At most 5 replies per message; auto-close on the 5th.
- Soft safety budget configurable (e.g. max agent messages per issue per hour)
  for INSTRUCT chains; no hard per-issue product limit.
- No self-mention; only active workspace principals.
- Audit: trigger, permissions, commands, provider actions, outcome.
- Pause per workspace; kill/cancel of a running process is a later slice.

## Ceremonies

A `CEREMONY` trigger references a channel and a schedule; the run is standalone
and does not touch the delivery pipeline. The model supports it from day one;
implementation is phase 2.

## Migration

1. The sweep becomes a trigger producer; the scheduler becomes the executor.
2. Workdir preparation and git publish logic are reused unchanged.
3. `AdvanceIssue` is called only from STATUS runs and only for explicit
   transitions.
4. `runs` gets the new columns; old runs remain readable.
5. The `bin/runner` single pass becomes `bin/scheduler` (loop + wake); a
   `--once` mode keeps tests/debugging simple.

# Acceptance Criteria

- [ ] Trigger persisted, deduplicated and leased; one trigger per status entry /
      message / reply-author; manual/ceremony as specified.
- [ ] Central scheduler with single-flight per agent, global concurrency and
      per-workspace pause.
- [ ] ASK/INSTRUCT wake immediately (<2 s local) via NOTIFY; INFO, closed and
      inactive agents do not wake; the safety poll covers missed notifications.
- [ ] ASK grants only `reply`; no reply by the agent fails the run with a clear
      reason.
- [ ] INSTRUCT capabilities never exceed the target role/repository policy.
- [ ] REPLY triggers only agent authors; the run can close the ASK and never
      advances the status.
- [ ] STATUS runs advance only through an explicit `agent`/`both` transition.
- [ ] `ens` token scoped; forbidden commands fail; no HTTP/DB by the agent.
- [ ] Manual retry/run-now and lease reclaim.
- [ ] `bin/scheduler --once` and the full loop are tested; ceremonies are
      supported by the schema (implementation phase 2).
- [ ] Audit and observability of triggers/runs/commands.

# Impact

- Runner/scheduler, trigger and run tables, message/reply APIs emitting
  triggers, CLI, run token, prompt context, role flags, tests, `bin/runner`
  migration.
- Depends on RFC-2026-0001; enables RFC-2026-0003 (delivery actions) and the
  autonomy gates.

# Alternatives Considered

- **Option A (per-agent watcher + per-agent queue)**: rejected; process
  duplication, poor observability, does not match the current runner.
- **Option B with no queue**: rejected; no durability, retry, backpressure or
  restart safety.
- **Agents using HTTP/DB directly**: rejected; permissions and context cannot be
  controlled; secrets would leak.
- **Reply triggering the replier**: rejected; replies have no mention/trigger.
- **Reply triggering no one**: rejected for agent-authored ASKs; the author must
  evaluate and close. User authors use the notification table.

# References

- Product owner brief and decisions, 2026-10-29.
- RFC-2026-0001 (communication) and RFC-2026-0003 (delivery).
- `docs/ADR-003-execution-targets.md`.
- `ExecuteSweep`, `Run`, `AgentExecutor`, `bin/runner`, `kanban.local` behaviour.
