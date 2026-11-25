#!/usr/bin/env bash
#
# Classify a pull request's changed files as "code" or "docs-only".
#
# Reads one changed path per line on stdin and prints exactly one word:
#   false  every path matches the docs-only list below (skip code CI jobs)
#   true   at least one path needs code CI, or the list is empty
#
# Keep the docs-only list identical to what was previously in the `paths-ignore`
# blocks of tests.yml and code-quality.yml. Those workflows now always start so
# their required status checks report "skipped" instead of staying "expected"
# forever, which used to make docs-only PRs unmergeable.

set -euo pipefail

is_docs_only_path() {
	local path="$1"

	case "${path}" in
		docs/* | .github/ISSUE_TEMPLATE/* | todo/* | TODO.md | LICENSE | .editorconfig)
			return 0
			;;
	esac

	return 1
}

classify_paths() {
	local path=""
	local seen=0

	while IFS= read -r path || [[ -n "${path}" ]]; do
		[[ -n "${path}" ]] || continue
		seen=1
		if ! is_docs_only_path "${path}"; then
			printf 'true\n'
			return 0
		fi
	done

	# No paths at all is unexpected; fail safe by running the full checks.
	if [[ "${seen}" -eq 0 ]]; then
		printf 'true\n'
		return 0
	fi

	printf 'false\n'
	return 0
}

classify_paths
