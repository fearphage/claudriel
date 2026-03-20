# Spec 10: Messaging Gateway

**Phase:** 3 — Onboarding and Export
**Milestone:** v2.3
**Estimated Effort:** 3 weeks
**Owner:** TBD

## Problem Statement

Claudriel's messaging is tightly coupled to Gmail. The agent tools, ingestion pipeline, and OAuth flow are all Gmail-specific. Adding a second messaging channel (Slack, Outlook, SMS) would require duplicating significant code. A messaging gateway abstraction would allow channel-agnostic agent interactions and a single ingestion pipeline for all message sources.

## User Stories

1. **As a user**, I connect Slack alongside Gmail, and the agent searches messages across both channels seamlessly.
2. **As a user**, when I ask the agent to "send a message to Sarah", it asks which channel to use based on my history with Sarah.
3. **As a developer**, adding a new messaging channel requires implementing one interface, not modifying the entire pipeline.

## Nonfunctional Requirements

- **Multi-tenant:** Each tenant's channel connections are independent.
- **Security:** OAuth tokens stored encrypted, per-channel, per-tenant.
- **Reliability:** If one channel is down, others continue functioning.
- **Audit:** All outbound messages logged with channel, recipient, and approval status.

## Data Model

### Entity: MessagingChannel

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | Primary key |
| tenant_id | uuid | FK to Tenant |
| channel_type | enum | `gmail`, `outlook`, `slack`, `sms` |
| display_name | string | User-facing name ("Work Gmail", "Team Slack") |
| config | json | Channel-specific config (encrypted at rest) |
| oauth_token | text | Encrypted OAuth token |
| oauth_refresh_token | text | Encrypted refresh token |
| token_expires_at | datetime | |
| status | enum | `active`, `disconnected`, `error` |
| last_sync_at | datetime | |
| created_at | datetime | |

### Interface: MessageGatewayInterface

```php
interface MessageGatewayInterface
{
    public function list(array $filters): array;          // Returns Envelope[]
    public function read(string $messageId): Envelope;
    public function draft(Envelope $envelope): DraftResult;
    public function send(string $draftId): SendResult;
    public function channelType(): string;
}
```

### Refactor: Existing Gmail code

- `InternalGoogleController` refactored to implement `MessageGatewayInterface`
- `GmailMessageNormalizer` remains as Gmail-specific normalizer, called by `GmailGateway`
- `GoogleTokenManager` generalized to `ChannelTokenManager`

## API Surface

### Internal API (Agent — replaces current Gmail-specific endpoints)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/internal/messages/list` | GET | List messages across all channels (or filter by channel) |
| `/api/internal/messages/read/{channel}/{id}` | GET | Read a specific message |
| `/api/internal/messages/draft` | POST | Create a draft on a specific channel |
| `/api/internal/messages/send` | POST | Send an approved draft |
| `/api/internal/channels/list` | GET | List connected channels |
| `/api/internal/channels/{id}/status` | GET | Channel health status |

### Agent Tools (replace Gmail-specific tools)

| Current Tool | New Tool | Change |
|-------------|----------|--------|
| `gmail_list` | `message_list` | Adds `channel` filter parameter |
| `gmail_read` | `message_read` | Adds `channel` parameter |
| `gmail_send` | `message_send` | Adds `channel` parameter, supports draft flow |

### GraphQL

- `messagingChannels: [MessagingChannel]` — List connected channels
- `connectChannel(type: String!, config: JSON!): MessagingChannel` — Initiate OAuth for a channel
- `disconnectChannel(id: ID!): Boolean` — Disconnect a channel

## Agent/Tool Interactions

1. **Message list:** Agent calls `message_list` with optional channel filter. Gateway dispatches to all active channels and merges results.
2. **Channel selection:** When sending, if user doesn't specify channel, agent checks Person record for preferred channel. If no preference, agent asks.
3. **Draft flow:** Agent creates draft → shows to user for approval → user confirms → agent sends.
4. **Channel down:** If a channel returns an error, agent reports it and continues with other channels.

## Acceptance Criteria

- [ ] `MessageGatewayInterface` defined with list/read/draft/send methods
- [ ] `GmailGateway` implements interface (refactored from current code)
- [ ] MessagingChannel entity registered with encrypted config storage
- [ ] Internal API endpoints work channel-agnostically
- [ ] Agent tools updated to use channel parameter
- [ ] Multiple channels can be connected per tenant
- [ ] Channel health shown in admin UI
- [ ] Outbound messages logged with channel and approval status
- [ ] Existing Gmail functionality unbroken after refactor

## Tests Required

### Playwright MCP
- `test_connect_gmail_channel` — OAuth flow for Gmail
- `test_channel_list` — View connected channels
- `test_disconnect_channel` — Disconnect and verify
- `test_multi_channel_message_list` — Messages from multiple channels displayed
- `test_channel_selection_on_send` — Agent asks which channel when sending

### Integration
- `GmailGatewayTest` — Implements interface correctly
- `ChannelTokenManagerTest` — Token storage, refresh, encryption
- `MessageListAggregationTest` — Results from multiple gateways merged
- `ChannelIsolationTest` — Tenant A's channels invisible to Tenant B
- `GatewayFallbackTest` — One channel error doesn't break others
- `BackwardsCompatibilityTest` — Existing Gmail-only tenants work unchanged

### Agent (Python)
- `test_message_tools_channel_param` — New tools accept channel parameter
- `test_backwards_compat` — Old tool names still work (deprecated alias)

## Rollout Plan

1. **Phase A:** Deploy interface and GmailGateway (refactor, no behavior change)
2. **Phase B:** Deploy MessagingChannel entity and channel management UI
3. **Phase C:** Deploy channel-agnostic agent tools (with Gmail as default)
4. **Phase D:** Add Outlook gateway (first new channel)
5. **Phase E:** Add Slack gateway (when demand justifies)

## Rollback Steps

Phase A rollback:
1. Revert GmailGateway to direct InternalGoogleController
2. No data changes

Phase B+ rollback:
1. Disable new channel types
2. Gmail continues working through GmailGateway
3. MessagingChannel records preserved
