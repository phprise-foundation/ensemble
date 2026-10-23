#!/usr/bin/env bash
#
# publish.sh - Publishes a project to GitHub (repo + tag + release).
#
# Usage:
#   ./bin/publish.sh --tag v0.1.0 [--path <dir>] [--repo <owner/name>] [--dry-run]
#
# Requirements: git, gh (authenticated)
#
set -Eeuo pipefail

readonly SCRIPT_NAME="${0##*/}"
readonly GITHUB_HOST="github.com"

readonly COLOR_RESET='\033[0m'
readonly COLOR_INFO='\033[0;34m'
readonly COLOR_OK='\033[0;32m'
readonly COLOR_WARN='\033[0;33m'
readonly COLOR_ERROR='\033[0;31m'

TARGET_PATH="$(pwd)"
REPO_INPUT=""
TAG_INPUT=""
DRY_RUN="false"
VISIBILITY="private"

log_info()  { printf "${COLOR_INFO}[INFO]${COLOR_RESET}  %s\n" "$*"; }
log_ok()    { printf "${COLOR_OK}[ OK ]${COLOR_RESET}  %s\n" "$*"; }
log_warn()  { printf "${COLOR_WARN}[WARN]${COLOR_RESET}  %s\n" "$*"; }
log_error() { printf "${COLOR_ERROR}[FAIL]${COLOR_RESET}  %s\n" "$*" >&2; }

die() {
    log_error "$*"
    exit 1
}

show_help() {
    cat <<HELP
Usage: ${SCRIPT_NAME} [OPTIONS]

Publishes a project to GitHub (repository, tag and release).

Options:
  --tag <version>        Tag to publish (e.g. v0.1.0). Required.
  --path <dir>           Local repository path. Default: current directory.
  --repo <owner/name>    GitHub repository. Required if the 'origin' remote is not set.
  --public               Create the repository as public (default: private).
  --private              Create the repository as private (default).
  --dry-run              Simulate all steps without changing anything.
  -h, --help             Show this message.

Example:
  ${SCRIPT_NAME} --tag v0.1.0 --repo phprise-foundation/ensemble --public
HELP
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || die "Required command not found: $1"
}

run() {
    if [ "$DRY_RUN" = "true" ]; then
        log_warn "[dry-run] $*"
        return 0
    fi
    "$@"
}

is_git_repository() {
    git rev-parse --is-inside-work-tree >/dev/null 2>&1
}

parse_arguments() {
    while [ $# -gt 0 ]; do
        case "$1" in
            --tag)     TAG_INPUT="${2:-}"; shift 2 ;;
            --path)    TARGET_PATH="${2:-}"; shift 2 ;;
            --repo)    REPO_INPUT="${2:-}"; shift 2 ;;
            --public)  VISIBILITY="public"; shift ;;
            --private) VISIBILITY="private"; shift ;;
            --dry-run) DRY_RUN="true"; shift ;;
            -h|--help) show_help; exit 0 ;;
            *) die "Unknown parameter: $1 (use --help)" ;;
        esac
    done
}

validate_environment() {
    require_command git
    require_command gh

    [ -n "$TAG_INPUT" ] || die "The --tag parameter is required."
    [ -d "$TARGET_PATH" ] || die "Path does not exist: $TARGET_PATH"

    gh auth status >/dev/null 2>&1 || die "gh is not authenticated. Run: gh auth login"
}

extract_repo_id() {
    local url="$1"
    url="${url%.git}"
    if [[ "$url" =~ ${GITHUB_HOST}[:/]([^/]+/[^/]+)$ ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
        return 0
    fi
    return 1
}

ensure_git_repository() {
    if is_git_repository; then
        log_ok "Git repository already initialized."
        return 0
    fi
    log_info "Initializing Git repository..."
    run git init -b main
    run git add .
    run git commit -m "Initial commit"
}

resolve_repository_id() {
    if is_git_repository && git remote get-url origin >/dev/null 2>&1; then
        local remote_url
        remote_url="$(git remote get-url origin)"
        if REPO_ID="$(extract_repo_id "$remote_url")"; then
            log_ok "Repository detected from remote: $REPO_ID"
            return 0
        fi
        log_warn "Could not identify the repository in: $remote_url"
    fi

    [ -n "$REPO_INPUT" ] || die "Remote 'origin' missing and --repo not provided."
    REPO_ID="$REPO_INPUT"
    log_info "Using provided repository: $REPO_ID"
}

ensure_github_repository() {
    if gh api "repos/$REPO_ID" >/dev/null 2>&1; then
        log_ok "Repository already exists on GitHub."
        return 0
    fi

    [ -n "$REPO_INPUT" ] || die "Repository missing on GitHub and --repo not provided."
    log_info "Creating $VISIBILITY repository on GitHub: $REPO_ID"
    run gh repo create "$REPO_ID" "--$VISIBILITY" --source=. --remote=origin
}

configure_origin_remote() {
    local target_url="git@${GITHUB_HOST}:${REPO_ID}.git"
    if is_git_repository && git remote get-url origin >/dev/null 2>&1; then
        run git remote set-url origin "$target_url"
        return 0
    fi
    run git remote add origin "$target_url"
}

push_branch() {
    local branch
    branch="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || printf 'main')"
    log_info "Pushing branch '$branch' to GitHub..."
    run git push -u origin "$branch"
}

publish_release() {
    if gh release view "$TAG_INPUT" >/dev/null 2>&1; then
        log_warn "Release '$TAG_INPUT' already exists. Skipping."
        return 0
    fi

    if ! git rev-parse "$TAG_INPUT" >/dev/null 2>&1; then
        log_info "Creating local tag '$TAG_INPUT'..."
        run git tag "$TAG_INPUT"
    fi

    log_info "Pushing tag '$TAG_INPUT'..."
    run git push origin "$TAG_INPUT"

    log_info "Creating release on GitHub..."
    run gh release create "$TAG_INPUT" --generate-notes
}

main() {
    parse_arguments "$@"
    validate_environment

    cd "$TARGET_PATH"
    log_info "Working directory: $(pwd)"

    ensure_git_repository
    resolve_repository_id
    ensure_github_repository
    configure_origin_remote
    push_branch
    publish_release

    log_ok "Published: ${REPO_ID} @ ${TAG_INPUT}"
}

main "$@"
