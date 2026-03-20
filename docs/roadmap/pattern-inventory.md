# Claudia → Claudriel Pattern Inventory

> Extracted 2026-03-19. Patterns only, no code. PolyForm Noncommercial license respected.

## Inventory Summary

| # | Pattern | Category | Impact | Effort | Priority |
|---|---------|----------|--------|--------|----------|
| 1 | Judgment Rules System | Agent Intelligence | High | Medium | P0 |
| 2 | Adaptive Memory Decay | Memory | High | Medium | P1 |
| 3 | Memory Consolidation | Memory | High | High | P1 |
| 4 | Structured Onboarding Archetypes | Onboarding | Medium | Medium | P1 |
| 5 | Rehearsal Effect on Search | Memory | Medium | Low | P1 |
| 6 | Preflight Self-Diagnostics | Observability | Medium | Low | P2 |
| 7 | Skill Metadata and Tool Richness | Agent Intelligence | High | High | P0 |
| 8 | Agent Continuation Mechanism | Agent Intelligence | High | Medium | P0 |
| 9 | PARA Vault Export | Data Portability | Medium | Medium | P2 |
| 10 | Messaging Gateway Concept | Integration | Medium | High | P2 |
| 11 | Entity Relationship Graph | Memory | Medium | High | P2 |
| 12 | Two-Tier Agent Dispatch | Agent Intelligence | Medium | High | P3 |
| 13 | Session Context Assembly | Agent Intelligence | Medium | Low | P1 |
| 14 | Background Job Scheduling | Infrastructure | Low | Medium | P3 |
| 15 | Terminal UI Dashboard | Observability | Low | High | P3 |
| 16 | Obsidian Canvas Generation | Export | Low | Medium | P3 |
| 17 | Velocity-Based Relationship Tracking | Memory | Medium | Medium | P2 |
| 18 | Temporal Extraction Pipeline | Ingestion | Medium | Medium | P2 |
| 19 | Confidence-Based Source Attribution | Trust | High | Low | P1 |
| 20 | Context File Fallback System | Resilience | Medium | Low | P2 |

---

## Detailed Pattern Descriptions

### 1. Judgment Rules System

**Behavior:** System learns from user corrections and stores them as durable rules. When the agent makes a mistake and the user corrects it, the correction is captured as a "judgment rule" with context about when to apply it. Future agent interactions consult these rules to avoid repeating mistakes.

**Why it matters for Claudriel:** Currently the agent has no persistent learning mechanism between sessions. Each conversation starts fresh. Judgment rules would allow the agent to accumulate domain knowledge per-tenant, improving accuracy over time.

**SaaS considerations:**
- Rules must be tenant-isolated (each user's corrections apply only to their agent)
- Storage: JudgmentRule entity with tenant_id, rule text, context, confidence, created/updated timestamps
- Rules injected into agent system prompt at session start (bounded by token budget)
- Admin UI for users to view, edit, and delete their rules
- Rate limiting on rule creation to prevent prompt injection via corrections

**Source reference:** `template-v2/.claude/rules/` (behavioral rules), `context/learnings.md` (session learnings), `context/patterns.md` (detected patterns)

**Risk/licensing:** Pattern only. Claudia stores rules as flat markdown files read by Claude Code. Claudriel will use a database-backed entity with API endpoints. No code overlap.

---

### 2. Adaptive Memory Decay

**Behavior:** Memory importance scores decay over time via a configurable daily rate (default 0.995). Recent memories surface first in search results. A background job runs daily at 2 AM applying the decay function. Memories accessed frequently resist decay (rehearsal effect).

**Why it matters for Claudriel:** As users accumulate events, commitments, and contacts, older/less-relevant items should naturally recede. Currently all entities have equal weight regardless of age or relevance.

**SaaS considerations:**
- Decay must be per-tenant (each tenant's entities decay independently)
- Configurable decay rate per tenant (account settings)
- Background CLI command (`claudriel:decay`) run via cron, processing tenants in batches
- Must not affect entity data integrity, only search ranking/surfacing priority
- Importance score field on relevant entities (Person, Commitment, McEvent)
- Observability: log decay runs, track entity count processed, surface anomalies

**Source reference:** `memory-daemon/claudia_memory/daemon/scheduler.py` (APScheduler daily job), `config.py` (decay_rate_daily: 0.995, min_importance_threshold: 0.1)

**Risk/licensing:** Pattern only. Claudia uses Python APScheduler + SQLite. Claudriel will use PHP CLI command + MySQL/SQLite via Waaseyaa entity system. Completely different implementation.

---

### 3. Memory Consolidation

**Behavior:** A background service merges near-duplicate memories, detects patterns across entities, and identifies cooling relationships. Runs every 6 hours. Uses embedding similarity to find duplicates, then merges metadata while preserving provenance.

**Why it matters for Claudriel:** Users interacting with the same contacts across many emails generate redundant Person and McEvent records. Consolidation reduces noise and improves brief quality.

**SaaS considerations:**
- Tenant-isolated processing (never merge across tenants)
- Embedding service needed (either local Ollama or cloud API like OpenAI embeddings)
- Deduplication candidates surfaced to user for approval before merge (safety-first principle)
- CLI command (`claudriel:consolidate`) with dry-run mode
- Audit trail: merged entities retain provenance chain
- Resource limits: cap embeddings per tenant per day to control costs

**Source reference:** `memory-daemon/claudia_memory/services/consolidate.py` (merge logic), `extraction/entity_extractor.py` (entity recognition)

**Risk/licensing:** Pattern only. The concept of embedding-based deduplication is well-established in information retrieval. No Claudia-specific algorithms copied.

---

### 4. Structured Onboarding Archetypes

**Behavior:** New users go through a 6-question discovery flow. Based on answers, the system detects an archetype (Consultant, Executive, Founder, Solo Professional, Content Creator) and provisions appropriate workspace structure, default commands, and context templates.

**Why it matters for Claudriel:** Currently new Claudriel accounts get a blank slate. Archetypes would provide immediate value by pre-configuring the system for common workflows, reducing time-to-value.

**SaaS considerations:**
- Archetypes stored as system configuration, not per-tenant data
- Onboarding flow via the agent chat interface (conversational, not form-based)
- Account metadata stores selected archetype for future reference
- Archetype determines: default workspace structure, suggested integrations, brief format, initial agent system prompt additions
- Must be skippable (users can opt out of guided onboarding)
- Analytics: track archetype distribution for product decisions

**Source reference:** `template-v2/.claude/skills/archetypes/` (5 archetype definitions), `skills/onboarding.md` (6-question flow)

**Risk/licensing:** Pattern only. The concept of user archetypes/personas is a standard UX pattern. Claudriel will define its own archetypes and discovery questions appropriate to the SaaS product.

---

### 5. Rehearsal Effect on Search

**Behavior:** When a memory is accessed (recalled), its importance score gets a small boost. Frequently accessed memories resist decay. This creates a natural "rehearsal effect" where important, actively-used information stays prominent.

**Why it matters for Claudriel:** Improves search relevance over time. Entities the user frequently interacts with (key contacts, active commitments) naturally rank higher without manual curation.

**SaaS considerations:**
- Lightweight: increment a `last_accessed_at` timestamp and `access_count` on entity access
- Boost factor applied during search/brief assembly
- Per-tenant isolation inherent (users only access their own entities)
- No additional infrastructure needed beyond schema migration
- Analytics opportunity: track most-accessed entities for product insights

**Source reference:** `memory-daemon/claudia_memory/services/recall.py` (rehearsal boost on access), `consolidate.py` (decay resistance for accessed items)

**Risk/licensing:** Pattern only. Rehearsal/recency boosting is a standard information retrieval technique.

---

### 6. Preflight Self-Diagnostics

**Behavior:** At session start, the system runs health checks: is the memory daemon reachable? Are MCP tools available? Are context files intact? If something is broken, it reports the issue and falls back to degraded mode rather than failing silently.

**Why it matters for Claudriel:** The agent subprocess depends on HMAC auth, Google OAuth tokens, and PHP internal APIs. Any of these can break. Preflight checks surface issues before the user encounters mysterious failures.

**SaaS considerations:**
- Health check endpoint (`GET /api/health`) with component-level status
- Agent preflight: at chat session start, verify OAuth tokens, HMAC signing, internal API reachability
- Graceful degradation: if Gmail token expired, tell user instead of failing mid-conversation
- Admin health dashboard showing per-tenant integration status
- CLI command (`claudriel:health`) for ops debugging

**Source reference:** `memory-daemon/claudia_memory/daemon/health.py` (HTTP health server), `template-v2/.claude/rules/memory-availability.md` (fallback behavior)

**Risk/licensing:** Pattern only. Health check endpoints are a universal ops practice.

---

### 7. Skill Metadata and Tool Richness

**Behavior:** Skills have structured YAML metadata (name, description, triggers, invocation mode, effort level). Tools are numerous (11+ MCP tools) covering memory, entities, relationships, predictions, context, briefings, and vault sync. The two-tier dispatch (fast Task tool vs autonomous Native agent) routes work appropriately.

**Why it matters for Claudriel:** The agent currently has only 5 tools (Gmail list/read/send, Calendar list/create). Expanding to 17+ tools would dramatically increase agent capability: commitment management, person lookup, brief generation, search, workspace operations, schedule queries, and more.

**SaaS considerations:**
- Each new tool needs an internal API endpoint with HMAC auth
- Tools must respect tenant isolation (all queries scoped to current tenant)
- Rate limiting per tool per tenant to prevent abuse
- Tool metadata for the agent's system prompt (description, parameters, examples)
- Audit logging for sensitive tools (email send, calendar create)
- Phased rollout: add tools incrementally, validate each before adding next

**Proposed new tools (12+):**
1. `commitment_list` — List active/pending commitments
2. `commitment_update` — Update commitment status
3. `person_search` — Search contacts by name/email
4. `person_detail` — Get full person context
5. `brief_generate` — Generate a day brief on demand
6. `event_search` — Search ingested events
7. `workspace_list` — List user workspaces
8. `workspace_context` — Get workspace summary
9. `schedule_query` — Query schedule entries
10. `triage_list` — List untriaged items
11. `triage_resolve` — Mark triage item as resolved
12. `search_global` — Full-text search across all entities

**Source reference:** `memory-daemon/claudia_memory/mcp/server.py` (11 MCP tools), `template-v2/.claude/skills/` (YAML skill metadata), `template-v2/.claude/agents/` (two-tier dispatch)

**Risk/licensing:** Pattern only. Tool-use agent patterns are well-documented in Anthropic's public API docs. Claudriel tools will be original PHP endpoints.

---

### 8. Agent Continuation Mechanism

**Behavior:** Agent conversations have configurable turn limits per task type. When the limit is reached, the agent can request continuation rather than being cut off. Long tasks (research, multi-step operations) get higher limits. The user can approve continuation.

**Why it matters for Claudriel:** Currently hardcoded at 25 turns. Some tasks (composing a complex email based on multiple events, generating a detailed brief) need more turns. Others (simple calendar check) need fewer. Configurable limits with continuation improve both UX and cost control.

**SaaS considerations:**
- Turn limits stored in account settings with sensible defaults per archetype
- Per-task-type overrides (e.g., email_compose: 15, research: 40, quick_lookup: 5)
- Continuation mechanism: agent emits a "needs_continuation" event, frontend shows "Continue?" button
- Cost tracking: log turns consumed per session for billing/analytics
- Hard ceiling per tenant per billing period to prevent runaway costs
- Admin UI for users to adjust their limits

**Source reference:** `template-v2/.claude/agents/` (agent metadata with dispatch-tier), `memory-daemon/claudia_memory/mcp/server.py` (session management)

**Risk/licensing:** Pattern only. Configurable turn limits are a standard agent framework feature.

---

### 9. PARA Vault Export

**Behavior:** User data is exported as structured markdown following the PARA method (Projects, Areas, Resources, Archive). Exports target Obsidian vault format with relationship links between entities. Users own their data in plain text.

**Why it matters for Claudriel:** Data portability is a SaaS trust differentiator. Users who know they can export everything are more willing to commit to the platform. Also useful for backup and compliance.

**SaaS considerations:**
- Export endpoint (`POST /api/export/para`) generates a ZIP of markdown files
- Tenant-scoped: only exports current tenant's data
- Rate limited: one export per hour per tenant
- Background job for large exports (queue + notification when ready)
- Export includes: commitments, persons, events, workspaces, schedule entries
- GDPR compliance: this serves as the "data portability" feature
- No Obsidian-specific features required, just clean markdown with wikilinks

**Source reference:** `memory-daemon/claudia_memory/services/vault_sync.py` (PARA sync), `canvas_generator.py` (Obsidian canvas), `template-v2/workspaces/` (PARA template structure)

**Risk/licensing:** Pattern only. PARA is Tiago Forte's public methodology. Markdown export is standard practice.

---

### 10. Messaging Gateway Concept

**Behavior:** Abstraction layer that normalizes different messaging channels (email, Slack, SMS) into a common Envelope format. Messages flow through the same ingestion pipeline regardless of source. Draft/send operations go through a common gateway with approval flow.

**Why it matters for Claudriel:** Currently Gmail-specific. A messaging gateway would allow adding Slack, SMS, or other channels without rewriting the agent tools or ingestion pipeline.

**SaaS considerations:**
- Gateway interface: `MessageGatewayInterface` with `list()`, `read()`, `draft()`, `send()` methods
- Channel implementations: GmailGateway (existing), SlackGateway (future), SMSGateway (future)
- All messages normalized to Envelope before entering ingestion pipeline
- Per-tenant channel configuration (which channels are connected)
- OAuth token management per channel per tenant
- Approval flow required for all outbound messages (safety-first)
- Rate limiting per channel per tenant

**Source reference:** `template-v2/.claude/skills/draft-reply.md` (email drafting), `follow-up-draft.md` (follow-up emails), MCP config (Gmail, Calendar integrations)

**Risk/licensing:** Pattern only. Message gateway/adapter pattern is a standard software architecture pattern.

---

### 11-20. Lower Priority Patterns (Not Selected for Initial Adoption)

| # | Pattern | Notes |
|---|---------|-------|
| 11 | Entity Relationship Graph | Valuable but high effort. Consider after consolidation (P3). |
| 12 | Two-Tier Agent Dispatch | Claudriel's subprocess model is simpler and sufficient for now. |
| 13 | Session Context Assembly | Partially implemented via ChatSystemPromptBuilder. Enhance as part of judgment rules. |
| 14 | Background Job Scheduling | Claudriel uses CLI commands + cron. Sufficient for current scale. |
| 15 | Terminal UI Dashboard | Not applicable to SaaS (Claudriel has web UI). |
| 16 | Obsidian Canvas Generation | Niche. Include in PARA export if demand exists. |
| 17 | Velocity-Based Relationship Tracking | Bundle with memory decay. |
| 18 | Temporal Extraction Pipeline | Partially exists in Claudriel ingestion. Enhance incrementally. |
| 19 | Confidence-Based Source Attribution | Bundle with judgment rules system. |
| 20 | Context File Fallback System | Not applicable to SaaS architecture. |

---

## Legal Compliance

All patterns in this inventory are architectural ideas and design concepts extracted from the Claudia system. No source code, configuration files, prompts, or literal text has been copied. Each pattern will be reimplemented as original code within the Claudriel/Waaseyaa architecture. The PolyForm Noncommercial license on the Claudia repository is respected.
