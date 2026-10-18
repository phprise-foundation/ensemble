#!/usr/bin/env bash
# Starts the dev server, runs the Cypress E2E suite against it, then stops it.
# Extra arguments are forwarded to `cypress run` (e.g. --browser chrome).
# The Cypress run is capped by E2E_TIMEOUT so it can never hang forever, and
# cleanup kills both the dev server and the Cypress runner on any exit.
#
# The dev server boots from a LEGACY .data/db.json (no `user` key, added in
# KAN-32): the suite must prove the fake backend backfills new seed keys on
# boot, not only on a clean clone. The developer database is backed up before
# and restored after the run.
set -uo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PORT="${E2E_PORT:-5173}"
TIMEOUT="${E2E_TIMEOUT:-300}"
BASE="http://127.0.0.1:$PORT"
LOG="$(mktemp)"
BACKUP="$(mktemp)"
DATA_FILE="$DIR/.data/db.json"
HAD_DATABASE=0
SERVER_PID=""
CYPRESS_PID=""
CYPRESS_LOG="$(mktemp)"
cd "$DIR"

cleanup() {
  if [ -n "$CYPRESS_PID" ]; then
    kill "$CYPRESS_PID" 2>/dev/null || true
  fi
  if [ -n "$SERVER_PID" ]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  if [ "$HAD_DATABASE" = "1" ]; then
    cp "$BACKUP" "$DATA_FILE"
  else
    rm -f "$DATA_FILE"
  fi
  rm -f "$LOG" "$BACKUP" "$CYPRESS_LOG"
}
trap cleanup EXIT INT TERM

if [ -f "$DATA_FILE" ]; then
  cp "$DATA_FILE" "$BACKUP"
  HAD_DATABASE=1
fi
mkdir -p "$DIR/.data"
node --input-type=module -e "import { createSeed } from './src/data/seed.js'; const database = createSeed(); delete database.user; process.stdout.write(JSON.stringify(database, null, 2))" >"$DATA_FILE" || {
  echo "could not prepare the legacy database fixture" >&2
  exit 1
}

./node_modules/.bin/vite --port "$PORT" --strictPort --host 127.0.0.1 >"$LOG" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 60); do
  curl -s -o /dev/null "$BASE/" && break
  sleep 0.5
done

if ! curl -s -o /dev/null "$BASE/"; then
  echo "dev server did not start on $BASE:" >&2
  cat "$LOG" >&2
  exit 1
fi

CYPRESS_BASE_URL="$BASE" timeout -k 10 "$TIMEOUT" ./node_modules/.bin/cypress run "$@" 2>&1 | tee "$CYPRESS_LOG" &
CYPRESS_PID=$!
wait "$CYPRESS_PID"
STATUS=$?
CYPRESS_PID=""
if [ "$STATUS" -eq 0 ] && grep -Eqi 'no spec files( were)? found' "$CYPRESS_LOG"; then
  STATUS=1
fi
exit "$STATUS"
