# RFC — Model performance measurement & pipeline triggers

Status: **Formalised** (all decisions taken in §10; implementation not started).
The implementation order is §11.

Goal: measure how well each **model** performs, segmented so we never compare
apples with oranges, and expose it in the **monitor**. Two headline metrics:
**M1** quality without rework and **M2** average cost per accepted task.

The measurement depends on a board change: transitions move from the agents to
the **pipeline**, expressed as **triggers**, with a `ready` flag. This document
records both the pipeline design and the measurement design because they are
coupled.

---

## 1. Non-negotiable context

- The board is **dynamic**: issue types, roles, statuses and transition rules are
  user-defined (soon persisted in the database). Therefore:
  - **Roles stay pure** — no status or transition names inside role definitions.
  - Metrics read a **semantic layer** (annotations), never hard-coded status
    strings. See §9.
- The flow is **forward-only across issue types** (`idea → rfc → task →
  subtask`). Rework is expressed as **block/unblock + infractions**, not backward
  transitions. Exception: the `Coding ↔ Reviewing` convergence loop (see §6).

## 2. Segmentation (avoid apples vs oranges)

- **Primary cohort:** role / stage.
- **Secondary cohort:** issue type.
- Compare **models within the same cohort**. Never collapse everything into a
  single "model score".
- Normalizers / covariates: task size and complexity, the **model actually used**
  (fallback), number of attempts, diff size.
- Group by `provider/model#variant`. Exclude or flag runs that used the
  **fallback model** (otherwise the "model" is a mixture).
- Report `n`, median, p90 and a confidence interval. **No minimum sample and no
  ranking gate:** show the data from the first run and let precision grow with
  `n`; the interval makes the small-sample uncertainty explicit.

## 3. Roles and responsibilities

| Role | Responsibility | Notes |
|---|---|---|
| Product Owner | Reduce user uncertainty; owns the **problem**, not the solution; authors the RFC **description** (extracted from the idea) | Asking the user questions is legitimate and **never penalised** |
| Solutions Architect | Authors the **solution proposal** on the RFC | Block + infraction when the idea is still ambiguous |
| Systems Architect | Integration / technical feasibility | No penalty at this stage (baton pass) |
| Framework Architect | Technical requirements, stack, least-complexity path | No penalty at this stage (baton pass) |
| Tech Lead | **Supreme authority**: approves, comments, unblocks. Does **not** author artifacts | Neutral judge; **never an infractor** |
| Coder | Implements subtasks; owns unit/integration tests (dev responsibility) | |
| Quality Assurance | Executes acceptance; owns the executable harness + **exploratory testing** | |

The RFC has **no single author**: the PO authors the **description** (extracted
from the idea), Solutions the **solution proposal**, Systems the **feasibility**,
and Framework the **execution plan** (with the task descriptions). All four are
stakeholders and all vote. The RFC itself is **spawned by a pipeline trigger**
(§4.4), never created by an agent.

Design consequence: because the Tech Lead authors nothing, he genuinely cannot be
an infractor — neutrality is real. Trade-off: his **own** quality is not
measurable (only cost, latency and decision rates).

## 4. Pipeline via triggers

Transitions are no longer an agent concern. Agents produce artifacts and set
flags; the engine moves the item.

- On success, the agent sets **`ready = true`**.
- On failure, the agent sets **`blocked = true`** and records an **infraction**.
- A **trigger** matches an event plus conditions and applies actions
  (set status/role/worker/ready, spawn items, emit events).

### 4.1 Trigger schema (refined)

The original draft mixed *schema* and *instance*. Keep a JSON Schema for triggers
and write clean instances:

```yaml
id: TRG-1
name: transition to discovered
enabled: true
priority: 10            # order when several triggers match the same event
once_per_item: true     # idempotency / loop protection
event_type: item_transitioned
when:                   # AND by default; supports all / any groups
  all:
    - {field: status,  op: eq, value: discovering}
    - {field: ready,   op: eq, value: true}
    - {field: blocked, op: eq, value: false}
then:                   # applied in order, atomically
  - {op: set, field: status, value: discovered}
  - {op: set, field: ready,  value: false}
  - {op: set, field: role,   value: solutions-architect}
  - {op: set, field: worker, value: ''}
  - {op: emit, event: item_transitioned}
```

Improvements over the draft:

- **Operators and aggregates.** `field=value` is not enough for voting or for
  "all artifacts present". Support `op` (`eq, ne, in, exists, gte, lt, changed`)
  and aggregates such as `count(votes where value = approve) >= 3`.
- **Typed actions:** `set / clear / append / spawn / emit / notify`.
- **Event sourcing + loop protection.** Every firing writes an event; setting
  `ready = false` prevents immediate re-entry, and a max cascade depth guards
  against cycles.
- **Index, do not scan.** The runner indexes triggers by `(event_type, status)`;
  scanning every item against every trigger does not scale.
- **Transaction / optimistic lock.** Several triggers may match one event; apply
  `then` atomically and re-validate `when` before writing (the board already has
  the `worker` lock).
- **Event types:** add `item_ready_changed`, `item_vote_cast`, `item_spawned`,
  `subtask_completed` (carries `parent_id`).
- **Comments and infractions live in separate tables** (so a comment does not
  bump `item.updated_at`); expose `comment_count` / `last_comment_at` on the item
  so triggers do not need a join.

### 4.2 `ready` and per-artifact `steps`

A single boolean `ready` is fine for **single-writer** stages (`Coding`,
`Reviewing`) but not for **multi-author** stages (`Discussing` has 3 authors,
`Refining` has 4): one author could "stamp" the others' work. Use per-artifact
completion and let the trigger fire when the required set is complete:

```yaml
steps:
  proposed_solution: done
  feasibility:       done
  plan:              pending
ready: false           # derived / optional
```

### 4.3 Field semantics

- `assignee` = attribution (who *should* do it).
- `worker` / `worker_at` = claim / lock (who holds it right now).
- Triggers change `role` and clear `worker`; `assignee` is derivable.

### 4.4 Automatic triggers (no agent)

Some transitions are decided by the engine from counters, never by an agent.
This removes the possibility of self-arbitration.

Voting (4 voters: 3 architects + PO; majority = 3 approvals):

```yaml
id: TRG-vote-approved
event_type: item_vote_cast
when:
  all:
    - {field: status,        op: eq,  value: voting}
    - {field: votes.approve, op: gte, value: 3}
then:
  - {op: set,  field: status, value: approved}
  - {op: emit, event: item_transitioned}
```

```yaml
id: TRG-vote-rejected
event_type: item_vote_cast
when:
  all:
    - {field: status,       op: eq,  value: voting}
    - {field: votes.reject, op: gte, value: 2}
then:
  - {op: set,  field: status, value: rejected}
  - {op: emit, event: item_transitioned}
```

`2 rejected` is terminal because the remaining votes can no longer reach the
3-approval majority.

RFC creation (idea → rfc), spawned by the engine, never by an agent:

```yaml
id: TRG-spawn-rfc
event_type: item_ready_changed
when:
  all:
    - {field: issue_type, op: eq,  value: idea}
    - {field: status,     op: eq,  value: discovered}
    - {field: ready,      op: eq,  value: true}
then:
  - {op: spawn, type: rfc, parent: <idea-id>, description: <from idea>}
  - {op: set,   field: ready,  value: false}
  - {op: emit,  event: item_spawned}
```

The RFC `description` is copied from the idea (authored by the PO). Solutions
sets `ready` on the discovered idea (a go/no-go before an RFC is spawned).

All subtasks done (task-level acceptance):

```yaml
id: TRG-subtasks-done
event_type: subtask_completed
when:
  all:
    - {field: issue_type,     op: eq, value: task}
    - {field: status,         op: eq, value: refined}
    - {field: total_subtasks, op: gte, value: 1}
    - {field: open_subtasks,  op: eq, value: 0}
then:
  - {op: set,  field: status, value: testing}
  - {op: set,  field: role,   value: quality-assurance}
  - {op: set,  field: worker, value: ''}
  - {op: emit, event: item_transitioned}
```

## 5. Flow (steps)

`(r)` = the step sets `ready = true`; `(b)` = the step sets `blocked = true`.

| Step | Actor | Issue type | Status | When success | When bad |
|---|---|---|---|---|---|
| Understanding | PO | idea | Discovering | add description `(r)` | add comment |
| RFC gate | Solutions Architect | idea | Discovered | set `ready` (go) | block `(b)` (no-go) |
| Solution Design | Solutions Architect | rfc | Discussing | add proposed_solution `(r)` | add infraction `(b)` |
| Feasibility | Systems Architect | rfc | Discussing | add feasibility `(r)` | add infraction `(b)` |
| Implementation Plan | Framework Architect | rfc | Discussing | add plan `(r)` | add infraction `(b)` |
| Voting | Architects + PO | rfc | Voting | add approve | add reprove |
| Acceptance Criteria + Tests | PO | task | Refining | add test_routine_bdd `(r)` | add infraction `(b)` |
| Boundary Tests Description | Solutions Architect | task | Refining | add boundary_tests `(r)` | add infraction `(b)` |
| Load Tests Description | Systems Architect | task | Refining | add load_tests `(r)` | add infraction `(b)` |
| Add Subtasks | Framework Architect | task | Refining | add subtasks `(r)` | add infraction `(b)` |
| **Refinement validation** | **Tech Lead** | task | Refining | validate `(r)` | add infraction `(b)` |
| Development | Coder | subtask | Coding | add pr `(r)` | add infraction `(b)` |
| Review | Tech Lead | subtask | Reviewing | merge pr `(r)` | add infraction → back to Coding |
| Testing | QA | **task** | Testing | add comment `(r)` | add infraction `(b)` |
| Unblocked items | Tech Lead | any | any | unblock | — |

Decisions embedded here:

- **Voting:** the **Tech Lead is removed** from voting so he stays impartial to
  judge. Voters are the **3 architects + the PO (4 voters)**; the RFC has no
  single author — the PO authors the description, Solutions the proposal, Systems
  the feasibility and Framework the plan — so **all four are stakeholders and
  vote**. A proposal passes with a **majority of 3 approvals**; it is rejected at
  **2 rejects**. Both outcomes are **automatic triggers** (§4.4), never an agent
  decision. There is **no abstention**: each of the 4 voters chooses approve or
  reject.
- **Refinement gets a validation step by the Tech Lead** (a correctness gate, not
  just a completeness check) before the items move on.
- **Testing is per task**, not per subtask — value is delivered at the task
  level. This requires adjusting the issue-type statuses (see §6).
- **`Refined` is not ready for Testing.** `Refined` means the task is ready to
  spawn subtasks; `Testing` starts only after **all subtasks are `Done`**. The
  move `Refined → Testing` is an automatic, agent-free trigger (§4.4).
- **QA** gains an executable harness and **exploratory testing** autonomy.
- **RFC authorship:** the RFC is **spawned by a trigger** when the idea is
  `discovered` and ready (§4.4); Solutions sets that `ready` on the idea (a
  go/no-go). The PO authors the description, Solutions the proposal, Systems the
  feasibility and Framework the execution plan.
- `Discussing` and `Refining` are **sequential within the same status**, using
  `role` to indicate whose turn it is (status does not change between authors).

### 5.1 Rejected proposals

- At most **3 proposals per idea** (`max_proposals_per_idea = 3`).
- Rejecting a proposal is a **neutral business outcome**, never an infraction.
- `reject_reason` taxonomy:

| Value | Meaning |
|---|---|
| `out_of_scope` | Does not serve the product or the stated problem. |
| `infeasible` | Technically or operationally impossible, or disproportionate. |
| `cost_benefit` | Feasible, but the cost outweighs the delivered value. |
| `risk` | Unacceptable security, compliance or operational risk. |
| `duplicate` | Already covered by an existing idea or RFC. |
| `unclear` | Problem or requirements are not understood well enough. |
| `deferred` | Valid, but intentionally postponed (timing/capacity). |
| `alternative` | Another proposal already covers the need better. |

## 6. Issue-type statuses must change

Because Testing moves to the **task** level, the `task` and `subtask` status sets
must be revised (the current local board runs `subtask: Coding → Reviewing →
Testing → Done`). Decision:

- `task`: `Refining → Refined → Testing → Done` (acceptance at task level).
  - `Refined` means **ready to spawn subtasks**, not "work finished".
  - `Refined → Testing` is **automatic and agent-free**: the trigger
    `TRG-subtasks-done` fires when the last subtask reaches `Done`
    (`total_subtasks >= 1` and `open_subtasks == 0`, §4.4). The
    `total_subtasks >= 1` guard prevents a task with no subtasks from skipping
    acceptance.
- `subtask`: `Coding → Reviewing → Done` (dev work; unit/integration tests live
  here, authored by the Coder; the `Coding ↔ Reviewing` loop is the only
  backward move and is the rework signal).

The task stays in `Refined` while any subtask is open, which makes the board show
exactly where acceptance is waiting.

## 7. Infractions

A first-class entity (separate table), not a free comment. Fields:

`id, infractor (agent:role:model), detected_by, adjudicated_by, infraction
(category enum), cause (requirement|design|bug|test-gap|scope|feasibility),
severity (minor|major|blocking), weight_applied, policy_version, evidence,
blocked (bool), status (declared|upheld|dismissed), created_at, resolved_at`.

Governance rules:

1. Only **`upheld`** infractions count.
2. **No self-** and **no reciprocal** infractions.
3. **Evidence required** (comment / artifact / run).
4. **Append-only** (audit); corrections are new entries.
5. The `infractor` is **resolved from the run ledger** (`agent` + `role` +
   **actual model at the time**), never typed by hand.
6. `adjudicated_by ∉ {infractor, detected_by}`.
7. **Root cause vs proximate:** the `infractor` is the proximate producer of the
   artifact; `cause` / `upstream` allows redirecting responsibility (the Tech
   Lead may attribute to Solutions, Systems or Framework by root cause).
8. Decision taken: a **test failure attributes to the Coder** (the Tech Lead is
   the judge and never an infractor). Record `cause` anyway for diagnostics.

**Weights:** a small, stable severity scale plus an impact multiplier
(`weight_base × f(#items blocked downstream, block time)`). Store
`severity`, `weight_applied` and `policy_version` at adjudication time so history
is reproducible. Always show raw counts alongside the weighted score.

## 8. Metrics

### M1 — quality without rework

Forward-only flow means "quality" is about **defects discovered downstream**, not
about items returning.

> **Defect Attribution Rate** = items produced by a role/model that received
> **≥ 1 upheld infraction** downstream ÷ items produced.

Secondary: **Block Rate** (downstream items blocked with an infraction attributed
to R ÷ items produced by R) and **Cancellation Rate**.

Caveats: who produces ≠ who detects; defects surface late, so record
`detected_at`. **No maturation window is applied:** every item counts as soon as
it exists; the confidence interval absorbs the small-sample noise while the
volume of data grows.

### M2 — cost

> **Cost per accepted task** = Σ cost (task + its subtasks) ÷ accepted tasks.

The model-comparison headline is the **per-model contribution per accepted
task**: for each task, which models contributed and at what cost, alongside the
task total (see the allocation policy below). Also report **cost per attempt**,
**median + p90** (cost is skewed), **tokens per artifact**, and keep
**infrastructure cost** (retries / fallback / provider errors) separate from
work cost.

Data source: `opencode.db` `session` table — `cost`, `tokens_input`,
`tokens_output`, `tokens_reasoning`, `tokens_cache_read`, `tokens_cache_write`,
`model`. Verified available: 262 sessions, 225 with cost, ≈ US$ 16.48 total.

### Unit of account

The **accepted task** is the unit of value (a subtask is an internal step).
Therefore:

- Coder cost is **rolled up** (subtasks → task).
- PO / Solutions / Framework costs are **diluted** across the accepted tasks they
  enabled (via the lineage graph).
- Acceptance tests run at **task** level (QA); unit/integration tests at
  **subtask** level (dev).

#### Allocation policy (decided)

The lineage is a **DAG** (`idea → N rfc → N task → N subtask`), so charging the
full cost of an upstream item to every descendant double-counts it. Decision:
**equal split** — divide each upstream item's cost by the number of accepted
tasks it (transitively) enabled. Simple, stable and auditable; the raw cost per
role stays next to the allocated cost so the split is always verifiable.
(Weighted by estimate/tokens was considered and deferred.)

Report cost on **three levels**:

1. **Per task, per model** — the primary view for model comparison: for each
   accepted task, the cost and effort each `provider/model#variant` contributed
   (its share of that task and its subtasks).
2. **Per task (total)** — the sum across models, so each task has a single cost.
3. **Per role (own level)** — what the role actually spent, before allocation.

Levels 1 and 2 drive M2; level 3 is diagnostic.

**Fan-out ratios** are diagnostic signals, not noise: too much fan-out =
inflated decomposition (cost); too little = under-specification (defects
downstream).

### Guardrails (avoid Goodhart)

Pair quality with cost; add escaped-defect, revert and coverage signals. A model
must not "win" by producing less or by testing less.

### Per-role diagnostic metrics

These explain M1; they are not competing headline metrics.

| Role | Observable metric |
|---|---|
| PO | Discovery bounce (ideas returned for ambiguity); % of RFCs approved without reopening; acceptance-criteria density |
| Solutions | RFC approved on the first vote; design-caused rework/blocked items in Coding; complexity generated (# subtasks, contracts/files per feature) |
| Systems | Integration/load causes attributed downstream; feasibility accuracy |
| Framework | Clarity of the plan (rework caused by plan); decomposition quality (fan-out sanity) |
| Coder | First-pass through Review+Test; requirement coverage; iterations per subtask; tokens per subtask |
| QA | Scenario coverage; defects found before Done; escape rate after Done |

## 9. Enablers

### 9.1 Runs ledger

One row per invocation, the join key between the board and cost:

`run_id, session_id, agent, role, issue_id, from_column, to_column, outcome,
model_configured, model_used, variant, fallback_used, started_at, ended_at,
duration_s, tokens_input/output/reasoning/cache_read/cache_write, cost_usd,
retries, error, workdir, commit_before/after`.

Implemented (agent-watcher, board-driven `watcher.run_agent`): the run uses
`opencode run --format json`, captures the `sessionID`, and writes one JSON line
per invocation to the runs ledger (`<workdir>/.agent-watcher/runs.jsonl`, or
`--runs-file`). Tokens and cost are still joined from `opencode.db` on
`session_id` (not stored in the row).

### 9.2 Semantic layer

Because the board is user-defined, columns/rules need annotations that metrics
read: `kind` (sourcing | gate | work), `role`, `terminal` (approved | cancelled),
`decision` (single | collective), `voter_set`, `spawns`, `produces`.

## 10. Decisions

1. **Monitor scope:** the monitor stays at `var/kanban.local/bin/monitor`; only
   its displayed content changes (no board UI for now).
2. **Voting:** 3 architects + PO vote (all stakeholders of a multi-author RFC);
   majority = 3 approvals; 2 rejects is terminal; both transitions are automatic
   triggers; **no abstention** (each voter chooses approve or reject). The RFC
   author (Solutions) votes.
3. **Arbitration:** not needed. The Tech Lead is not a party (the interest belongs
   to the PO and the architects), and the automatic triggers remove
   self-arbitration.
4. **Maturation window / minimum sample:** none. Data is monitored continuously
   from the first run, with `n` and the confidence interval shown.
5. **Rejected proposals:** limit of 3 per idea; `reject_reason` taxonomy in §5.1;
   rejection is neutral, never an infraction.
6. **Allocation policy:** equal split; cost is reported per task **and per model**
   (see §8).
7. **RFC creation:** spawned by a pipeline trigger, never created by an agent.
   **Solutions sets `ready` on the discovered idea** (a go/no-go before the RFC
   is spawned); the PO authors only the description.

Migration note: the current `roles/product-owner.md` has the PO creating the RFC
directly (`kanban create`); under the trigger model this becomes the engine's
`spawn` action, so that role file will need updating when the trigger model
lands.

## 11. Next steps

**Next task: step 3 (infractions + adjudication)** — steps 4–6 depend on it.

1. Formalise this as an **RFC** issue on the board.
2. ~~Instrument the runs ledger~~ **Done** (agent-watcher `watcher.run_agent` →
   `opencode run --format json` + `sessionID`; agent-watcher PRs #3 and #4).
3. Implement **infractions + adjudication**.
4. Normalise **lineage / spawn** (RFC → tasks → subtasks).
5. Implement the **triggers**, including the automatic voting and
   `subtasks-done` transitions.
6. Build the **semantic layer + metrics read model** (per-task and per-model
   cost) and update `var/kanban.local/bin/monitor`.
