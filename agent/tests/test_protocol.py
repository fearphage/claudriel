"""Tests for JSONL protocol validation (terminal envelope, tool pairing)."""

from __future__ import annotations

import json

import pytest

from claudriel_agent.protocol import (
    ProtocolValidationError,
    assert_valid_protocol_stream,
    parse_jsonl_events,
    validate_protocol_events,
)


def test_terminal_done_only() -> None:
    events = [{"event": "message", "content": "x"}, {"event": "done"}]
    validate_protocol_events(events)


def test_rejects_double_done() -> None:
    events = [{"event": "done"}, {"event": "done"}]
    with pytest.raises(ProtocolValidationError, match="exactly one terminal"):
        validate_protocol_events(events)


def test_rejects_done_then_message() -> None:
    events = [{"event": "done"}, {"event": "message", "content": "late"}]
    with pytest.raises(ProtocolValidationError, match="after terminal"):
        validate_protocol_events(events)


def test_tool_pairing_ok() -> None:
    events = [
        {"event": "tool_call", "tool": "gmail_list", "args": {}},
        {"event": "tool_result", "tool": "gmail_list", "result": {}},
        {"event": "done"},
    ]
    validate_protocol_events(events)


def test_tool_result_without_call() -> None:
    events = [
        {"event": "tool_result", "tool": "gmail_list", "result": {}},
        {"event": "done"},
    ]
    with pytest.raises(ProtocolValidationError, match="without preceding tool_call"):
        validate_protocol_events(events)


def test_message_between_call_and_result() -> None:
    events = [
        {"event": "tool_call", "tool": "gmail_list", "args": {}},
        {"event": "message", "content": "oops"},
        {"event": "tool_result", "tool": "gmail_list", "result": {}},
        {"event": "done"},
    ]
    with pytest.raises(ProtocolValidationError, match="between tool_call and tool_result"):
        validate_protocol_events(events)


def test_needs_continuation_before_done() -> None:
    events = [
        {"event": "message", "content": "a"},
        {"event": "tool_call", "tool": "gmail_list", "args": {}},
        {"event": "tool_result", "tool": "gmail_list", "result": {}},
        {"event": "needs_continuation", "turns_consumed": 1, "task_type": "general", "message": "m"},
        {"event": "done"},
    ]
    validate_protocol_events(events)


def test_assert_valid_protocol_stream() -> None:
    lines = json.dumps({"event": "message", "content": "z"}) + "\n" + json.dumps({"event": "done"}) + "\n"
    out = assert_valid_protocol_stream(lines)
    assert len(out) == 2
    assert out[-1]["event"] == "done"


def test_error_terminal() -> None:
    events = [{"event": "error", "message": "bad"}]
    validate_protocol_events(events)


def test_parse_jsonl_events_skips_blank_lines() -> None:
    lines = ["", "  ", '{"event": "done"}']
    events = parse_jsonl_events(lines)
    assert events == [{"event": "done"}]
