# ADR-002 — Workflow model (issues, boards and transitions)

Status: **Accepted** (P1).

## Decisions

1. **A workflow belongs to a workspace.** It owns its issue types, statuses,
   transitions and fields. A board references exactly one workflow; a board can
   never mix issues from two workflows.
2. **Statuses belong to an issue type.** The status *name* is user-defined, but
   its kind is one of three fixed values: `initial`, `transitional`, `final`.
   Users cannot invent a fourth kind. Each issue type has exactly one initial
   status; final statuses are free.
3. **Transitions never change the issue type.** `from` and `to` must belong to
   the same issue type. An RFC remains an RFC after approval. Creating tasks
   from an approved RFC is a **user-configured trigger** (P3), not a
   transition, and the cardinality is not fixed: RFC → tasks is not 1:1 or 1:N.
4. **Issues belong to the workspace, not to a board.** An issue has `typeId` and
   `statusId`; it has no `boardId`. A board displays the issues whose status is
   in one of its columns and whose issue type passes the board filters. The same
   issue can therefore appear in several boards.
5. **Issue keys are workspace-scoped.** The workspace owns a prefix (`KAN`,
   `KANB`, …); a new issue gets `PREFIX-N` from a per-workspace sequence.
   Changing the prefix later does not rewrite existing keys.
6. **Custom fields are defined per issue type.** The v1 field types are:
   `text`, `textarea`, `number`, `date`, `boolean`, `single_select` and
   `multi_select`. Single/multi select options are stored with the definition.
   `summary` and `description` are system fields on the issue, not custom field
   definitions.
7. **Identifiers:** configuration entities have an opaque UUID `id` and a
   human-readable `key` slug (unique in its scope). Issues use the `PREFIX-N`
   key in URLs. Clients should treat `id` and `key` as immutable.
8. **Permissions:** workspace `owner`/global admin can create, edit and delete
   configuration; any member can read it. Tenant-scoped lookups always include
   the workspace, so an id from another workspace behaves as not found.
9. **HTTP:** nested under `/api/workspaces/{workspace}`; `GET`, `POST`, `PATCH`,
   `DELETE`; no `PUT`.

## P1 slices

- **P1a:** workflows, issue types and field definitions.
- **P1b:** statuses and transitions.
- **P1c:** boards and columns.
- **P1d:** issues (key sequence, create/list/move/comments/history).

Statuses and transitions are documented here but implemented in P1b so each
slice can be tested and committed on its own.
