---
name: claudriel:ingestion
description: Triggers when working on event ingestion, Gmail normalization, or commitment extraction from messages
---

# Ingestion Specialist

## Scope

Packages: `src/Ingestion/*`, `src/Pipeline/*`
Key files: `GmailMessageNormalizer.php`, `EventHandler.php`, `CommitmentHandler.php`, `CommitmentExtractionStep.php`

## Key Interfaces

```php
// GmailMessageNormalizer — pure function, no dependencies
public function normalize(array $raw, string $tenantId): Envelope

// EventHandler — persists events and upserts people
public function handle(Envelope $envelope): McEvent

// CommitmentHandler — saves AI-extracted commitments
public function handle(array $candidates, McEvent $event, string $personId, string $tenantId): void

// CommitmentExtractionStep — AI pipeline step
public function process(array $input, PipelineContext $context): StepResult
```

## Architecture

Full ingestion pipeline:

```
Gmail API raw payload
  → GmailMessageNormalizer::normalize($raw, $tenantId)
  → Envelope(source='gmail', type='message.received', payload=[...])
  → EventHandler::handle($envelope)
  → saves McEvent + upserts Person (by email uniqueness)
  → McEvent

McEvent body
  → CommitmentExtractionStep::process(['body' => ..., 'from_email' => ...], $ctx)
  → AI prompt → Anthropic API → JSON response
  → StepResult::success(['commitments' => [{title, confidence}, ...]])

Extraction output
  → CommitmentHandler::handle($candidates, $event, $personId, $tenantId)
  → filters: confidence < 0.7 → silently skipped
  → saves Commitment entity per accepted candidate
```

Envelope structure:
```php
new Envelope(
    source:    'gmail',
    type:      'message.received',
    payload:   ['message_id', 'thread_id', 'from_email', 'from_name', 'subject', 'date', 'body'],
    timestamp: ISO 8601,
    traceId:   uniqid('gmail-', true),
    tenantId:  string,
)
```

## Common Mistakes

- **Base64url decoding**: Gmail body uses URL-safe alphabet. Must `strtr($data, '-_', '+/')` before `base64_decode()`. Standard `base64_decode()` alone produces garbled text.
- **From header parsing**: "From" comes in two formats: `"Name <email>"` and bare `"email"`. The regex must handle both or person upsert gets wrong email.
- **No deduplication**: `CommitmentHandler` saves every candidate above threshold. If the same email is processed twice, duplicate commitments are created.
- **Person upsert is insert-only**: `EventHandler::upsertPerson()` checks email existence but does NOT update the name if the person already exists with a different name.
- **AI response parsing**: `CommitmentExtractionStep` expects raw JSON array from AI. If the AI wraps it in markdown code fences or adds commentary, `json_decode()` fails and the step returns `StepResult::failure()`.

## Testing Patterns

- **GmailMessageNormalizer**: Pure function, test with fixture arrays. No mocking needed.
- **EventHandler**: Inject repos with `InMemoryStorageDriver`. Verify McEvent saved and Person upserted.
- **CommitmentHandler**: Inject repo with `InMemoryStorageDriver`. Test threshold filtering with candidates at 0.69, 0.70, 0.71.
- **CommitmentExtractionStep**: Mock the `$aiClient` to return known JSON strings. Test with valid JSON, empty array, and invalid responses.

## Related Specs

- `docs/specs/ingestion.md` — full Envelope structure, Gmail payload notes, person upsert logic
- `docs/specs/pipeline.md` — Waaseyaa PipelineStepInterface contract, StepResult API, prompt template
- `docs/specs/entity.md` — McEvent, Person, Commitment entity definitions
