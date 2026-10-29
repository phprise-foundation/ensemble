---
id: RFC-2026-0001
title: Channels, Messages, Replies and Mentions for mixed user/agent communication
status: accepted
author: Esdras
createdAt: 2026-10-29 00:30:00
updatedAt: 2026-10-29 00:30:00
tags: [channel, message, reply, mention, communication]
priority: critical
estimated_effort: 5d
dependencies: []
---

# Context

The board conversation is a flat list of comments (`IssueComment`): no typed
author, no replies, no structured mention, no delivery semantics. Agents are
triggered only by the status they own; a review conversation, a question to an
agent or a hand-off between two agents has no first-class representation.

The product needs one communication fabric for mixed user/agent work, official
on the board and mirrored to the PR, that is also the source of agent triggers.
It must support future general channels and ceremonies without reworking the
model later.

Decisions taken with the product owner on 2026-10-29:

- Any active agent **or user** of the workspace can be mentioned.
- One structured mention per message; the mention is stored in its own hidden
  field and rendered as a highlight. Other `@` in the content are plain text.
- Replies never carry a mention and never trigger an agent.
- A message authored by a **user** with a mention is automatically an **ASK**;
  without a mention it is **INFO**. A user can never create an **INSTRUCT**.
- A message authored by an **agent** (via CLI) chooses its type freely:
  INFO, ASK or INSTRUCT.
- The author of an ASK must be notified of every reply, evaluate it and close
  the message (`closed = true`).
- Board and PR comments coexist with the same Markdown content (≤1023 chars);
  the PR mirror is designed in RFC-2026-0003.
- Merge/deploy permissions live on the role as on/off flags (RFC-2026-0003).

# Proposal

## Domain model

### Channel

| Field | Notes |
|---|---|
| `id` | 32-hex id |
| `workspaceId` | tenant |
| `issueId` | null for workspace channels; set for the automatic issue channel |
| `name` | unique per workspace; issue channel is `#<KEY>` |
| `type` | `TEXT` (implemented), `VOICE` (reserved, not implemented) |
| `visibility` | `PUBLIC` \| `PRIVATE` |
| `ownerType` / `ownerId` | `USER` \| `AGENT`; the creator or the system |
| `archivedAt` | nullable |
| `createdAt` / `updatedAt` | |

- An **issue channel** is created automatically with the issue, private to the
  workspace, with the reporter, the assignee and mentioned principals
  subscribed.
- Workspace channels (`#general`, ceremonies) are created by users. They are
  part of the model from day one; their UI is phase 2.

### Subscriber

`channel_subscribers`: `channelId`, `principalType` (`USER` \| `AGENT`),
`principalId`, `permission` (`READ` \| `WRITE`), `createdAt`.

- The owner, workspace managers and the creator have implicit write.
- Agents receive a subscription to the issue channel when mentioned or when a
  run is triggered for that issue. The run token authorises the write; the
  subscription is the durable record.

### Message

| Field | Notes |
|---|---|
| `id` | |
| `workspaceId` | |
| `channelId` | |
| `type` | `INFO` \| `ASK` \| `INSTRUCT` |
| `content` | Markdown, ≤1023 code points |
| `authorType` / `authorId` | `USER` \| `AGENT` |
| `mentionType` / `mentionId` | structured mention, at most one; nullable |
| `recipientType` / `recipientId` | required for ASK/INSTRUCT; equals the mention |
| `closed` | boolean; closed blocks replies and triggers |
| `answerState` | `PENDING` \| `ANSWERED` \| `CLOSED` \| `EXPIRED` |
| `externalRefs` | JSON list of provider artefacts (PR comment/review) |
| `createdAt` / `updatedAt` | |

- The mention is the authoritative reference. The content carries a rendered
  highlight (e.g. `**@Vivy**`), but the backend resolves the trigger from
  `mentionType`/`mentionId`, never from parsing text.
- ASK/INSTRUCT require a mention; it becomes the recipient. INFO may carry a
  passive mention (highlight only, no wake-up) or none.
- The 1023 limit reuses `shared/limits.js` / the agent channel contract.

### Reply

`replies`: `id`, `workspaceId`, `messageId`, `content` (Markdown, ≤1023),
`authorType` / `authorId`, `externalRefs`, `createdAt` / `updatedAt`.

- A reply has no type, no recipient and no mention. It never triggers an agent.
- A message accepts at most **5 replies**; the 5th closes the message
  automatically. The author can close earlier.
- Replying to a closed message is `409`.

### Notification

`notifications`: `id`, `workspaceId`, `principalType`, `principalId`,
`messageId`, `replyId`, `kind` (`REPLY`; future `MENTION`), `readAt`,
`createdAt`.

- Used for **user** authors of an ASK: every reply creates an unread
  notification until the author reads it or closes the message.
- **Agent** authors are notified by a `REPLY` trigger (RFC-2026-0002), not by
  this table.

## Semantics

| Type | Waits for reply | Wakes an agent | Grants | Reply rule |
|---|---|---|---|---|
| INFO | no | no | nothing new; agent needs `interact` to post a new message | user may reply; agents never reply to INFO |
| ASK | yes | yes, immediately | `reply` only | expected from the mentioned principal; author notified of each reply |
| INSTRUCT | no | yes, immediately | actions allowed by the target role + optional reply | optional |
| REPLY | — | no | — | flat, no mentions, max 5 per message |

### Author rules

- **User author**:
  - with mention → `ASK` (derived by the API; the UI sends no type);
  - without mention → `INFO`;
  - `INSTRUCT` is rejected (`422 field=type`). The product wants the user to
    present the problem, not the solution.
- **Agent author** (via CLI): chooses the type; ASK/INSTRUCT require a mention;
  INFO may carry a passive mention.
- A user reply is allowed on INFO, ASK and INSTRUCT; an agent reply is allowed
  on ASK and INSTRUCT, never on INFO.

### Reply notification and closing

- When a reply is created on an ASK:
  - `answerState` becomes `ANSWERED` (if still open);
  - the ASK author is notified:
    - **user author**: an unread `notifications` row; the channel and message
      show a badge; the author opens the thread, evaluates and closes;
    - **agent author**: a `REPLY` trigger (RFC-2026-0002); the run receives the
      thread, evaluates and may `ens msg close --message <id>`; it also has the
      author's role capabilities, but it never advances the issue status.
- The author closes with `closed = true`; only the author (or a workspace
  manager for moderation) can close. Closing stops replies and triggers.
- The 5th reply auto-closes the message (`closed = true`); the author is still
  notified to review the last reply.
- If the author is an inactive agent, no trigger is created; the message stays
  `ANSWERED` and a human can close it. (If the product prefers that agent
  authors are never woken by replies, this is the one point to revisit; the
  RFC assumes the author must evaluate and therefore is woken.)

### Mentions

- At most one per message; the content may contain other `@` as plain text.
- The principal must be an active user or agent of the workspace; otherwise
  `422 field=mention`.
- The UI renders the mention from the structured field (never by parsing), so a
  renamed agent keeps the correct identity and a forged `@Name` is just text.
- Removing the mention from the composer before sending turns a user message
  back into INFO; it is not sent at all.
- Mentions are not allowed in replies.

## Channels and visibility

- Issue channels: name `#<KEY>`, `visibility = PRIVATE`, auto-created on issue
  creation; auto-subscribe reporter, assignee and mentions as they appear.
- Workspace channels: created by a manager, public or private; subscribers with
  read/write; `#general` is just a channel (phase 2 UI).
- Reading: workspace members with access to the issue/channel; writing:
  subscribers with `WRITE` (owner/manager implicit).
- Archiving keeps history and blocks new messages.

## Permissions

Role flags (boolean, toggled in the role editor):

| Flag | Meaning |
|---|---|
| `interact` | may converse (post INFO/ASK/INSTRUCT per author rules) |
| `unblock` | may unblock issues (existing) |
| `review` | may review PRs (RFC-2026-0003) |
| `merge` | may merge under repository policy (RFC-2026-0003) |
| `deploy` | may deploy under environment policy (RFC-2026-0003) |

- Channel read/write is separate from role flags and enforced by subscription.
- An INSTRUCT never grants more than the target role allows: the run builds the
  intersection of the message type, the target role flags and the repository/
  environment policy (RFC-2026-0002).
- Users have no role flags; they cannot merge/deploy through the product (they
  use the provider directly if needed).

## API (proposed)

- `GET/POST /api/workspaces/{ws}/channels`
- `GET/PATCH /api/workspaces/{ws}/channels/{id}` (visibility, archive,
  subscribers)
- `GET/POST /api/workspaces/{ws}/channels/{id}/messages`
- `GET/PATCH /api/workspaces/{ws}/messages/{id}` (close)
- `POST /api/workspaces/{ws}/messages/{id}/replies`
- `GET /api/workspaces/{ws}/issues/{key}/channel` (the automatic channel)
- `GET /api/workspaces/{ws}/notifications` (user authors; unread count)
- `PATCH /api/workspaces/{ws}/notifications/{id}` (mark read)
- Compatibility: `GET/POST /api/workspaces/{ws}/issues/{key}/comments` maps to
  the issue channel as INFO messages during the migration.

Message creation payload:

```json
{
  "content": "Markdown text",
  "mention": { "type": "agent", "id": "<id>" }
}
```

- User: `type` is rejected; the API derives ASK/INFO from `mention`.
- Agent: `type` is required (`info|ask|instruct`); ASK/INSTRUCT require
  `mention`.
- Replies: `{ "content": "..." }` only.

## UI

- The issue panel becomes the issue channel: messages, replies (0–5), author
  avatar/name, type badge for agent messages, `PENDING`/`CLOSED` state, link to
  external artefacts (PR).
- User composer: no type selector. Inserting a mention shows an “Ask” hint and
  sends ASK; removing it sends INFO. Mentions are highlighted and limited to
  one. Markdown preview and the 1023 counter.
- Agent messages display their type (Info/Ask/Instruct); replies are always
  plain.
- The ASK author sees an unread badge on the channel/message and a **Close**
  action; closing is confirmed and visible.
- Role editor: toggles for `interact`, `unblock`, `review`, `merge`, `deploy`.
- Workspace channels list is phase 2.

## Migration

- Create a `#<KEY>` channel per existing issue.
- Convert every `IssueComment` into an INFO message in that channel, preserving
  id/createdAt and mapping the author (user login → user id; agent name → agent
  id when resolvable, otherwise an unknown principal).
- `issue_events` stays as the audit ledger; message creation also records an
  event (`type = comment/message`) so History keeps working.
- The comments endpoint remains a facade for one release; the frontend moves to
  messages.

## Non-goals

- Triggers/wake-up (RFC-2026-0002).
- PR mirror, review, merge and deploy (RFC-2026-0003).
- Voice channels, DMs, e-mail/push notifications, mentions in replies,
  workspace-channel UI and ceremonies (phase 2).
- Mention notifications for non-authors (future).

# Acceptance Criteria

- [ ] Channels, subscribers, messages, replies and notifications persisted with
      domain and integration tests.
- [ ] Issue channel auto-created; comments migrated as INFO; legacy endpoint
      works as a facade.
- [ ] User message with mention becomes ASK; without mention becomes INFO;
      `INSTRUCT` from a user is rejected.
- [ ] Agent message chooses INFO/ASK/INSTRUCT via CLI; ASK/INSTRUCT require a
      mention; INFO with mention does not wake.
- [ ] One structured mention per message, hidden field, highlight; other `@`
      are plain; invalid principal → `422`.
- [ ] Replies have no mention, max 5, auto-close on the 5th; closed blocks
      replies and triggers.
- [ ] The ASK author is notified of every reply (user: unread notification;
      agent: REPLY trigger per RFC-2026-0002); the author closes; the state
      becomes CLOSED.
- [ ] Markdown sanitized; 1023 code points enforced for messages and replies.
- [ ] Role flags with toggles; INSTRUCT cannot exceed the target role.
- [ ] UI: channel view, composer, mention picker, replies, close, unread badge,
      role toggles; no type selector for users.
- [ ] No console/copilot/design work in this slice.

# Impact

- New `Communication` domain/context, tables and migration; new APIs; issue
  panel rewrite around the channel; role editor; notifications; tests.
- `IssueComment`/`CommentIssue` become legacy/compatibility; issue events gain
  message events.
- RFC-2026-0002 consumes message/reply events for triggers; RFC-2026-0003
  mirrors messages/reviews to the PR.

# Alternatives Considered

- **Keep flat comments and parse `@`**: rejected (ambiguous names, no trigger
  idempotency, no replies, no closed state).
- **Nested Slack-like threads**: rejected for the MVP (complex UI; 5 flat
  replies cover the conversations).
- **Message types chosen by users**: rejected; users would produce INSTRUCT and
  skip the problem framing. Mention = ASK is the induced behaviour.
- **Mentions in replies**: rejected; action requests are INSTRUCT messages, and
  the 5-reply cap bounds conversations.
- **Replies waking the author only for users**: rejected for autonomy; agent
  authors must be able to evaluate and close (via a REPLY trigger).

# References

- Product owner brief, 2026-10-29.
- RFC-2026-0002 (orchestration) and RFC-2026-0003 (delivery).
- `docs/ADR-003-execution-targets.md`.
- Current `IssueComment`, `CommentIssue`, `IssueEvent` and the 1023/4095 limits.
- `kanban.local` review-by-comment behaviour.
