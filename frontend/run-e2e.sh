#!/usr/bin/env bash
# Starts the dev server, runs the Cypress E2E suite against it, then stops it.
# Extra arguments are forwarded to `cypress run` (e.g. --browser chrome).
# The Cypress run is capped by E2E_TIMEOUT so it can never hang forever, and
# cleanup kills both the dev server and the Cypress runner on any exit.
#
# The dev server boots from a LEGACY database (no `user` key, added in
# KAN-32): the suite must prove the fake backend backfills new seed keys on
# boot, not only on a clean clone. Two modes:
#
# - E2E_DATA_FILE set (absolute path): the legacy seed goes there, and neither
#   `.data/db.json` nor the file itself is backed up, restored or removed. The
#   caller owns the file (KAN-125/KAN-145).
# - E2E_DATA_FILE unset: the seed goes to `.data/db.json`, which is backed up
#   before and restored after the run, as it always was.
#
# With E2E_PORT unset a random free-ish port is picked instead of 5173, so a
# developer server or another suite on 5173 no longer breaks the run.
#
# A foreign DISPLAY makes Electron share the Cypress cache with other runners
# and die on `SingletonLock`, so every run takes an Xvfb server number of its
# own (KAN-125).
#
# E2E_FALLBACK_TARGET runs the unset-E2E_DATA_FILE mode over another file, so
# the guard can prove the restore without stamping the tree database.
set -uo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PORT="${E2E_PORT:-$(node -e "console.log(30000 + Math.floor(Math.random() * 20000))")}"
TIMEOUT="${E2E_TIMEOUT:-300}"
BASE="http://127.0.0.1:$PORT"
LOG="$(mktemp)"
BACKUP="$(mktemp -d)"
DATA_FILE="${E2E_FALLBACK_TARGET:-${E2E_DATA_FILE:-$DIR/.data/db.json}}"
EPHEMERAL=0
HAD_DATABASE=0
SERVER_PID=""
CYPRESS_PID=""
XVFB_PID=""
CYPRESS_LOG="$(mktemp)"
cd "$DIR"

[ -n "${E2E_DATA_FILE:-}" ] && EPHEMERAL=1

xvfb_server_number() {
  [ -e "/tmp/.X${1}-lock" ] || [ -e "/tmp/.X11-unix/X${1}" ] && return 1
  return 0
}

start_isolated_display() {
  for offset in $(seq 0 99); do
    candidate=$(((PORT + offset) % 1000))
    if xvfb_server_number "$candidate"; then
      Xvfb ":$candidate" -screen 0 1280x800x24 -nolisten tcp >/dev/null 2>&1 &
      XVFB_PID=$!
      export DISPLAY=":$candidate"
      return 0
    fi
  done
  return 1
}

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
  if [ "$EPHEMERAL" = "0" ] && [ "$HAD_DATABASE" = "1" ]; then
    cp "$BACKUP/db.json" "$DATA_FILE"
  fi
  rm -rf "$LOG" "$BACKUP" "$CYPRESS_LOG"
}
trap cleanup EXIT INT TERM

mkdir -p "$(dirname "$DATA_FILE")"
if [ -f "$DATA_FILE" ]; then
  cp "$DATA_FILE" "$BACKUP/db.json"
  [ "$EPHEMERAL" = "1" ] || HAD_DATABASE=1
fi
node --input-type=module -e "import { createSeed } from './src/data/seed.js'; const database = createSeed(); delete database.user; process.stdout.write(JSON.stringify(database, null, 2))" >"$DATA_FILE.seed" || {
  echo "could not prepare the legacy database fixture" >&2
  exit 1
}
mv "$DATA_FILE.seed" "$DATA_FILE"

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

if [ -n "${DISPLAY:-}" ] && ! start_isolated_display; then
  echo "could not start Xvfb: no free server number" >&2
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
