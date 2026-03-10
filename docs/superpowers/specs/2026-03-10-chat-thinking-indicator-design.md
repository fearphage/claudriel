# Chat Thinking Indicator Design

## Problem

The chat UI has no visual feedback between sending a message and receiving the first streamed token (the "thinking" gap). During streaming, there's no indicator that more content is coming. The current static "Claudriel is thinking..." text (hidden/shown with a CSS class) feels dead.

## Solution

CSS-only animated indicators for both phases of the response lifecycle:

1. **Pre-stream thinking indicator**: A shimmer-animated message bubble that appears in the message flow
2. **Streaming cursor**: A pulsing dot at the end of the growing assistant message

## Design

### Thinking Indicator (pre-stream)

- Appears as an assistant message bubble in the message flow (not a separate element below the messages)
- Contains "Claudriel is thinking..." text with a CSS shimmer effect
- Shimmer: uses `-webkit-background-clip: text` and `background-clip: text` (both prefixed and unprefixed) with a moving linear gradient
- Gradient: light gray to white to light gray (`#888` / `#ccc` / `#888`), sweeping left to right over ~2s, infinite loop
- Inserted into the DOM immediately when the user clicks Send (before the fetch POST)
- Persists through both the fetch phase and the early SSE connection phase
- Removed and replaced by the real assistant message when the first SSE `chat-token` event arrives

**Lifecycle across all response paths:**

| Path | Indicator appears | Indicator removed |
|------|------------------|-------------------|
| Normal SSE stream | On send click | On first `chat-token` (replaced by assistant bubble) |
| Non-streaming fallback (no `message_id`) | On send click | After `appendMessage()` renders the full response |
| Fetch error (network) | On send click | In the `.catch()` handler |
| Fetch error (server) | On send click | In the `!result.ok` handler |
| SSE error (`chat-error`, `onerror`) | On send click | In the error/onerror handlers |

### Streaming Cursor (during token flow)

- A pulsing dot (`●`) rendered via `::after` pseudo-element on `.message-content`
- Pulses between full opacity and ~20% opacity on a ~1s cycle (`@keyframes pulse`)
- Activated by adding a `.streaming` class to the `.message.assistant` div (the parent, so the CSS selector is `.message.assistant.streaming .message-content::after`)
- Removed when the `chat-done` SSE event fires (also removed in `chat-error` and `onerror` handlers)

### Removed

- The static `<div class="loading" id="loading">` element (dashboard.twig line 523)
- Associated `.loading` / `.loading.visible` CSS rules
- All 7 JS references to `loadingEl`: declaration (line 544), and the 6 show/hide calls in `sendMessage()` and its callbacks

### Helper functions (JS)

- `showThinking()`: creates a `.message.assistant.thinking-indicator` div with label "Claudriel" and shimmer content, appends to `messagesEl`, scrolls to bottom. Returns the element.
- `removeThinking()`: finds and removes `.thinking-indicator` from `messagesEl` if present.

These replace all `loadingEl.classList.add/remove('visible')` calls.

## File Changes

Only `templates/dashboard.twig` is modified:

1. **CSS**: Add `@keyframes shimmer` and `@keyframes pulse-dot`, `.thinking-indicator` styles (with `-webkit-background-clip` and `background-clip`), `.message.assistant.streaming .message-content::after` styles. Remove `.loading` / `.loading.visible` styles.
2. **JS**: Remove `loadingEl` variable. Add `showThinking()` and `removeThinking()` helpers. Call `showThinking()` at the start of `sendMessage()`. Call `removeThinking()` in every exit path (success, error, network error, non-streaming fallback). Add `.streaming` class to assistant `.message` div on first `chat-token`, remove on `chat-done`/`chat-error`/`onerror`.
3. **HTML**: Remove the `<div class="loading" id="loading">` element.

## Constraints

- CSS-only animation (no JS animation loops, no external dependencies)
- No backend changes
- No changes to SSE event format
- Only `dashboard.twig` is modified (not the legacy `chat.html.twig`)
- Both `-webkit-background-clip` and `background-clip` must be used for cross-browser shimmer support
