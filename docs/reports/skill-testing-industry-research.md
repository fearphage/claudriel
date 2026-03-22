# Skill Testing & Drift Prevention: Industry Research

**Date:** 2026-03-21
**Context:** Research into industry practices for testing and maintaining AI agent skill/tool systems,
with focus on patterns applicable to Claudriel's Claude Code skill architecture (markdown instruction
files that guide LLM behavior, calling GraphQL APIs).

---

## 1. How Teams Test LLM-Driven Skills

### The Eval Stack (universal pattern)

Every mature team uses a layered eval stack. From bottom to top:

| Layer | What it tests | Tools |
|---|---|---|
| Unit / deterministic | Does the output contain required fields? Is JSON valid? Does the right tool get called? | promptfoo assertions, jsonschema, Pydantic/Zod |
| Rubric / LLM-as-judge | Did the agent follow the correct workflow? Is the intent parsed correctly? | LLM rubric grader, LangSmith evaluators |
| End-to-end / trajectory | Did the full tool-call sequence reach the correct outcome? | LangSmith trace eval, Claude Code skill-creator evals |
| Multi-turn | Did the agent accomplish the user's goal across an entire interaction? | LangSmith Multi-turn Evals (2025) |

### Golden Datasets

A golden dataset is a curated set of (input, expected behavior) pairs. Teams build it by:
1. Brainstorming representative user intents
2. Adding tricky edge cases (ambiguous phrasing, partial data, adversarial inputs)
3. Capturing real production failures as permanent regression cases

Golden datasets are versioned alongside the skill files. When a skill changes, the full golden
set reruns and scores must not regress below a threshold.

### Deterministic vs. Rubric Graders

Claude Code's Skill Creator (Skills 2.0, 2026) formalizes two grader types:

- **Deterministic graders:** Shell scripts, file checks, JSON schema validation. Score: pass/fail.
- **LLM rubric graders:** A second model evaluates qualitative criteria (e.g., "Did the agent
  extract the noun phrase rather than the full sentence?"). Score: 0.0-1.0 per criterion.

Ship thresholds used in practice:
- Technical skills: 5/8 rubric points
- Creative/intent-parsing skills: 3/6 rubric points

### Promptfoo (open source, MIT)

Promptfoo is the most practical small-team tool. Declarative YAML config:

```yaml
prompts: [skills/commit.md]
providers: [anthropic:claude-sonnet-4-5]
rubricProvider: anthropic:claude-opus-4
tests:
  - description: "Extracts entity name from natural sentence"
    vars:
      input: "add a workspace called NorthCloud for my Go projects"
    assert:
      - type: contains-json
      - type: llm-rubric
        value: "The entity name extracted is 'NorthCloud', not the full sentence"
  - description: "Rejects ambiguous input gracefully"
    vars:
      input: "add that thing"
    assert:
      - type: llm-rubric
        value: "Agent asks a clarifying question rather than guessing"
```

Promptfoo joined OpenAI (March 2026) but remains open source. It has a GitHub Action for CI
integration and supports matrix testing across prompts x models x test cases.

### Key Insight: Real Production Traffic is the Best Test Source

The best golden cases come from real failures and real user inputs, not invented scenarios.
Log inputs that produce wrong outputs in production, add them to the golden set immediately.

---

## 2. Drift Prevention for Agent Instruction Files

### What "drift" means for skills

A skill file drifts when:
- It references a GraphQL field, mutation, or type that has been renamed or removed
- It describes intent-parsing rules that no longer match actual user phrasing patterns
- It references a workflow step that was refactored or deleted from the backend

### Detecting instruction-to-schema drift

**GraphQL Inspector** (open source) compares schema versions and reports breaking changes:
removed fields, altered types, renamed arguments. Running it in CI catches the "schema moved,
skill didn't" problem before it reaches production.

Pattern:
1. On every deploy, run `graphql-inspector diff old-schema.graphql new-schema.graphql`
2. If breaking changes exist, fail CI and emit a checklist of skills that reference those fields
3. Grep skill files for the changed field names (simple but effective)

**Apollo Skills pattern:** Apollo open-sourced reusable skill files that embed the GraphQL schema
context directly. The skill references the schema introspection, so schema drift is caught when
the skill's embedded schema diverges from the live API. This is the "schema as ground truth"
approach.

### Score-based drift detection

The Agent Skill Bus framework (open source, 2025) monitors skill quality scores over time and
flags a drift alert when the score drops more than 15% week-over-week. This catches silent
degradation without requiring a specific test to have anticipated the failure.

Pattern for a small team:
1. Run your golden eval suite on a schedule (nightly or weekly)
2. Store scores in a simple JSON file or CI artifact
3. Fail if any skill drops more than a threshold from its last-known-good score

### Prompt drift (model behavior drift)

Prompt drift is when the model changes (new version, new fine-tune) and the skill instructions
that worked before now produce subtly wrong outputs. Detection approach:
- Lock a specific model version in your eval config
- Run evals against the old and new model in parallel before upgrading
- Only promote the model version if all skill scores remain above threshold

---

## 3. Patterns from Similar Systems

### Claude Code Skill Creator (Skills 2.0, 2026)

The most directly relevant system. Key patterns:
- SKILL.md has YAML front matter (name, description, allowed-tools)
- Evals are defined in a separate `promptfooconfig.yaml` alongside the skill
- Skill Creator runs evals in parallel, each in a clean independent agent context
- Scores are tracked over time; editing a skill and rerunning shows immediate delta
- "Ship threshold" is explicit: a skill must score above a minimum before being considered stable

Source: [code.claude.com/docs/en/skills](https://code.claude.com/docs/en/skills),
[Improving skill-creator](https://claude.com/blog/improving-skill-creator-test-measure-and-refine-agent-skills)

### LangSmith (LangChain)

Five patterns their team uses in production:
1. **Bespoke test logic per datapoint** — custom assertions, not generic
2. **Single-step evals** — validate one decision point in isolation
3. **Full agent turn testing** — end-to-end for each user intent
4. **Multi-turn with conditional logic** — simulate realistic back-and-forth
5. **Clean environment setup** — reproducible, no state leaking between tests

LangSmith also supports online evaluation: scoring real production traffic in real time to
detect quality drift as it happens, not just in CI.

### OpenAI Evals (2025-2026)

OpenAI published a dedicated post on testing agent skills systematically. Key takeaways:
- Evals should capture the full trajectory (tool calls, reasoning steps), not just final output
- Use a separate "eval model" to judge (not the same model being evaluated)
- Deterministic checks first; rubric checks only for what can't be checked deterministically

Source: [developers.openai.com/blog/eval-skills](https://developers.openai.com/blog/eval-skills)

### Semantic Kernel / AutoGPT

Both use function manifests (JSON schema) as the contract between skills and the runtime.
Testing pattern: validate that the tool call arguments produced by the model satisfy the
schema before execution. Reject and retry (up to N times) if they don't. This prevents
malformed calls from reaching the API.

---

## 4. Schema Validation Approaches

### At the call site (runtime guard)

Before executing any AI-generated GraphQL mutation or query argument:
1. Validate the generated variables against the expected JSON schema (jsonschema, Zod, Pydantic)
2. If invalid, return a structured error to the model and allow one retry
3. Log all validation failures — they are your drift signal

OpenAI Structured Outputs with `strict: true` guarantees schema conformance at generation time
(no post-hoc validation needed), but this only applies to OpenAI's function calling, not
arbitrary GraphQL variables.

### GraphQL contract testing

**Pactflow + Pact** is the standard consumer-driven contract testing approach:
- The "consumer" (the agent/skill) defines what fields it expects in the response
- The "provider" (the GraphQL API) must satisfy those contracts in CI
- If the provider removes a field the consumer uses, CI fails before deploy

**graphql-contract-test** (GitHub: symm/graphql-contract-test) is a simpler open source tool
for the same purpose.

**GraphQL Inspector** for breaking-change detection:
```bash
graphql-inspector diff schema-old.graphql schema-new.graphql
```
Run this in CI on every schema change. Output lists breaking changes (removed fields, changed
types) that would break downstream consumers including agent skills.

### Key statistic from 2026 State of Agentic API Testing

72% of GraphQL regressions occur in nested response fields, not at the top level or status
codes. This means field-level contract tests are more valuable than simple "did it respond?"
checks.

---

## 5. Regression Testing When Skill Files Change

### The PR gate pattern

Every change to a skill file triggers an eval run in CI:
1. Eval runs against the full golden dataset for that skill
2. Scores compared to the last-known-good baseline stored in the repo
3. PR is blocked if any score regresses below threshold
4. PR description shows score delta (improved / unchanged / regressed)

Tools: promptfoo + GitHub Actions, or LangSmith CI integration with pytest/Vitest.

### A/B testing skill versions

Claude Code Skills 2.0 supports explicit A/B testing: run the old skill and the new skill
against the same golden set, compare scores side by side. Only promote the new version if it
matches or beats the old one.

Source: [MindStudio: Claude Code Skills 2.0](https://www.mindstudio.ai/blog/claude-code-skills-2-evaluation-ab-testing)

### The "eval from scratch" pattern

Rather than maintaining a complex framework, some teams build evals from first principles:
1. Write a test script that calls the skill with a fixed input
2. Parse the output and check specific assertions (field present, value in expected set, etc.)
3. Pipe results to a JSON file; compare to a baseline JSON in git
4. Diff is the regression report

This is low-overhead and does not require any external platform. Appropriate for Claudriel's
size and architecture.

Source: [Evaluating Claude's dbt Skills: Building an Eval from Scratch](https://rmoff.net/2026/03/13/evaluating-claudes-dbt-skills-building-an-eval-from-scratch/)

---

## Recommended Stack for Claudriel

Given Claudriel's architecture (Claude Code skills as .md files, GraphQL backend, small team):

| Need | Tool / Pattern | Effort |
|---|---|---|
| Skill regression testing | promptfoo + YAML golden tests, one file per skill | Low |
| Rubric grading | LLM-as-judge via promptfoo `llm-rubric` assertion | Low |
| GraphQL schema drift | graphql-inspector diff in CI | Low |
| Field-level contract testing | Pact/Pactflow or graphql-contract-test | Medium |
| Score tracking over time | Store promptfoo JSON output in `docs/reports/eval-scores/` | Low |
| Production drift detection | Online scoring of real chat inputs (LangSmith or custom) | High |

**Minimum viable approach for now:**
1. One `promptfooconfig.yaml` per skill in `.claude/skills/`
2. 5-10 golden test cases per skill (intent variations + edge cases)
3. `graphql-inspector diff` on every schema migration PR
4. Nightly CI job that runs all skill evals and saves scores to a JSON artifact
5. Manual review of score deltas before merging any skill change

---

## Sources

- [Building a Golden Dataset for AI Evaluation](https://www.getmaxim.ai/articles/building-a-golden-dataset-for-ai-evaluation-a-step-by-step-guide/)
- [A pragmatic guide to LLM evals for devs](https://newsletter.pragmaticengineer.com/p/evals)
- [Automated Prompt Regression Testing with LLM-as-a-Judge](https://www.traceloop.com/blog/automated-prompt-regression-testing-with-llm-as-a-judge-and-ci-cd)
- [Unit Tests for AI Agent Skills (Minko Gechev)](https://blog.mgechev.com/2026/02/26/skill-eval/)
- [Testing Agent Skills Systematically with Evals (OpenAI)](https://developers.openai.com/blog/eval-skills)
- [promptfoo GitHub](https://github.com/promptfoo/promptfoo)
- [LangSmith Evaluation Concepts](https://docs.langchain.com/langsmith/evaluation-concepts)
- [Evaluating Deep Agents: LangChain's Learnings](https://blog.langchain.com/evaluating-deep-agents-our-learnings/)
- [Claude Code Skill Docs](https://code.claude.com/docs/en/skills)
- [Improving skill-creator (Anthropic blog)](https://claude.com/blog/improving-skill-creator-test-measure-and-refine-agent-skills)
- [Claude Code Skills 2.0: Evals and A/B Testing (MindStudio)](https://www.mindstudio.ai/blog/claude-code-skills-2-evaluation-ab-testing)
- [Evaluating Claude's dbt Skills from Scratch](https://rmoff.net/2026/03/13/evaluating-claudes-dbt-skills-building-an-eval-from-scratch/)
- [Claude Code: How to Write, Eval, and Iterate on a Skill](https://www.mager.co/blog/2026-03-08-claude-code-eval-loop/)
- [Apollo Skills: Teaching AI Agents to Use GraphQL](https://www.apollographql.com/blog/apollo-skills-teaching-ai-agents-how-to-use-apollo-and-graphql)
- [5 AI Agent Failures in Production](https://dev.to/nebulagg/5-ai-agent-failures-in-production-and-how-to-fix-them-2nm0)
- [Agent Skill Bus (GitHub)](https://github.com/ShunsukeHayashi/agent-skill-bus)
- [API Resiliency and Contract Testing for GraphQL (Specmatic)](https://specmatic.io/api-resiliency-and-contract-testing-for-graphql/)
- [State of Agentic API Testing 2026 (KushoAI)](https://reports.kusho.ai/state-of-agentic-api-testing-2026)
- [Contract Testing for GraphQL (Pactflow)](https://pactflow.io/blog/contract-testing-for-graphql/)
- [graphql-contract-test (GitHub)](https://github.com/symm/graphql-contract-test)
- [Tool Calling testing (Promptfoo docs)](https://www.promptfoo.dev/docs/configuration/tools/)
- [LLM Model Drift: Detect, Prevent, Mitigate](https://byaiteam.com/blog/2025/12/30/llm-model-drift-detect-prevent-and-mitigate-failures/)
- [Ship Prompts Like Software: Regression Testing for LLMs](https://www.anup.io/ship-prompts-like-software-regression-testing-for-llms/)
