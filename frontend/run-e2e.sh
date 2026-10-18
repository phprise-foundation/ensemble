#!/usr/bin/env bash
# Starts the dev server, runs the Cypress E2E suite against it, then stops it.
# Extra arguments are forwarded to `cypress run` (e.g. --browser chrome).
# The Cypress run is capped by E2E_TIMEOUT so it can never hang forever, and
# cleanup kills both the dev server and the Cypress runner on any exit.
set -uo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PORT="${E2E_PORT:-5173}"
TIMEOUT="${E2E_TIMEOUT:-300}"
BASE="http://127.0.0.1:$PORT"
LOG="$(mktemp)"
cd "$DIR"

./node_modules/.bin/vite --port "$PORT" --strictPort --host 127.0.0.1 >"$LOG" 2>&1 &
SERVER_PID=$!
CYPRESS_PID=""
cleanup() {
  if [ -n "$CYPRESS_PID" ]; then
    kill "$CYPRESS_PID" 2>/dev/null || true
  fi
  kill "$SERVER_PID" 2>/dev/null || true
  wait "$SERVER_PID" 2>/dev/null || true
  rm -f "$LOG"
}
trap cleanup EXIT INT TERM

for _ in $(seq 1 60); do
  curl -s -o /dev/null "$BASE/" && break
  sleep 0.5
done

if ! curl -s -o /dev/null "$BASE/"; then
  echo "dev server did not start on $BASE:" >&2
  cat "$LOG" >&2
  exit 1
fi

CYPRESS_BASE_URL="$BASE" timeout -k 10 "$TIMEOUT" ./node_modules/.bin/cypress run "$@" &
CYPRESS_PID=$!
wait "$CYPRESS_PID"
CYPRESS_PID=""
