#!/usr/bin/env bash
# Seeds the E2E backend through the real API: the same surface the UI uses.
# The backend must already be listening (bin/e2e-* leaves admin/admin1234).
set -euo pipefail

BASE="${E2E_API_BASE:-http://127.0.0.1:8081}"
LOGIN="${E2E_LOGIN:-admin}"
PASSWORD="${E2E_PASSWORD:-admin1234}"

api() {
  local method="$1" path="$2" body="${3:-}"
  if [ -n "$body" ]; then
    curl -sS -X "$method" "$BASE$path" -H 'Content-Type: application/json' -H "Authorization: Bearer $TOKEN" -d "$body"
  else
    curl -sS -X "$method" "$BASE$path" -H "Authorization: Bearer $TOKEN"
  fi
}

field() {
  python3 -c "import json,sys; print(json.load(sys.stdin)$1)"
}

TOKEN=$(curl -sS "$BASE/api/auth/login" -X POST -H 'Content-Type: application/json' -d "{\"login\":\"$LOGIN\",\"password\":\"$PASSWORD\"}" | field "['token']")
WS=$(api GET /api/workspaces | field "['workspaces'][0]['id']")

# Workflow Standard + issue type + statuses (roles come before statuses).
TEAM=$(api POST "/api/workspaces/$WS/teams" '{"name":"Default team"}' | field "['team']['id']")
ROLE_PO=$(api POST "/api/workspaces/$WS/teams/$TEAM/roles" '{"name":"Product Owner","description":"Turns intent into a framed problem.","interactive":true}' | field "['role']['id']")
ROLE_TL=$(api POST "/api/workspaces/$WS/teams/$TEAM/roles" '{"name":"Tech Lead","description":"Reviews changes and unblocks impediments.","unblock":true}' | field "['role']['id']")
ROLE_CODER=$(api POST "/api/workspaces/$WS/teams/$TEAM/roles" '{"name":"Coder","description":"Implements the planned work."}' | field "['role']['id']")
ROLE_QA=$(api POST "/api/workspaces/$WS/teams/$TEAM/roles" '{"name":"Quality Assurance","description":"Refines acceptance and verifies outcomes."}' | field "['role']['id']")

WF=$(api POST "/api/workspaces/$WS/workflows" '{"name":"Standard"}' | field "['workflow']['id']")
TASK=$(api POST "/api/workspaces/$WS/workflows/$WF/issue-types" '{"name":"Task","level":1}' | field "['issueType']['id']")
ST_TODO=$(api POST "/api/workspaces/$WS/workflows/$WF/statuses" "{\"name\":\"To Develop\",\"type\":\"initial\",\"issueTypeId\":\"$TASK\",\"roleIds\":[\"$ROLE_PO\"]}" | field "['status']['id']")
ST_DOING=$(api POST "/api/workspaces/$WS/workflows/$WF/statuses" "{\"name\":\"In Development\",\"type\":\"transitional\",\"issueTypeId\":\"$TASK\",\"roleIds\":[\"$ROLE_CODER\"]}" | field "['status']['id']")
ST_REVIEW=$(api POST "/api/workspaces/$WS/workflows/$WF/statuses" "{\"name\":\"In Review\",\"type\":\"transitional\",\"issueTypeId\":\"$TASK\",\"roleIds\":[\"$ROLE_TL\"]}" | field "['status']['id']")
ST_TEST=$(api POST "/api/workspaces/$WS/workflows/$WF/statuses" "{\"name\":\"In Test\",\"type\":\"transitional\",\"issueTypeId\":\"$TASK\",\"roleIds\":[\"$ROLE_QA\"]}" | field "['status']['id']")
ST_DONE=$(api POST "/api/workspaces/$WS/workflows/$WF/statuses" "{\"name\":\"Done\",\"type\":\"final\",\"issueTypeId\":\"$TASK\"}" | field "['status']['id']")
ST_CANCELED=$(api POST "/api/workspaces/$WS/workflows/$WF/statuses" "{\"name\":\"Canceled\",\"type\":\"final\",\"issueTypeId\":\"$TASK\"}" | field "['status']['id']")

api POST "/api/workspaces/$WS/workflows/$WF/transitions" "{\"fromStatusId\":\"$ST_TODO\",\"toStatusId\":\"$ST_DOING\",\"actor\":\"both\"}" >/dev/null
api POST "/api/workspaces/$WS/workflows/$WF/transitions" "{\"fromStatusId\":\"$ST_DOING\",\"toStatusId\":\"$ST_REVIEW\",\"actor\":\"both\"}" >/dev/null
api POST "/api/workspaces/$WS/workflows/$WF/transitions" "{\"fromStatusId\":\"$ST_REVIEW\",\"toStatusId\":\"$ST_TEST\",\"actor\":\"both\"}" >/dev/null
api POST "/api/workspaces/$WS/workflows/$WF/transitions" "{\"fromStatusId\":\"$ST_TEST\",\"toStatusId\":\"$ST_DONE\",\"actor\":\"both\"}" >/dev/null

BOARD=$(api POST "/api/workspaces/$WS/boards" "{\"name\":\"Kanban\",\"workflowId\":\"$WF\"}" | field "['board']['id']")
api POST "/api/workspaces/$WS/boards/$BOARD/columns" "{\"name\":\"Todo\",\"statusIds\":[\"$ST_TODO\"]}" >/dev/null
api POST "/api/workspaces/$WS/boards/$BOARD/columns" "{\"name\":\"Doing\",\"wip\":6,\"statusIds\":[\"$ST_DOING\",\"$ST_REVIEW\",\"$ST_TEST\"]}" >/dev/null
api POST "/api/workspaces/$WS/boards/$BOARD/columns" "{\"name\":\"Done\",\"statusIds\":[\"$ST_DONE\",\"$ST_CANCELED\"]}" >/dev/null

# Team composition.
PROFILES=$(api POST "/api/workspaces/$WS/teams/$TEAM/profiles" '{"name":"Socratic","style":"Deconstructs certainties and hunts the root cause.","tone":"Inquisitive","emojiUsage":"Never"}' | field "['profile']['id']")
api POST "/api/workspaces/$WS/teams/$TEAM/profiles" '{"name":"Nerd","style":"Deep reasoning, lists and a rigorous process.","tone":"Precise","emojiUsage":"Moderate"}' >/dev/null
api POST "/api/workspaces/$WS/teams/$TEAM/profiles" '{"name":"Professor","style":"A demanding master who reviews and teaches.","tone":"Didactic","emojiUsage":"Never"}' >/dev/null
api POST "/api/workspaces/$WS/teams/$TEAM/profiles" '{"name":"Activist","style":"Contagious joy and fervor for causes.","tone":"Energetic","emojiUsage":"Frequent"}' >/dev/null
api POST "/api/workspaces/$WS/teams/$TEAM/profiles" '{"name":"Skeptic","style":"Constant doubt and honest investigation.","tone":"Cautious","emojiUsage":"Never"}' >/dev/null

VIVY_PORTRAIT=$(node -e "import('./src/data/seed-portrait.js').then((m) => process.stdout.write(m.VIVY_PORTRAIT))")
VIVY=$(api POST "/api/workspaces/$WS/teams/$TEAM/agents" "{\"name\":\"Vivy\",\"whoAmI\":\"The first autonomous agent, curious about people.\",\"age\":20,\"maritalStatus\":\"Single\",\"roleIds\":[\"$ROLE_PO\"],\"profileId\":\"$PROFILES\",\"avatarColor\":\"#4c9aff\",\"portrait\":\"$VIVY_PORTRAIT\",\"model\":\"test-model\",\"fallbackModel\":\"\",\"active\":true}" | field "['agent']['id']")
KAIO=$(api POST "/api/workspaces/$WS/teams/$TEAM/agents" "{\"name\":\"Kaio\",\"whoAmI\":\"A pragmatic builder.\",\"age\":27,\"maritalStatus\":\"Single\",\"roleIds\":[\"$ROLE_CODER\"],\"profileId\":\"$PROFILES\",\"avatarColor\":\"#ff991f\",\"model\":\"test-model\",\"fallbackModel\":\"\",\"active\":true}" | field "['agent']['id']")
ALAN=$(api POST "/api/workspaces/$WS/teams/$TEAM/agents" "{\"name\":\"Alan\",\"whoAmI\":\"A patient reviewer and teacher.\",\"age\":41,\"maritalStatus\":\"Married\",\"roleIds\":[\"$ROLE_TL\"],\"profileId\":\"$PROFILES\",\"avatarColor\":\"#8777d9\",\"model\":\"test-model\",\"fallbackModel\":\"\",\"active\":true}" | field "['agent']['id']")
MAI=$(api POST "/api/workspaces/$WS/teams/$TEAM/agents" "{\"name\":\"Mai\",\"whoAmI\":\"Distrusts the happy path.\",\"age\":33,\"maritalStatus\":\"Single\",\"roleIds\":[\"$ROLE_QA\"],\"profileId\":\"$PROFILES\",\"avatarColor\":\"#00b8d9\",\"model\":\"test-model\",\"fallbackModel\":\"\",\"active\":true}" | field "['agent']['id']")

# Issues across the whole board.
I1=$(api POST "/api/workspaces/$WS/issues" "{\"issueTypeId\":\"$TASK\",\"summary\":\"Frame the onboarding intent\",\"description\":\"The client wants a smoother onboarding. Clarify value, risks and open questions.\",\"assigneeId\":\"$VIVY\"}" | field "['issue']['key']")
I2=$(api POST "/api/workspaces/$WS/issues" "{\"issueTypeId\":\"$TASK\",\"summary\":\"Fix the login redirect loop\",\"description\":\"After a successful login the app bounces back to the sign-in screen on slow networks.\",\"assigneeId\":\"$KAIO\"}" | field "['issue']['key']")
I3=$(api POST "/api/workspaces/$WS/issues" "{\"issueTypeId\":\"$TASK\",\"summary\":\"Add dark theme to the board\",\"description\":\"Dark theme by default with a light option.\",\"assigneeId\":\"$ALAN\"}" | field "['issue']['key']")
I4=$(api POST "/api/workspaces/$WS/issues" "{\"issueTypeId\":\"$TASK\",\"summary\":\"Card shows aging flag\",\"description\":\"Flag cards with more than 24h without activity.\",\"assigneeId\":\"$MAI\"}" | field "['issue']['key']")
I5=$(api POST "/api/workspaces/$WS/issues" "{\"issueTypeId\":\"$TASK\",\"summary\":\"Set up the CI pipeline\",\"description\":\"Run tests on every pull request.\",\"assigneeId\":\"$KAIO\"}" | field "['issue']['key']")
api POST "/api/workspaces/$WS/issues" "{\"issueTypeId\":\"$TASK\",\"summary\":\"Retire the legacy exporter\",\"description\":\"No longer needed.\"}" >/dev/null
api POST "/api/workspaces/$WS/issues" "{\"issueTypeId\":\"$TASK\",\"summary\":\"Legacy issue over the limits\",\"description\":\"$(printf 'D%.0s' $(seq 1 200))\"}" >/dev/null

api POST "/api/workspaces/$WS/issues/$I1/comments" '{"body":"We lose users in the first step."}' >/dev/null
api POST "/api/workspaces/$WS/issues/$I1/comments" '{"body":"Let me investigate this."}' >/dev/null
api POST "/api/workspaces/$WS/issues/$I1/comments" '{"body":"Have a look at the data flow."}' >/dev/null
api POST "/api/workspaces/$WS/issues/$I1/comments" '{"body":"Great progress so far."}' >/dev/null
move() {
  local key="$1" status="$2"
  local response
  response=$(api POST "/api/workspaces/$WS/issues/$key/move" "{\"statusId\":\"$status\"}")
  case "$response" in
    *'"issue"'*) ;;
    *) echo "move failed for $key -> $status: $response" >&2; exit 1 ;;
  esac
}

move "$I2" "$ST_DOING"
move "$I3" "$ST_DOING"
move "$I3" "$ST_REVIEW"
move "$I4" "$ST_DOING"
move "$I4" "$ST_REVIEW"
move "$I4" "$ST_TEST"
move "$I5" "$ST_DOING"
move "$I5" "$ST_REVIEW"
move "$I5" "$ST_TEST"
move "$I5" "$ST_DONE"
api POST "/api/workspaces/$WS/issues/$I1/comments" '{"body":"revisão pronta"}' >/dev/null

echo "e2e seed ok (workspace $WS)"
