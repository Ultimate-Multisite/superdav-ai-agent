#!/usr/bin/env bash
# task-complete-helper.sh — Project-level wrapper for task completion.
#
# Enforces a PR merge check before delegating to the aidevops framework
# task-complete-helper.sh. This prevents the premature-completion bug
# (GH#466) where a worker marks a task complete before the PR is merged.
#
# Usage: identical to the framework script
#   task-complete-helper.sh <task-id> --pr <number> [options]
#   task-complete-helper.sh <task-id> --verified [date] [options]
#
# Options:
#   --pr <number>              PR number (required unless --verified is used)
#   --verified <date>          Verified date (YYYY-MM-DD, defaults to today)
#   --gh-repo <owner/repo>     GitHub repo slug for PR lookup (default: auto-detect)
#   --skip-merge-check         Skip PR merge verification (tests/CI only)
#   --repo-path <path>         Path to git repository (default: current directory)
#   --no-push                  Mark complete but don't push (for testing)
#   --verify                   Run verify-brief.sh on task brief before completing
#   --help                     Show this help message
#
# Exit codes:
#   0 - Success
#   1 - Error (PR not merged, task not found, git error, etc.)
#
# Fixes: https://github.com/Ultimate-Multisite/sd-ai-agent/issues/466

set -euo pipefail

# ---------------------------------------------------------------------------
# Logging helpers (inline — no dependency on shared-constants.sh)
# ---------------------------------------------------------------------------
_log_info() { echo -e "\033[0;34m[INFO]\033[0m $*" >&2; }
_log_error() { echo -e "\033[0;31m[ERROR]\033[0m $*" >&2; }
_log_warn() { echo -e "\033[1;33m[WARN]\033[0m $*" >&2; }
_log_success() { echo -e "\033[0;32m[OK]\033[0m $*" >&2; }

# ---------------------------------------------------------------------------
# verify_pr_merged — check that a PR is in MERGED state before proceeding.
#
# Arguments:
#   $1 - PR number
#   $2 - GitHub repo slug (owner/repo), or empty to auto-detect from git remote
#
# Returns:
#   0 - PR is merged
#   1 - PR is not merged, or lookup failed
# ---------------------------------------------------------------------------
verify_pr_merged() {
	local pr_number="$1"
	local gh_repo="${2:-}"

	if ! command -v gh &>/dev/null; then
		_log_warn "gh CLI not found — skipping PR merge check"
		return 0
	fi

	if ! command -v jq &>/dev/null; then
		_log_warn "jq not found — skipping PR merge check"
		return 0
	fi

	# Build gh pr view args — add --repo only when a slug is provided
	local gh_args=("pr" "view" "$pr_number" "--json" "state,mergedAt")
	if [[ -n "$gh_repo" ]]; then
		gh_args+=("--repo" "$gh_repo")
	fi

	_log_info "Verifying PR #${pr_number} is merged${gh_repo:+ (repo: $gh_repo)}..."

	local pr_json
	if ! pr_json=$(gh "${gh_args[@]}" 2>&1); then
		_log_error "Failed to fetch PR #${pr_number}: ${pr_json}"
		_log_error "Check that the PR exists and gh CLI is authenticated."
		return 1
	fi

	local pr_state pr_merged_at
	pr_state=$(printf '%s' "$pr_json" | jq -r '.state // ""' 2>/dev/null || true)
	pr_merged_at=$(printf '%s' "$pr_json" | jq -r '.mergedAt // ""' 2>/dev/null || true)

	if [[ "$pr_state" != "MERGED" ]] || [[ -z "$pr_merged_at" ]] || [[ "$pr_merged_at" == "null" ]]; then
		_log_error "PR #${pr_number} is not merged (state: ${pr_state:-unknown})"
		_log_error "Task completion is only allowed after the PR is merged."
		_log_error "Wait for the PR to merge, then re-run this command."
		return 1
	fi

	_log_success "PR #${pr_number} is merged (mergedAt: ${pr_merged_at})"
	return 0
}

# ---------------------------------------------------------------------------
# Main — parse args, run merge check, delegate to framework script
# ---------------------------------------------------------------------------
main() {
	if [[ $# -eq 0 || "$1" == "--help" ]]; then
		grep '^#' "$0" | grep -v '#!/usr/bin/env' | sed 's/^# //' | sed 's/^#//'
		return 0
	fi

	local pr_number="" gh_repo="" skip_merge_check=false

	# Scan args for --pr, --gh-repo, --skip-merge-check (pass everything else through)
	local -a passthrough_args=()
	local i=1
	while [[ $i -le $# ]]; do
		local arg="${!i}"
		case "$arg" in
		--pr)
			i=$((i + 1))
			pr_number="${!i:-}"
			passthrough_args+=("--pr" "$pr_number")
			;;
		--gh-repo)
			i=$((i + 1))
			gh_repo="${!i:-}"
			# Do NOT pass --gh-repo to framework script (it may not support it)
			;;
		--skip-merge-check)
			skip_merge_check=true
			# Do NOT pass to framework script
			;;
		*)
			passthrough_args+=("$arg")
			;;
		esac
		i=$((i + 1))
	done

	# Run PR merge check when --pr is provided
	if [[ -n "$pr_number" ]]; then
		if [[ "$skip_merge_check" == "true" ]]; then
			_log_warn "Skipping PR merge check (--skip-merge-check). Use only in tests."
		else
			if ! verify_pr_merged "$pr_number" "$gh_repo"; then
				return 1
			fi
		fi
	fi

	# Delegate to the aidevops framework script
	local framework_script="${HOME}/.aidevops/agents/scripts/task-complete-helper.sh"

	if [[ -x "$framework_script" ]]; then
		_log_info "Delegating to framework: $framework_script"
		exec "$framework_script" "${passthrough_args[@]}"
	else
		_log_error "Framework task-complete-helper.sh not found: $framework_script"
		_log_error "Install aidevops: https://aidevops.sh"
		return 1
	fi
}

main "$@"
