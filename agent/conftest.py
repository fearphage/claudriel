"""Pytest configuration: add agent directory to Python path."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
