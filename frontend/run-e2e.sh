#!/usr/bin/env bash
# Runs the Cypress E2E suite against the REAL backend: an ephemeral
# PostgreSQL database seeded through the API, the PHP server on :8081 and
# the Vite dev server proxying /api to it. Nothing touches the developer
# database. Extra arguments are forwarded to `cypress run`.
#
# E2E_DATABASE_URL points at the ephemeral database (created when missing).
# The database is recreated on every run; nothing touches the developer one.
# Without a DISPLAY the run gets its own Xvfb display.
set -uo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$DIR/.." && pwd)"
PORT="${E2E_PORT:-5173}"
API_PORT="${E2E_API_PORT:-8081}"
SPEC_COUNT="$(find "$DIR/cypress/e2e" -name '*.cy.js' | wc -l | tr -d ' ')"
DEFAULT_TIMEOUT=$((120 + 60 * SPEC_COUNT))
TIMEOUT="${E2E_TIMEOUT:-$DEFAULT_TIMEOUT}"
BASE="http://127.0.0.1:$PORT"
API="http://127.0.0.1:$API_PORT"
LOG="$(mktemp)"
API_LOG="$(mktemp)"
CYPRESS_LOG="$(mktemp)"
E2E_DATABASE_URL="${E2E_DATABASE_URL:-pgsql://phprise:phprise@127.0.0.1:5432/ensemble_e2e}"
SERVER_PID=""
API_PID=""
XVFB_PID=""
DISPLAY_NUMBER=""
cd "$DIR"

export VITE_API_URL="$API"

cleanup() {
  if [ -n "$SERVER_PID" ]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  if [ -n "$API_PID" ]; then
    kill "$API_PID" 2>/dev/null || true
    wait "$API_PID" 2>/dev/null || true
  fi
  if [ -n "$XVFB_PID" ]; then
    kill "$XVFB_PID" 2>/dev/null || true
    wait "$XVFB_PID" 2>/dev/null || true
  fi
  rm -f "$LOG" "$API_LOG" "$CYPRESS_LOG"
}
trap cleanup EXIT INT TERM

if ! command -v php >/dev/null 2>&1; then
  echo "php is required to run the backend under test" >&2
  exit 1
fi

# Ensure the ephemeral database exists (best effort: local dev uses the
# docker compose instance; CI services provision the database themselves).
DB_NAME=$(printf '%s' "$E2E_DATABASE_URL" | sed -E 's|.*/([^/?]+)(\?.*)?$|\1|')
if command -v docker >/dev/null 2>&1 && docker compose -f "$ROOT/docker-compose.dev.yml" ps >/dev/null 2>&1; then
  docker compose -f "$ROOT/docker-compose.dev.yml" exec -T postgres psql -U phprise -d postgres -c "CREATE DATABASE $DB_NAME" >/dev/null 2>&1 || true
elif command -v psql >/dev/null 2>&1; then
  ROOT_URL=$(printf '%s' "$E2E_DATABASE_URL" | sed -E 's|/[^/?]+(\?.*)?$|/postgres|')
  psql "$ROOT_URL" -c "CREATE DATABASE $DB_NAME" >/dev/null 2>&1 || true
fi

if ! (cd "$ROOT" && TEST_DATABASE_URL="$E2E_DATABASE_URL" php bin/e2e-reset >/dev/null && DATABASE_URL="$E2E_DATABASE_URL" BOOTSTRAP_WORKSPACE_NAME=Default BOOTSTRAP_WORKSPACE_PREFIX=KANB php bin/install >/dev/null); then
  echo "could not bootstrap the ephemeral database: $E2E_DATABASE_URL" >&2
  exit 1
fi

port_is_busy() {
  (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null && return 0
  return 1
}

if port_is_busy "$API_PORT"; then
  echo "port $API_PORT is already in use: the API would answer from another tree. Set E2E_API_PORT to a free port." >&2
  exit 1
fi
if port_is_busy "$PORT"; then
  echo "port $PORT is already in use: the suite would measure another tree. Set E2E_PORT to a free port." >&2
  exit 1
fi

if [ -f "$ROOT/.env" ]; then
  set -a
  . "$ROOT/.env"
  set +a
fi
DATABASE_URL="$E2E_DATABASE_URL" php -d variables_order=EGPCS -S "127.0.0.1:$API_PORT" -t "$ROOT/public" "$ROOT/public/router.php" >"$API_LOG" 2>&1 &
API_PID=$!

for _ in $(seq 1 60); do
  curl -s -o /dev/null "$API/api/auth/login" && break
  sleep 0.5
done

if ! kill -0 "$API_PID" 2>/dev/null; then
  echo "php server died before answering on $API:" >&2
  cat "$API_LOG" >&2
  exit 1
fi

if E2E_API_BASE="$API" bash "$DIR/tests/e2e-seed.sh" >/dev/null; then
  echo "e2e backend seeded on $API"
else
  echo "could not seed the e2e backend:" >&2
  E2E_API_BASE="$API" bash "$DIR/tests/e2e-seed.sh" >&2 || true
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

if [ "${E2E_VISUAL_AUDIT:-0}" = "1" ] && [[ " $* " != *" --browser "* ]]; then
  set -- --browser chrome "$@"
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
