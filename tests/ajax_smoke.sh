#!/usr/bin/env bash
#
# Smoke-tests that a real Moodle AJAX (external API) HTTP response stays
# valid, uncorrupted JSON with Tracy enabled.
#
# This is NOT a PHPUnit test and cannot be one: PHPUnit runs as CLI SAPI,
# and Tracy\Helpers::isHtmlMode() — the method that decides whether Tracy
# injects the debug bar/bluescreen into a response — has a hard `!isCli()`
# condition and unconditionally returns false under CLI, regardless of what
# headers or superglobals a test fakes. The only way to verify this
# mechanism is a real HTTP request/response cycle against a running site.
# See tests/bootstrap_test.php for what IS covered at the PHPUnit level
# (our own, narrower PHP_SAPI check that disables the bar for CLI scripts).
#
# Requires: curl, python3 (for JSON validation), a running Moodle site
# reachable at MOODLE_URL with Tracy enabled and low-level or high-level
# activation in effect.
#
# Usage:
#   MOODLE_URL=http://localhost:8000 ./tests/ajax_smoke.sh

set -euo pipefail

url="${MOODLE_URL:-http://localhost:8000}/lib/ajax/service-nologin.php?info=core_get_string"
payload='[{"index":0,"methodname":"core_get_string","args":{"stringid":"cancel","component":"core"}}]'
headers_file=$(mktemp)
body_file=$(mktemp)
trap 'rm -f "$headers_file" "$body_file"' EXIT

check() {
    local label="$1"
    shift

    curl -sS -D "$headers_file" -o "$body_file" \
        "$url" -H "Content-Type: application/json" -X POST -d "$payload" "$@"

    local status
    status=$(head -1 "$headers_file" | grep -oE '[0-9]{3}' | head -1)
    if [[ "$status" != "200" ]]; then
        echo "FAIL ($label): expected HTTP 200, got $status"
        cat "$headers_file"
        exit 1
    fi

    local content_type
    content_type=$(grep -i '^content-type:' "$headers_file" | tr -d '\r')
    if [[ "$content_type" != *"application/json"* ]]; then
        echo "FAIL ($label): expected Content-Type: application/json, got: $content_type"
        echo "--- body ---"
        cat "$body_file"
        exit 1
    fi

    if ! python3 -c 'import json,sys; json.load(open(sys.argv[1]))' "$body_file" 2>/dev/null; then
        echo "FAIL ($label): response body is not valid JSON (Tracy likely injected HTML into it)"
        echo "--- body ---"
        cat "$body_file"
        exit 1
    fi

    echo "OK ($label)"
}

check "with X-Requested-With (jQuery/YUI-style AJAX)" -H "X-Requested-With: XMLHttpRequest"
check "without X-Requested-With (plain fetch())"

echo "All AJAX smoke checks passed."
