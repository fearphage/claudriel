# Dashboard Design — Brief + Chat Unified

**Date:** 2026-03-09
**Status:** Approved

## Overview

Merge the Day Brief and Chat into a single dashboard page at `/`. Two-column layout with live updates via SSE. Brief updates are triggered by a file signal; chat responses stream tokens from Anthropic.

## Layout

**Wide (>900px):** Side-by-side. Brief 40%, Chat 60%. Both columns scroll independently.
**Narrow (<=900px):** Stacked. Brief collapses into a `<details>` element. Chat takes full width below.

Single Twig template: `templates/dashboard.twig`. Replaces `day-brief.html.twig` and `chat.html.twig` as the home page.

```
┌─────────────────────────────────────────────────┐
│  Claudriel                                      │
├──────────────────────┬──────────────────────────┤
│  Day Brief (live)    │  Chat                    │
│                      │                          │
│  Events (3)          │  [session selector]      │
│  ├─ gmail            │                          │
│  │  └─ subject...    │  You: hello              │
│  └─ smoke-test       │                          │
│     └─ test.ping     │  Claudia: Hi! Here's     │
│                      │  what I see tod|          │
│  People (2)          │  (streaming...)           │
│  Pending (1)         │                          │
│  Drifting (0)        │                          │
│                      ├──────────────────────────┤
│                      │  [message input] [Send]  │
└──────────────────────┴──────────────────────────┘
```

## Routes

| Route | Method | Controller | Purpose |
|---|---|---|---|
| `/` | GET | `DashboardController::show` | Renders dashboard (brief data + chat sessions + config) |
| `/stream/brief` | GET | `BriefStreamController::stream` | SSE: pushes brief JSON when signal file changes |
| `/api/chat/send` | POST | `ChatController::send` | Saves user message, returns `{ message_id, session_id }` |
| `/stream/chat/{messageId}` | GET | `ChatStreamController::stream` | SSE: streams Anthropic response tokens, saves final message |

Existing routes `/brief` and `/chat` become redirects to `/` (backward compat, can remove later).

Route group `stream` for SSE endpoints. All routes reuse existing bearer auth pattern where applicable (stream endpoints use query param `?token=` since EventSource can't set headers).

## Signal Mechanism

File: `storage/brief-signal.txt`

**Write side:** `IngestController::handle()` and `CommitmentIngestHandler::handle()` touch this file after successful persistence (simple `file_put_contents` with current timestamp).

**Read side:** `BriefStreamController` runs a loop:
1. Read `brief-signal.txt` mtime
2. If mtime > last-seen mtime, assemble fresh brief JSON via `DayBriefAssembler`, emit SSE event
3. Sleep 2 seconds, repeat (with 200ms debounce after emitting to coalesce rapid ingests)
4. Send SSE keepalive comment every 15s to prevent timeout
5. Send `retry: 3000` header for explicit reconnect timing
6. Disconnect after 5 minutes (client auto-reconnects via EventSource)

## Chat Streaming Flow

1. Client POSTs `{ message, session_id? }` to `/api/chat/send`
2. Controller saves user `ChatMessage`, creates session if needed, returns `{ message_id, session_id }`
3. Client opens `EventSource('/stream/chat/{messageId}')`
4. `ChatStreamController`:
   - Loads session history
   - Builds system prompt via `ChatSystemPromptBuilder`
   - Calls Anthropic API with `stream: true`
   - For each `content_block_delta`: emits SSE `data: {"token": "..."}`
   - On completion: emits `data: {"done": true, "full_response": "..."}`, saves assistant `ChatMessage`
   - Sends `retry: 3000` header
5. Client appends tokens to message bubble in real-time, closes EventSource on `done`

## New Files

```
src/Controller/DashboardController.php    — combines brief + chat data, renders template
src/Controller/BriefStreamController.php  — SSE brief stream
src/Controller/ChatStreamController.php   — SSE chat token stream
src/Support/BriefSignal.php               — read/write signal file (encapsulates path + mtime logic)
templates/dashboard.twig                  — unified two-column template
```

## Modified Files

```
src/Provider/ClaudrielServiceProvider.php — new routes, redirect old ones
src/Controller/IngestController.php       — touch signal after ingest
src/Controller/ChatController.php         — return message_id instead of full response
src/Ingestion/Handler/CommitmentIngestHandler.php — touch signal after commitment creation
```

## Removed (after migration)

`templates/day-brief.html.twig` and `templates/chat.html.twig` become unused. Keep for one release cycle, then delete.

## Client-Side JS

Single `<script>` block in `dashboard.twig`:

- **Brief panel:** Opens `EventSource('/stream/brief')`. On `message` event, re-renders brief sections by replacing innerHTML of each section container. Reconnects automatically on error.
- **Chat panel:** On send, POSTs message, then opens `EventSource('/stream/chat/{messageId}')`. Appends tokens to a growing message bubble. On `done`, closes the EventSource. Session selector and new-chat button work as today.
- **Auth for SSE:** If bearer token is needed, appends `?token=...` to EventSource URLs. For local dev (no auth on GET routes), this is optional.

## Error Handling

- Brief SSE: if signal file missing, create it on first read. If assembler throws, emit SSE error event, client shows "Brief unavailable" in panel.
- Chat SSE: if Anthropic API fails mid-stream, emit `{"error": "..."}` event. Client shows error in chat bubble. Saved message gets `status: failed`.
- EventSource auto-reconnects with exponential backoff (browser default). Server sends `retry: 3000` header.

## Debug Headers

SSE responses include `X-Claudriel-Event` header for debugging:
- `brief-update` on brief stream events
- `chat-token` / `chat-done` / `chat-error` on chat stream events

## What This Does NOT Include

- WebSocket upgrade (SSE is sufficient for unidirectional server-to-client)
- Authentication changes (reuses existing bearer token pattern)
- Mobile app or PWA concerns
- Chat history loading when clicking a session (existing limitation, separate issue)
