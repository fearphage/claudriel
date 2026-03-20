"""Tests for dynamic agent tool discovery."""

from __future__ import annotations

import importlib
import sys
import textwrap
import types
from pathlib import Path
from unittest.mock import MagicMock

import pytest


def _stub_main_dependencies() -> None:
    for mod_name in [
        "anthropic",
        "util.http",
        "tools.gmail_list",
        "tools.gmail_read",
        "tools.gmail_send",
        "tools.calendar_list",
        "tools.calendar_create",
        "tools.judgment_rule_suggest",
        "tools.commitment_list",
        "tools.commitment_update",
        "tools.person_search",
        "tools.person_detail",
        "tools.brief_generate",
        "tools.event_search",
        "tools.search_global",
        "tools.workspace_list",
        "tools.workspace_context",
        "tools.schedule_query",
        "tools.triage_list",
        "tools.triage_resolve",
    ]:
        if mod_name in sys.modules:
            continue
        stub = types.ModuleType(mod_name)
        stub.TOOL_DEF = {"name": mod_name.split(".")[-1], "input_schema": {"type": "object", "properties": {}}}
        stub.execute = lambda api, args: {}
        stub.PhpApiClient = MagicMock
        sys.modules[mod_name] = stub


_stub_main_dependencies()
main = importlib.import_module("main")


def _write_tool(path: Path, body: str) -> None:
    path.write_text(textwrap.dedent(body), encoding="utf-8")


def test_discover_tools_loads_valid_modules_in_sorted_order(tmp_path, monkeypatch):
    package_dir = tmp_path / "dynamic_tools"
    package_dir.mkdir()
    _write_tool(package_dir / "__init__.py", "")
    _write_tool(
        package_dir / "z_last.py",
        """
        TOOL_DEF = {"name": "z_last", "description": "z", "input_schema": {"type": "object", "properties": {}}}
        def execute(api, args):
            return {"tool": "z_last"}
        """,
    )
    _write_tool(
        package_dir / "a_first.py",
        """
        TOOL_DEF = {"name": "a_first", "description": "a", "input_schema": {"type": "object", "properties": {}}}
        def execute(api, args):
            return {"tool": "a_first"}
        """,
    )
    _write_tool(package_dir / "skip_me.py", "TOOL_DEF = {'name': 'skip_me'}")

    monkeypatch.syspath_prepend(str(tmp_path))

    tools, executors = main.discover_tools(package_name="dynamic_tools", package_path=package_dir)

    assert [tool["name"] for tool in tools] == ["a_first", "z_last"]
    assert sorted(executors.keys()) == ["a_first", "z_last"]


def test_discover_tools_respects_allowlist(tmp_path, monkeypatch):
    package_dir = tmp_path / "dynamic_tools_filter"
    package_dir.mkdir()
    _write_tool(package_dir / "__init__.py", "")
    _write_tool(
        package_dir / "alpha.py",
        """
        TOOL_DEF = {"name": "alpha", "description": "alpha", "input_schema": {"type": "object", "properties": {}}}
        def execute(api, args):
            return {"tool": "alpha"}
        """,
    )
    _write_tool(
        package_dir / "beta.py",
        """
        TOOL_DEF = {"name": "beta", "description": "beta", "input_schema": {"type": "object", "properties": {}}}
        def execute(api, args):
            return {"tool": "beta"}
        """,
    )

    monkeypatch.syspath_prepend(str(tmp_path))

    tools, executors = main.discover_tools(
        package_name="dynamic_tools_filter",
        package_path=package_dir,
        enabled_tool_names={"beta"},
    )

    assert [tool["name"] for tool in tools] == ["beta"]
    assert list(executors.keys()) == ["beta"]


def test_discover_tools_rejects_duplicate_tool_names(tmp_path, monkeypatch):
    package_dir = tmp_path / "dynamic_tools_dupe"
    package_dir.mkdir()
    _write_tool(package_dir / "__init__.py", "")
    _write_tool(
        package_dir / "one.py",
        """
        TOOL_DEF = {"name": "dupe", "description": "first", "input_schema": {"type": "object", "properties": {}}}
        def execute(api, args):
            return {"tool": "one"}
        """,
    )
    _write_tool(
        package_dir / "two.py",
        """
        TOOL_DEF = {"name": "dupe", "description": "second", "input_schema": {"type": "object", "properties": {}}}
        def execute(api, args):
            return {"tool": "two"}
        """,
    )

    monkeypatch.syspath_prepend(str(tmp_path))

    with pytest.raises(ValueError, match="Duplicate tool name: dupe"):
        main.discover_tools(package_name="dynamic_tools_dupe", package_path=package_dir)
