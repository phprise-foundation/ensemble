# CHAT.md — Copilot protocol and decisions

> Reference for the board **Copilot** (the always-on chat agent embedded in the
> UI). Keep this in sync with `STATE.md` and `ROADMAP.md`.

## Overview

The board exposes a Copilot conversation:

- `GET  /api/chat` — conversation history.
- `POST /api/chat` — send `{ "message": "..." }`, get the updated conversation.

The HTTP layer never talks to an LLM directly. It depends on the **`ChatAgent`**
domain contract (`reply(Conversation): Message`). Two implementations exist:

- `EchoChatAgent` — offline stub (default). Echoes the message.
- `HttpChatAgent` — calls the always-on **chat service** over HTTP.

Both are selected in the composition root (`public/index.php`) by `CHAT_URL`.

## Board → chat service protocol

`HttpChatAgent` sends:

```
POST {CHAT_URL}/reply
Authorization: Bearer {CHAT_TOKEN}
Content-Type: application/json

{ "messages": [ { "role": "user", "body": "..." }, ... ] }
```

and expects:

```
200 { "reply": "..." }
```

Any non-2xx status or a response without a string `reply` raises
`ChatUnavailableException`, which the controller maps to `502`.

The service is **read-only**: it inspects the board and answers, never mutates it.

## Chat service

Implemented in the [agent-watcher](https://github.com/phprise-foundation/agent-watcher)
repo as `bin/chat-server` (`src/chat_server.py`), a small stdlib HTTP wrapper
around `opencode`:

- reads the board through the `kanban` helper (`AW_BACKEND=local`);
- builds a prompt with the board snapshot + the conversation;
- returns opencode's answer.

| Env | Meaning |
|---|---|
| `AW_CHAT_PORT` | listening port (default `8080`) |
| `AW_CHAT_TOKEN` | bearer token required on `POST /reply` |
| `AW_BACKEND` / `AW_KANBAN_URL` / `AW_KANBAN_TOKEN` | how the service reads the board |
| `AW_MODEL` | opencode model (`provider/model`) |
| `AW_WORKDIR` | working directory passed to opencode |

## Running with the real service

```bash
# .env
CHAT_URL=http://chat:8080
CHAT_TOKEN=change-me

docker compose --profile chat up -d --build
```

Without `CHAT_URL` (the default), the board uses the offline stub, so a plain
`docker compose up` keeps working with no LLM.

## Decisions taken autonomously

- **No new PHP HTTP dependency.** `phprise/common` and `guzzlehttp/psr7` ship no
  client, so `HttpChatAgent` uses a hand-rolled `CurlHttpTransport` behind a tiny
  `HttpTransport` interface (testable with a fake).
- **Board snapshot in the prompt, not tool-calling.** For a read-only copilot the
  service includes the board items in the prompt; this keeps it stateless and
  avoids giving the agent write tools.
- **`set_time_limit(120)` in the front controller** — a real opencode reply can
  exceed PHP's default 30s. A synchronous request is acceptable for now; SSE /
  async is deferred to the hardening phase.
- **Chat token separate from the board password** (`CHAT_TOKEN`): the board↔service
  hop already uses a token, even though the UI auth is still the raw password.

## Pending (next phases)

- **Phase 3 — actions.** Let the copilot create/move/comment items. Requires
  giving the agent the `kanban` write helper and a safe confirmation flow.
  Decision needed: which actions to expose and how to confirm destructive ones.
- **Hardening (#5).** SSE/streaming for the copilot, and replacing the raw board
  password with a dedicated token.
