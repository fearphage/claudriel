---
name: claudriel:chat
description: Triggers when working on the chat interface, Anthropic API streaming, system prompt building, or chat entities
---

# Chat Specialist

## Scope

Packages: `src/Domain/Chat/*`, `src/Entity/ChatSession.php`, `src/Entity/ChatMessage.php`
Key files: `AnthropicChatClient.php`, `ChatSystemPromptBuilder.php`, `ChatController.php`, `ChatStreamController.php`, `StorageRepositoryAdapter.php`

## Key Interfaces

```php
// AnthropicChatClient — Anthropic API wrapper
public function __construct(string $apiKey, string $model = 'claude-sonnet-4-20250514')
public function complete(string $systemPrompt, array $messages): string
public function stream(string $systemPrompt, array $messages, callable $onToken): void

// ChatSystemPromptBuilder — assembles context-rich system prompt
public function __construct(string $rootPath, DayBriefAssembler $briefAssembler)
public function build(string $tenantId): string

// ChatController — message creation
public function send(ServerRequestInterface $request): ResponseInterface

// ChatStreamController — SSE streaming
public function stream(ServerRequestInterface $request, array $params): void

// StorageRepositoryAdapter — bridges ETM to EntityRepositoryInterface
public function __construct(EntityTypeManager $etm, string $entityTypeId)
```

## Architecture

End-to-end chat flow:

```
1. Client POSTs message
   POST /api/chat/send → ChatController::send()
   → creates/loads ChatSession entity
   → saves user ChatMessage (role='user')
   → returns JSON { session_uuid, message_id }

2. Client opens SSE connection
   GET /stream/chat/{messageId} → ChatStreamController::stream()
   → loads ChatSession via message's session_uuid
   → loads all ChatMessages for conversation history
   → formats as [{role: 'user'|'assistant', content: '...'}]

3. System prompt assembly
   ChatSystemPromptBuilder::build($tenantId)
   → reads CLAUDE.md (personality)
   → reads context/me.md (user context)
   → calls DayBriefAssembler::assemble() (current brief data)
   → concatenates sections into single system prompt
   (missing files silently omitted)

4. Anthropic API streaming
   AnthropicChatClient::stream($systemPrompt, $messages, $onToken)
   → POST https://api.anthropic.com/v1/messages (stream: true)
   → reads response body line by line
   → parses SSE: event: content_block_delta → extracts text
   → calls $onToken(string) per delta

5. SSE emission to client
   ChatStreamController emits:
   → event: chat-token  data: {"token": "..."}    (per delta)
   → event: chat-done   data: {"message": "..."}  (full response)
   → event: chat-error  data: {"error": "..."}    (on failure)
   → saves assistant ChatMessage (role='assistant')
```

## Common Mistakes

- **StorageRepositoryAdapter necessity**: Chat controllers need `EntityRepositoryInterface` but only have `EntityTypeManager`. The adapter bridges this gap. Don't try to use ETM directly for repository-style operations.
- **Output buffering for SSE**: Must call both `ob_flush()` AND `flush()` after each SSE event. Missing either causes the browser to buffer all tokens until stream ends.
- **System prompt file paths**: `ChatSystemPromptBuilder` reads files relative to `$rootPath` (from `CLAUDRIEL_ROOT` env or project root). Wrong path = empty personality/context sections with no error.
- **Message ordering**: Chat history is loaded by `session_uuid` and must be chronologically ordered for coherent conversation context. The entity query order matters.
- **API key in constructor**: `AnthropicChatClient` takes the API key at construction time. It reads from `$_ENV['ANTHROPIC_API_KEY']` at the controller level. Missing key = runtime error on first stream, not at boot.
- **No conversation pruning**: All messages for a session are loaded and sent to the API. Long conversations will exceed token limits with no truncation strategy.

## Testing Patterns

- **AnthropicChatClient**: Mock HTTP responses. Test `complete()` with a simple JSON response. Test `stream()` with multi-line SSE fixture data containing `content_block_delta` events.
- **ChatSystemPromptBuilder**: Create temp directory with test `CLAUDE.md` and `context/me.md`. Verify `build()` concatenates all sections. Test with missing files to verify graceful omission.
- **ChatController**: Use `InMemoryStorageDriver` via `StorageRepositoryAdapter`. Send mock `ServerRequestInterface` with JSON body. Verify session + message saved.
- **ChatStreamController**: Hardest to unit test due to output buffering. Consider integration test or mock `AnthropicChatClient::stream()` to verify callback behavior.

## Related Specs

- `docs/specs/chat.md` — full SSE format, environment variables, route table, entity keys
- `docs/specs/infrastructure.md` — ClaudrielServiceProvider route and entity registration
- `docs/specs/day-brief.md` — DayBriefAssembler used by ChatSystemPromptBuilder
