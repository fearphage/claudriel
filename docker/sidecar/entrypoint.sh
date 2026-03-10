#!/bin/sh
# Copy read-only Claude config to a writable location so Claude Code
# can write working state (session files, tool output, etc.)
if [ -d /root/.claude-config ]; then
    cp -a /root/.claude-config/. /root/.claude/
fi

exec "$@"
