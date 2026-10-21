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
#
# When E2E_DATA_FILE is set, the fixture goes to that file instead of the
# developer database: nothing is backed up, restored or removed — the caller
# owns that file for the whole round and keeps it afterwards (KAN-125/145).
# When no DISPLAY is available the Cypress run gets its own Xvfb display,
# seeded from the port, so parallel invocations never contend for :99.
set -uo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PORT="${E2E_PORT:-5173}"
SPEC_COUNT="$(find "$DIR/cypress/e2e" -name '*.cy.js' | wc -l | tr -d ' ')"
DEFAULT_TIMEOUT=$((120 + 60 * SPEC_COUNT))
TIMEOUT="${E2E_TIMEOUT:-$DEFAULT_TIMEOUT}"
BASE="http://127.0.0.1:$PORT"
LOG="$(mktemp)"
DATA_FILE="${E2E_DATA_FILE:-$DIR/.data/db.json}"
EPHEMERAL=0
mkdir -p "$(dirname "$DATA_FILE")"
BACKUP="$(mktemp "$(dirname "$DATA_FILE")/backup.XXXXXX" 2>/dev/null || mktemp)"
HAD_DATABASE=0
SERVER_PID=""
CYPRESS_PID=""
XVFB_PID=""
DISPLAY_NUMBER=""
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
  if [ -n "$XVFB_PID" ]; then
    kill "$XVFB_PID" 2>/dev/null || true
    wait "$XVFB_PID" 2>/dev/null || true
  fi
  if [ "$EPHEMERAL" != "1" ]; then
    if [ "$HAD_DATABASE" = "1" ]; then
      RESTORE="$(mktemp "$(dirname "$DATA_FILE")/restore.XXXXXX" 2>/dev/null || mktemp)"
      cp "$BACKUP" "$RESTORE"
      mv "$RESTORE" "$DATA_FILE"
    else
      rm -f "$DATA_FILE"
    fi
  fi
  rm -f "$LOG" "$BACKUP" "$CYPRESS_LOG"
}
trap cleanup EXIT INT TERM

if [ "${E2E_DATA_FILE:+set}" = "set" ]; then
  EPHEMERAL=1
elif [ -f "$DATA_FILE" ]; then
  cp "$DATA_FILE" "$BACKUP"
  HAD_DATABASE=1
fi

SEED="$(mktemp "$(dirname "$DATA_FILE")/seed.XXXXXX" 2>/dev/null || mktemp)"
if node --input-type=module -e "import { createSeed } from './src/data/seed.js'; const database = createSeed(); delete database.user; process.stdout.write(JSON.stringify(database, null, 2))" >"$SEED"; then
  mv "$SEED" "$DATA_FILE"
else
  rm -f "$SEED"
  echo "could not prepare the legacy database fixture" >&2
  exit 1
fi

port_is_busy() {
  (exec 3<>"/dev/tcp/127.0.0.1/$PORT") 2>/dev/null && return 0
  return 1
}

if port_is_busy; then
  echo "port $PORT is already in use: the suite would measure another tree. Set E2E_PORT to a free port." >&2
  exit 1
fi

./node_modules/.bin/vite --port "$PORT" --strictPort --host 127.0.0.1 >"$LOG" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 60); do
  curl -s -o /dev/null "$BASE/" && break
  sleep 0.5
done

if ! kill -0 "$SERVER_PID" 2>/dev/null; then
  echo "dev server process died before answering on $BASE:" >&2
  cat "$LOG" >&2
  exit 1
fi

if ! curl -s -o /dev/null "$BASE/"; then
  echo "dev server did not start on $BASE:" >&2
  cat "$LOG" >&2
  exit 1
fi

if [ -z "${DISPLAY:-}" ] && command -v Xvfb >/dev/null 2>&1; then
  for _ in 1 2 3 4 5; do
    DISPLAY_NUMBER=$((PORT % 70 + 20))
    while [ -e "/tmp/.X$DISPLAY_NUMBER-lock" ]; do
      DISPLAY_NUMBER=$((DISPLAY_NUMBER + 1))
    done
    Xvfb ":$DISPLAY_NUMBER" -screen 0 1280x1024x24 >/dev/null 2>&1 &
    XVFB_PID=$!
    for _ in $(seq 1 20); do
      if [ -e "/tmp/.X$DISPLAY_NUMBER-lock" ]; then
        break
      fi
      sleep 0.1
    done
    if kill -0 "$XVFB_PID" 2>/dev/null && [ -e "/tmp/.X$DISPLAY_NUMBER-lock" ]; then
      break
    fi
    kill "$XVFB_PID" 2>/dev/null || true
    XVFB_PID=""
    DISPLAY_NUMBER=""
    sleep 0.2
  done
  if [ -n "$DISPLAY_NUMBER" ]; then
    export DISPLAY=":$DISPLAY_NUMBER"
  fi
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
