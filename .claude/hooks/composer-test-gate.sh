#!/usr/bin/env bash
#
# Stop hook: refuse to finish the task until `composer test` is fully green.
#
# `composer test` runs the whole QA gate (config:clear, pint --test, phpstan,
# artisan test). If any step fails, this hook blocks the Stop event and feeds
# the failing output back to Claude so it keeps working. It only lets the turn
# end once every step passes.

set -uo pipefail

# Resolve the project root. Claude Code sets CLAUDE_PROJECT_DIR for hooks;
# fall back to this script's location (<project>/.claude/hooks/) if it is unset.
project_dir="${CLAUDE_PROJECT_DIR:-}"
if [ -z "$project_dir" ]; then
    script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    project_dir="$(cd "$script_dir/../.." && pwd)"
fi
cd "$project_dir" || exit 0

output="$(composer test 2>&1)"
status=$?

if [ "$status" -eq 0 ]; then
    # Everything is green — allow the task to finish.
    exit 0
fi

reason="$(printf '%s\n' \
    "\`composer test\` did not pass (exit code ${status}). The task cannot be" \
    "finished until it is fully green. Fix the failing lint / types / tests and" \
    "let this hook re-run." \
    "" \
    "----- composer test output (tail) -----" \
    "$(printf '%s\n' "$output" | tail -n 80)")"

jq -n --arg reason "$reason" '{decision: "block", reason: $reason}'
exit 0
