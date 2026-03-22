# Eval Framework Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an eval runner that validates skill YAML files deterministically and scores skill outputs with an LLM judge, plus a CI workflow to catch regressions.

**Architecture:** Python eval runner reads `.claude/skills/*/evals/*.yaml`, validates schema (deterministic mode) or sends inputs to Claude and judges outputs (LLM-judge mode). CI workflow triggers on skill file changes, runs evals, posts results on PRs.

**Tech Stack:** Python 3.14, anthropic SDK, PyYAML, GitHub Actions

**Spec:** `docs/superpowers/specs/2026-03-22-eval-framework-design.md`

---

## File Structure

| Action | File | Responsibility |
|--------|------|----------------|
| Create | `agent/eval_runner.py` | CLI orchestrator: parse args, discover evals, dispatch |
| Create | `agent/eval_judge.py` | LLM judge: send to Claude, parse scores |
| Create | `agent/eval_report.py` | Generate JSON + markdown reports |
| Create | `agent/eval_schema.py` | YAML schema validation for deterministic mode |
| Create | `agent/tests/test_eval_schema.py` | Tests for YAML validation |
| Create | `agent/tests/test_eval_judge.py` | Tests for judge prompt + score parsing |
| Create | `agent/tests/test_eval_report.py` | Tests for report generation |
| Create | `agent/tests/test_eval_runner.py` | Integration tests for runner CLI |
| Create | `agent/requirements.txt` | Python dependencies |
| Create | `.github/workflows/skill-evals.yml` | CI workflow |
| Create | `docs/reports/eval-baseline.json` | Initial baseline scores |

---

## Task 1: Python Dependencies

**Files:**
- Create: `agent/requirements.txt`
- Create: `agent/tests/__init__.py`

- [ ] **Step 1: Create requirements.txt**

```
anthropic>=0.40.0
pyyaml>=6.0
```

- [ ] **Step 2: Create test package init**

```bash
mkdir -p agent/tests
touch agent/tests/__init__.py
```

- [ ] **Step 3: Install deps and verify**

```bash
pip install -r agent/requirements.txt
python -c "import yaml; import anthropic; print('OK')"
```

Expected: `OK`

- [ ] **Step 4: Commit**

```bash
git add agent/requirements.txt agent/tests/__init__.py
git commit -m "chore(#445): add eval framework Python dependencies"
```

---

## Task 2: Eval Schema Validator (Deterministic Mode)

**Files:**
- Create: `agent/eval_schema.py`
- Create: `agent/tests/test_eval_schema.py`

- [ ] **Step 1: Write the failing tests**

Create `agent/tests/test_eval_schema.py`:

```python
"""Tests for eval YAML schema validation."""
import pytest
from eval_schema import validate_eval_file, ValidationError, discover_eval_files


def test_valid_basic_eval():
    """A well-formed basic eval file passes validation."""
    data = {
        "schema_version": "1.0",
        "skill": "commitment",
        "entity_type": "commitment",
        "tests": [
            {
                "name": "create-simple",
                "operation": "create",
                "input": "I owe Sarah a proposal",
                "assertions": [
                    {"type": "confirmation_shown"}
                ],
            }
        ],
    }
    errors = validate_eval_file(data, "basic.yaml")
    assert errors == []


def test_missing_required_fields():
    """Missing schema_version or skill fails."""
    data = {"tests": []}
    errors = validate_eval_file(data, "bad.yaml")
    assert any("schema_version" in e.message for e in errors)
    assert any("skill" in e.message for e in errors)


def test_invalid_assertion_type():
    """Unknown assertion type is flagged."""
    data = {
        "schema_version": "1.0",
        "skill": "commitment",
        "entity_type": "commitment",
        "tests": [
            {
                "name": "bad-assertion",
                "operation": "create",
                "input": "test",
                "assertions": [{"type": "nonexistent_type"}],
            }
        ],
    }
    errors = validate_eval_file(data, "test.yaml")
    assert any("nonexistent_type" in e.message for e in errors)


def test_duplicate_test_names():
    """Duplicate test names within a file are flagged."""
    data = {
        "schema_version": "1.0",
        "skill": "commitment",
        "entity_type": "commitment",
        "tests": [
            {"name": "dupe", "operation": "create", "input": "a", "assertions": []},
            {"name": "dupe", "operation": "list", "input": "b", "assertions": []},
        ],
    }
    errors = validate_eval_file(data, "test.yaml")
    assert any("duplicate" in e.message.lower() for e in errors)


def test_test_missing_required_fields():
    """Test case missing name, operation, or input is flagged."""
    data = {
        "schema_version": "1.0",
        "skill": "commitment",
        "entity_type": "commitment",
        "tests": [{"assertions": []}],
    }
    errors = validate_eval_file(data, "test.yaml")
    assert any("name" in e.message for e in errors)


def test_discover_eval_files():
    """discover_eval_files finds YAML files in skill eval dirs."""
    from pathlib import Path
    files = discover_eval_files(Path(".claude/skills"))
    assert len(files) > 0
    assert all(f.suffix in (".yaml", ".yml") for f in files)
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd agent && python -m pytest tests/test_eval_schema.py -v
```

Expected: FAIL — `ModuleNotFoundError: No module named 'eval_schema'`

- [ ] **Step 3: Implement eval_schema.py**

Create `agent/eval_schema.py`:

```python
"""YAML schema validation for eval files."""
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import yaml

VALID_ASSERTION_TYPES = {
    "field_extraction",
    "direction_detected",
    "confirmation_shown",
    "graphql_operation",
    "table_presented",
    "filter_applied",
    "resolve_first",
    "disambiguation",
    "error_surfaced",
    "before_after_shown",
    "asks_for_field",
    "no_conjunction_split",
    "echo_back_required",
    "offers_alternative",
}

REQUIRED_TOP_LEVEL = {"schema_version", "skill", "tests"}
REQUIRED_TEST_FIELDS = {"name", "operation", "input"}


@dataclass
class ValidationError:
    file: str
    message: str
    line: int | None = None


def validate_eval_file(data: dict[str, Any], filename: str) -> list[ValidationError]:
    """Validate a parsed eval YAML structure. Returns list of errors (empty = valid)."""
    errors: list[ValidationError] = []

    for field in REQUIRED_TOP_LEVEL:
        if field not in data:
            errors.append(ValidationError(filename, f"Missing required field: {field}"))

    tests = data.get("tests", [])
    if not isinstance(tests, list):
        errors.append(ValidationError(filename, "tests must be a list"))
        return errors

    seen_names: set[str] = set()
    for i, test in enumerate(tests):
        if not isinstance(test, dict):
            errors.append(ValidationError(filename, f"Test {i} must be a mapping"))
            continue

        for field in REQUIRED_TEST_FIELDS:
            if field not in test:
                errors.append(ValidationError(filename, f"Test {i}: missing required field: {field}"))

        name = test.get("name", "")
        if name in seen_names:
            errors.append(ValidationError(filename, f"Duplicate test name: {name}"))
        seen_names.add(name)

        for assertion in test.get("assertions", []):
            if not isinstance(assertion, dict):
                continue
            atype = assertion.get("type", "")
            if atype not in VALID_ASSERTION_TYPES:
                errors.append(ValidationError(filename, f"Test '{name}': unknown assertion type: {atype}"))

    return errors


def load_and_validate(path: Path) -> list[ValidationError]:
    """Load a YAML file and validate it."""
    with open(path) as f:
        data = yaml.safe_load(f)
    if not isinstance(data, dict):
        return [ValidationError(str(path), "File must contain a YAML mapping")]
    return validate_eval_file(data, path.name)


def discover_eval_files(skills_dir: Path) -> list[Path]:
    """Find all eval YAML files under skill directories."""
    files: list[Path] = []
    for eval_dir in sorted(skills_dir.glob("*/evals")):
        for yaml_file in sorted(eval_dir.glob("*.yaml")):
            files.append(yaml_file)
        for yml_file in sorted(eval_dir.glob("*.yml")):
            files.append(yml_file)
    return files
```

- [ ] **Step 4: Run tests**

```bash
cd agent && python -m pytest tests/test_eval_schema.py -v
```

Expected: All 6 tests pass.

- [ ] **Step 5: Commit**

```bash
git add agent/eval_schema.py agent/tests/test_eval_schema.py
git commit -m "feat(#445): add eval YAML schema validator"
```

---

## Task 3: LLM Judge

**Files:**
- Create: `agent/eval_judge.py`
- Create: `agent/tests/test_eval_judge.py`

- [ ] **Step 1: Write the failing tests**

Create `agent/tests/test_eval_judge.py`:

```python
"""Tests for LLM judge prompt construction and score parsing."""
import json
import pytest
from eval_judge import build_judge_prompt, parse_judge_response, JudgeScore


def test_build_judge_prompt_includes_skill_and_input():
    prompt = build_judge_prompt(
        skill_name="commitment",
        user_input="I owe Sarah a proposal",
        response_text="I'll create a commitment...",
        assertions=[{"type": "confirmation_shown"}, {"type": "direction_detected", "direction": "outbound"}],
    )
    assert "commitment" in prompt
    assert "I owe Sarah a proposal" in prompt
    assert "I'll create a commitment" in prompt
    assert "confirmation_shown" in prompt
    assert "direction_detected" in prompt


def test_build_judge_prompt_formats_assertions_as_rubric():
    prompt = build_judge_prompt(
        skill_name="test",
        user_input="test input",
        response_text="test response",
        assertions=[
            {"type": "field_extraction", "field": "title", "should_match": "Proposal"},
            {"type": "confirmation_shown"},
        ],
    )
    assert "field_extraction" in prompt
    assert "title" in prompt
    assert "Proposal" in prompt


def test_parse_judge_response_valid_json():
    raw = json.dumps({
        "scores": [
            {"assertion": "confirmation_shown", "score": 5, "reason": "Clear confirmation"},
            {"assertion": "direction_detected", "score": 4, "reason": "Correctly outbound"},
        ],
        "overall": 4.5,
    })
    result = parse_judge_response(raw)
    assert len(result.scores) == 2
    assert result.scores[0].score == 5
    assert result.overall == 4.5


def test_parse_judge_response_extracts_json_from_text():
    """Judge may include text around the JSON."""
    raw = 'Here is my evaluation:\n{"scores": [{"assertion": "a", "score": 3, "reason": "ok"}], "overall": 3.0}\nDone.'
    result = parse_judge_response(raw)
    assert result.overall == 3.0


def test_parse_judge_response_invalid():
    """Garbage input returns a zero-score result."""
    result = parse_judge_response("this is not json at all")
    assert result.overall == 0.0
    assert result.error is not None
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd agent && python -m pytest tests/test_eval_judge.py -v
```

Expected: FAIL — `ModuleNotFoundError: No module named 'eval_judge'`

- [ ] **Step 3: Implement eval_judge.py**

Create `agent/eval_judge.py`:

```python
"""LLM judge for scoring skill eval outputs."""
import json
import re
from dataclasses import dataclass, field
from typing import Any

import anthropic

JUDGE_MODEL = "claude-haiku-4-5-20251001"


@dataclass
class AssertionScore:
    assertion: str
    score: float
    reason: str


@dataclass
class JudgeScore:
    scores: list[AssertionScore] = field(default_factory=list)
    overall: float = 0.0
    error: str | None = None


def build_judge_prompt(
    skill_name: str,
    user_input: str,
    response_text: str,
    assertions: list[dict[str, Any]],
) -> str:
    """Build the judge evaluation prompt."""
    rubric_lines = []
    for i, assertion in enumerate(assertions, 1):
        parts = [f"{i}. Type: {assertion['type']}"]
        for key, value in assertion.items():
            if key != "type":
                parts.append(f"   {key}: {value}")
        rubric_lines.append("\n".join(parts))

    rubric = "\n".join(rubric_lines)

    return f"""You are evaluating a Claudriel skill response for correctness and quality.

Skill: {skill_name}
User input: {user_input}

Skill response:
{response_text}

Evaluate against these criteria:
{rubric}

For each criterion, score 0-5:
0 = completely wrong or missing
1 = attempted but mostly wrong
2 = partially correct with significant issues
3 = mostly correct with minor issues
4 = correct with trivial issues
5 = perfect

Return ONLY valid JSON (no other text):
{{"scores": [{{"assertion": "<type>", "score": <N>, "reason": "<brief>"}}], "overall": <N.N>}}"""


def parse_judge_response(raw: str) -> JudgeScore:
    """Parse judge response, extracting JSON from potential surrounding text."""
    # Try direct parse first
    try:
        data = json.loads(raw.strip())
        return _build_score(data)
    except json.JSONDecodeError:
        pass

    # Try to find JSON in text
    match = re.search(r'\{.*"scores".*"overall".*\}', raw, re.DOTALL)
    if match:
        try:
            data = json.loads(match.group())
            return _build_score(data)
        except json.JSONDecodeError:
            pass

    return JudgeScore(error=f"Could not parse judge response: {raw[:200]}")


def _build_score(data: dict) -> JudgeScore:
    scores = []
    for s in data.get("scores", []):
        scores.append(AssertionScore(
            assertion=s.get("assertion", ""),
            score=float(s.get("score", 0)),
            reason=s.get("reason", ""),
        ))
    return JudgeScore(scores=scores, overall=float(data.get("overall", 0.0)))


def judge_response(
    skill_name: str,
    user_input: str,
    response_text: str,
    assertions: list[dict[str, Any]],
    model: str = JUDGE_MODEL,
) -> JudgeScore:
    """Send a skill response to the LLM judge for scoring."""
    client = anthropic.Anthropic()
    prompt = build_judge_prompt(skill_name, user_input, response_text, assertions)

    response = client.messages.create(
        model=model,
        max_tokens=1024,
        messages=[{"role": "user", "content": prompt}],
    )

    raw = response.content[0].text
    return parse_judge_response(raw)
```

- [ ] **Step 4: Run tests**

```bash
cd agent && python -m pytest tests/test_eval_judge.py -v
```

Expected: All 5 tests pass.

- [ ] **Step 5: Commit**

```bash
git add agent/eval_judge.py agent/tests/test_eval_judge.py
git commit -m "feat(#445): add LLM judge scoring module"
```

---

## Task 4: Report Generator

**Files:**
- Create: `agent/eval_report.py`
- Create: `agent/tests/test_eval_report.py`

- [ ] **Step 1: Write the failing tests**

Create `agent/tests/test_eval_report.py`:

```python
"""Tests for eval report generation."""
import json
from eval_report import generate_report, format_markdown, SkillResult, TestResult


def test_generate_report_structure():
    results = {
        "commitment": SkillResult(
            tests_run=10,
            tests_passed=9,
            average_score=4.2,
            failures=[TestResult(name="edge-case", score=1.0, reason="missed")],
        ),
    }
    report = generate_report(results)
    assert report["totals"]["tests_run"] == 10
    assert report["totals"]["tests_passed"] == 9
    assert report["totals"]["pass_rate"] == 0.9
    assert "commitment" in report["skills"]


def test_generate_report_empty():
    report = generate_report({})
    assert report["totals"]["tests_run"] == 0
    assert report["totals"]["pass_rate"] == 0.0


def test_format_markdown_includes_summary():
    results = {
        "commitment": SkillResult(tests_run=5, tests_passed=5, average_score=4.5, failures=[]),
    }
    report = generate_report(results)
    md = format_markdown(report)
    assert "commitment" in md
    assert "5/5" in md or "100%" in md


def test_format_markdown_shows_failures():
    results = {
        "commitment": SkillResult(
            tests_run=3,
            tests_passed=1,
            average_score=2.0,
            failures=[
                TestResult(name="test-a", score=1.0, reason="bad"),
                TestResult(name="test-b", score=0.0, reason="wrong"),
            ],
        ),
    }
    report = generate_report(results)
    md = format_markdown(report)
    assert "test-a" in md
    assert "test-b" in md
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd agent && python -m pytest tests/test_eval_report.py -v
```

Expected: FAIL — `ModuleNotFoundError`

- [ ] **Step 3: Implement eval_report.py**

Create `agent/eval_report.py`:

```python
"""Eval report generation in JSON and markdown formats."""
from dataclasses import dataclass, field
from datetime import datetime, timezone
from typing import Any


@dataclass
class TestResult:
    name: str
    score: float
    reason: str


@dataclass
class SkillResult:
    tests_run: int
    tests_passed: int
    average_score: float
    failures: list[TestResult] = field(default_factory=list)


def generate_report(results: dict[str, SkillResult], mode: str = "llm-judge") -> dict[str, Any]:
    """Generate a structured report from skill results."""
    total_run = sum(r.tests_run for r in results.values())
    total_passed = sum(r.tests_passed for r in results.values())

    skills_data = {}
    for name, result in sorted(results.items()):
        skills_data[name] = {
            "tests_run": result.tests_run,
            "tests_passed": result.tests_passed,
            "average_score": round(result.average_score, 2),
            "failures": [
                {"test": f.name, "score": f.score, "reason": f.reason}
                for f in result.failures
            ],
        }

    return {
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "mode": mode,
        "skills": skills_data,
        "totals": {
            "tests_run": total_run,
            "tests_passed": total_passed,
            "pass_rate": round(total_passed / total_run, 3) if total_run > 0 else 0.0,
        },
    }


def format_markdown(report: dict[str, Any]) -> str:
    """Format a report as markdown for PR comments."""
    lines = [
        f"## Skill Eval Report ({report['mode']})",
        "",
        f"**Total:** {report['totals']['tests_passed']}/{report['totals']['tests_run']} passed "
        f"({report['totals']['pass_rate'] * 100:.1f}%)",
        "",
        "| Skill | Passed | Score | Status |",
        "|-------|--------|-------|--------|",
    ]

    for name, data in report["skills"].items():
        status = "pass" if data["tests_passed"] == data["tests_run"] else "FAIL"
        lines.append(
            f"| {name} | {data['tests_passed']}/{data['tests_run']} "
            f"| {data['average_score']:.1f} | {status} |"
        )

    # Show failures
    all_failures = []
    for name, data in report["skills"].items():
        for f in data["failures"]:
            all_failures.append((name, f))

    if all_failures:
        lines.extend(["", "### Failures", ""])
        for skill, failure in all_failures:
            lines.append(f"- **{skill}/{failure['test']}** (score: {failure['score']}): {failure['reason']}")

    return "\n".join(lines)
```

- [ ] **Step 4: Run tests**

```bash
cd agent && python -m pytest tests/test_eval_report.py -v
```

Expected: All 4 tests pass.

- [ ] **Step 5: Commit**

```bash
git add agent/eval_report.py agent/tests/test_eval_report.py
git commit -m "feat(#445): add eval report generator"
```

---

## Task 5: Eval Runner CLI

**Files:**
- Create: `agent/eval_runner.py`
- Create: `agent/tests/test_eval_runner.py`

- [ ] **Step 1: Write the failing tests**

Create `agent/tests/test_eval_runner.py`:

```python
"""Tests for the eval runner CLI orchestrator."""
import json
from pathlib import Path
from unittest.mock import patch
from eval_runner import run_deterministic, parse_args


def test_parse_args_deterministic():
    args = parse_args(["--deterministic"])
    assert args.deterministic is True
    assert args.llm_judge is False


def test_parse_args_llm_judge_with_skill():
    args = parse_args(["--llm-judge", "--skill", "commitment"])
    assert args.llm_judge is True
    assert args.skill == "commitment"


def test_parse_args_default_skills_dir():
    args = parse_args(["--deterministic"])
    assert args.skills_dir == ".claude/skills"


def test_run_deterministic_on_real_evals():
    """Run deterministic validation against the actual eval files."""
    results = run_deterministic(Path(".claude/skills"))
    # Should find eval files and validate them
    assert results["totals"]["tests_run"] > 0
    # Existing evals should be valid
    assert results["totals"]["tests_passed"] == results["totals"]["tests_run"]
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd agent && python -m pytest tests/test_eval_runner.py -v
```

Expected: FAIL — `ModuleNotFoundError`

- [ ] **Step 3: Implement eval_runner.py**

Create `agent/eval_runner.py`:

```python
#!/usr/bin/env python3
"""Eval runner CLI for Claudriel skill evaluations.

Usage:
    python agent/eval_runner.py --deterministic
    python agent/eval_runner.py --llm-judge [--skill NAME] [--type TYPE]
"""
import argparse
import json
import sys
from pathlib import Path

import yaml

from eval_schema import discover_eval_files, load_and_validate
from eval_judge import judge_response
from eval_report import generate_report, format_markdown, SkillResult, TestResult

PASS_THRESHOLD = 3.0  # Minimum score to count as pass


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Claudriel skill eval runner")
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--deterministic", action="store_true", help="Schema validation only")
    mode.add_argument("--llm-judge", action="store_true", help="Full LLM-judge evaluation")
    parser.add_argument("--skill", type=str, help="Run evals for specific skill only")
    parser.add_argument("--type", type=str, choices=["basic", "trajectory", "multi-turn"], help="Run specific eval type")
    parser.add_argument("--skills-dir", type=str, default=".claude/skills", help="Skills directory")
    parser.add_argument("--output", type=str, help="Write JSON report to file")
    parser.add_argument("--markdown", action="store_true", help="Print markdown summary to stdout")
    return parser.parse_args(argv)


def run_deterministic(skills_dir: Path) -> dict:
    """Run deterministic validation on all eval files."""
    files = discover_eval_files(skills_dir)
    results: dict[str, SkillResult] = {}

    for eval_file in files:
        skill_name = eval_file.parent.parent.name
        errors = load_and_validate(eval_file)

        if skill_name not in results:
            results[skill_name] = SkillResult(tests_run=0, tests_passed=0, average_score=5.0, failures=[])

        results[skill_name].tests_run += 1
        if errors:
            for error in errors:
                results[skill_name].failures.append(TestResult(
                    name=f"{eval_file.name}:{error.message}",
                    score=0.0,
                    reason=error.message,
                ))
        else:
            results[skill_name].tests_passed += 1

    return generate_report(results, mode="deterministic")


def run_llm_judge(skills_dir: Path, skill_filter: str | None = None, type_filter: str | None = None) -> dict:
    """Run LLM-judge evaluation on eval files."""
    files = discover_eval_files(skills_dir)
    results: dict[str, SkillResult] = {}

    for eval_file in files:
        skill_name = eval_file.parent.parent.name
        if skill_filter and skill_name != skill_filter:
            continue
        if type_filter and type_filter not in eval_file.stem:
            continue

        with open(eval_file) as f:
            data = yaml.safe_load(f)

        if not isinstance(data, dict):
            continue

        # Read the skill's SKILL.md for system prompt context
        skill_md = eval_file.parent.parent / "SKILL.md"
        skill_context = skill_md.read_text() if skill_md.exists() else f"Skill: {skill_name}"

        subject_model = data.get("subject_model", "claude-sonnet-4-6")

        if skill_name not in results:
            results[skill_name] = SkillResult(tests_run=0, tests_passed=0, average_score=0.0, failures=[])

        scores_sum = 0.0
        for test in data.get("tests", []):
            results[skill_name].tests_run += 1
            test_name = test.get("name", "unnamed")
            user_input = test.get("input", "")
            assertions = test.get("assertions", [])

            # Get skill response from subject model
            import anthropic
            client = anthropic.Anthropic()
            response = client.messages.create(
                model=subject_model,
                max_tokens=2048,
                system=skill_context,
                messages=[{"role": "user", "content": user_input}],
            )
            response_text = response.content[0].text

            # Judge the response
            score = judge_response(skill_name, user_input, response_text, assertions)

            scores_sum += score.overall
            if score.overall >= PASS_THRESHOLD:
                results[skill_name].tests_passed += 1
            else:
                results[skill_name].failures.append(TestResult(
                    name=test_name,
                    score=score.overall,
                    reason="; ".join(s.reason for s in score.scores if s.score < PASS_THRESHOLD),
                ))

        if results[skill_name].tests_run > 0:
            results[skill_name].average_score = scores_sum / results[skill_name].tests_run

    return generate_report(results, mode="llm-judge")


def main() -> None:
    args = parse_args()
    skills_dir = Path(args.skills_dir)

    if args.deterministic:
        report = run_deterministic(skills_dir)
    else:
        report = run_llm_judge(skills_dir, args.skill, args.type)

    if args.output:
        Path(args.output).write_text(json.dumps(report, indent=2))
        print(f"Report written to {args.output}", file=sys.stderr)

    if args.markdown:
        print(format_markdown(report))
    else:
        print(json.dumps(report, indent=2))

    # Exit with error if failures
    if report["totals"]["tests_passed"] < report["totals"]["tests_run"]:
        sys.exit(1)


if __name__ == "__main__":
    main()
```

- [ ] **Step 4: Run tests**

```bash
cd agent && python -m pytest tests/test_eval_runner.py -v
```

Expected: All 4 tests pass.

- [ ] **Step 5: Run deterministic mode against real evals**

```bash
cd /home/jones/dev/claudriel && python agent/eval_runner.py --deterministic --markdown
```

Expected: Markdown table showing all skills with eval files passing validation.

- [ ] **Step 6: Commit**

```bash
git add agent/eval_runner.py agent/tests/test_eval_runner.py
git commit -m "feat(#445): add eval runner CLI with deterministic and LLM-judge modes"
```

---

## Task 6: CI Workflow

**Files:**
- Create: `.github/workflows/skill-evals.yml`
- Create: `docs/reports/eval-baseline.json`

- [ ] **Step 1: Create the CI workflow**

Create `.github/workflows/skill-evals.yml`:

```yaml
name: Skill Evals

on:
  pull_request:
    paths:
      - '.claude/skills/**'
  schedule:
    - cron: '0 6 * * *'  # Daily at 6am UTC
  workflow_dispatch:

jobs:
  deterministic:
    name: Deterministic Eval Validation
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-python@v5
        with:
          python-version: '3.14'

      - name: Install dependencies
        run: pip install -r agent/requirements.txt

      - name: Run deterministic evals
        working-directory: ${{ github.workspace }}
        run: python agent/eval_runner.py --deterministic --markdown

  llm-judge:
    name: LLM Judge Evaluation
    runs-on: ubuntu-latest
    if: github.event_name == 'schedule' || github.event_name == 'workflow_dispatch'
    env:
      ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-python@v5
        with:
          python-version: '3.14'

      - name: Install dependencies
        run: pip install -r agent/requirements.txt

      - name: Run LLM judge evals
        working-directory: ${{ github.workspace }}
        run: python agent/eval_runner.py --llm-judge --output eval-results.json --markdown

      - name: Upload eval results
        uses: actions/upload-artifact@v4
        with:
          name: eval-results-${{ github.run_id }}
          path: eval-results.json
          retention-days: 90
```

- [ ] **Step 2: Create initial baseline**

```bash
cd /home/jones/dev/claudriel && python agent/eval_runner.py --deterministic --output docs/reports/eval-baseline.json
```

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/skill-evals.yml docs/reports/eval-baseline.json
git commit -m "feat(#446): add skill eval CI workflow with deterministic gate and nightly LLM-judge"
```

---

## Task 7: Final Verification and Issue Closure

- [ ] **Step 1: Run full Python test suite**

```bash
cd agent && python -m pytest tests/ -v
```

Expected: All tests pass.

- [ ] **Step 2: Run deterministic evals end-to-end**

```bash
cd /home/jones/dev/claudriel && python agent/eval_runner.py --deterministic --markdown
```

Expected: All eval files pass schema validation.

- [ ] **Step 3: Run PHP test suite (no regressions)**

```bash
vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 4: Push and close issues**

```bash
git push origin main
gh issue close 445 --comment "LLM-judge eval framework implemented: eval_runner.py, eval_judge.py, eval_report.py with tests."
gh issue close 446 --comment "CI workflow at .github/workflows/skill-evals.yml: deterministic gate on PRs, nightly LLM-judge runs."
```
