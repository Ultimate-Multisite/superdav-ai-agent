#!/usr/bin/env bash
#
# Deploy Gratis AI Agent to the WordPress.org plugin directory via SVN.
#
# Usage:
#   bin/deploy-wporg.sh --version 1.2.0 --username YOUR_WP_USERNAME [--svn-dir ~/svn/gratis-ai-agent]
#
# Prerequisites:
#   1. Plugin approved on WordPress.org (SVN repo must exist)
#   2. SVN checked out: svn checkout https://plugins.svn.wordpress.org/gratis-ai-agent/ ~/svn/gratis-ai-agent
#   3. svn CLI installed (apt-get install subversion / brew install subversion)
#
# What this script does:
#   1. Builds production assets and ZIP via bin/build.sh
#   2. Syncs built files into SVN trunk/
#   3. Stages new/deleted files with svn add / svn delete
#   4. Commits trunk
#   5. Creates a version tag via svn copy
#
# See docs/wordpress-org-submission.md for the full submission workflow.

set -euo pipefail

# ── Defaults ──────────────────────────────────────────────────────────────────
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SVN_DIR="${HOME}/svn/gratis-ai-agent"
WP_USERNAME=""
VERSION=""
DRY_RUN=false

# ── Argument parsing ──────────────────────────────────────────────────────────
usage() {
	cat >&2 <<EOF
Usage: bin/deploy-wporg.sh --version VERSION --username WP_USERNAME [OPTIONS]

Required:
  --version VERSION       Plugin version to deploy (e.g. 1.2.0)
  --username WP_USERNAME  WordPress.org username for SVN authentication

Options:
  --svn-dir DIR           Path to SVN checkout (default: ~/svn/gratis-ai-agent)
  --dry-run               Build and sync but do not commit or tag
  --help                  Show this help

Examples:
  bin/deploy-wporg.sh --version 1.2.0 --username developer-dave
  bin/deploy-wporg.sh --version 1.3.0 --username developer-dave --dry-run
EOF
	exit 1
}

while [ $# -gt 0 ]; do
	case "$1" in
	--version)
		VERSION="$2"
		shift 2
		;;
	--username)
		WP_USERNAME="$2"
		shift 2
		;;
	--svn-dir)
		SVN_DIR="$2"
		shift 2
		;;
	--dry-run)
		DRY_RUN=true
		shift
		;;
	--help | -h) usage ;;
	*)
		echo "Unknown option: $1" >&2
		usage
		;;
	esac
done

if [ -z "$VERSION" ] || [ -z "$WP_USERNAME" ]; then
	echo "ERROR: --version and --username are required." >&2
	usage
fi

# ── Validate prerequisites ────────────────────────────────────────────────────
if ! command -v svn >/dev/null 2>&1; then
	echo "ERROR: svn is not installed." >&2
	echo "  Ubuntu/Debian: sudo apt-get install subversion" >&2
	echo "  macOS:         brew install subversion" >&2
	exit 1
fi

if [ ! -d "${SVN_DIR}/.svn" ]; then
	echo "ERROR: SVN checkout not found at: ${SVN_DIR}" >&2
	echo ""
	echo "Check out the repository first:" >&2
	echo "  svn checkout https://plugins.svn.wordpress.org/gratis-ai-agent/ ${SVN_DIR} --username ${WP_USERNAME}" >&2
	echo ""
	echo "If the checkout fails with a 404, the plugin has not been approved yet." >&2
	echo "See docs/wordpress-org-submission.md for the submission process." >&2
	exit 1
fi

# ── Verify version matches plugin header ──────────────────────────────────────
PLUGIN_VERSION="$(grep -m1 '^ \* Version:' "${PLUGIN_DIR}/gratis-ai-agent.php" | sed 's/^.*Version:[[:space:]]*//' | tr -d '[:space:]')"
if [ "$PLUGIN_VERSION" != "$VERSION" ]; then
	echo "ERROR: --version ${VERSION} does not match plugin header version ${PLUGIN_VERSION}." >&2
	echo "Update the Version: field in gratis-ai-agent.php before deploying." >&2
	exit 1
fi

README_STABLE="$(grep -m1 '^Stable tag:' "${PLUGIN_DIR}/readme.txt" | sed 's/^Stable tag:[[:space:]]*//' | tr -d '[:space:]')"
if [ "$README_STABLE" != "$VERSION" ]; then
	echo "ERROR: readme.txt Stable tag (${README_STABLE}) does not match --version ${VERSION}." >&2
	echo "Update 'Stable tag:' in readme.txt before deploying." >&2
	exit 1
fi

# ── Build ─────────────────────────────────────────────────────────────────────
echo "==> Building Gratis AI Agent v${VERSION}..."
cd "$PLUGIN_DIR"
bin/build.sh

ZIP_PATH="${PLUGIN_DIR}/gratis-ai-agent-${VERSION}.zip"
if [ ! -f "$ZIP_PATH" ]; then
	echo "ERROR: Expected ZIP not found: ${ZIP_PATH}" >&2
	exit 1
fi
echo "    Built: ${ZIP_PATH}"

# ── Extract ZIP into a temp directory ────────────────────────────────────────
EXTRACT_DIR="$(mktemp -d)"
cleanup() {
	rm -rf "$EXTRACT_DIR"
}
trap cleanup EXIT

unzip -q "$ZIP_PATH" -d "$EXTRACT_DIR"
EXTRACTED_PLUGIN="${EXTRACT_DIR}/gratis-ai-agent"

if [ ! -d "$EXTRACTED_PLUGIN" ]; then
	echo "ERROR: ZIP does not contain a top-level gratis-ai-agent/ directory." >&2
	exit 1
fi

# ── Sync into SVN trunk ───────────────────────────────────────────────────────
echo "==> Syncing files into SVN trunk/..."
SVN_TRUNK="${SVN_DIR}/trunk"

rsync -a --delete \
	--exclude='.svn' \
	"${EXTRACTED_PLUGIN}/" "${SVN_TRUNK}/"

echo "    Sync complete."

# ── Stage new and deleted files ───────────────────────────────────────────────
echo "==> Staging SVN changes..."
cd "$SVN_DIR"

# Add new files
svn status | grep '^?' | awk '{print $2}' | while IFS= read -r f; do
	svn add "$f"
done

# Remove deleted files
svn status | grep '^!' | awk '{print $2}' | while IFS= read -r f; do
	svn delete "$f"
done

echo "    SVN status:"
svn status

# ── Commit trunk ─────────────────────────────────────────────────────────────
if [ "$DRY_RUN" = true ]; then
	echo ""
	echo "==> DRY RUN — skipping commit and tag."
	echo "    To deploy for real, re-run without --dry-run."
	exit 0
fi

echo ""
echo "==> Committing trunk (you will be prompted for your WP.org password)..."
svn commit trunk/ \
	-m "Update trunk to Gratis AI Agent v${VERSION}" \
	--username "$WP_USERNAME"

echo "    Trunk committed."

# ── Tag the release ───────────────────────────────────────────────────────────
echo "==> Creating tag ${VERSION}..."

if svn info "tags/${VERSION}" >/dev/null 2>&1; then
	echo "    WARNING: Tag ${VERSION} already exists — skipping tag creation."
else
	svn copy trunk/ "tags/${VERSION}" \
		-m "Tag Gratis AI Agent v${VERSION}" \
		--username "$WP_USERNAME"
	echo "    Tag created: tags/${VERSION}"
fi

# ── Done ──────────────────────────────────────────────────────────────────────
echo ""
echo "==> Deployment complete!"
echo "    Plugin URL: https://wordpress.org/plugins/gratis-ai-agent/"
echo "    SVN trunk:  https://plugins.svn.wordpress.org/gratis-ai-agent/trunk/"
echo "    SVN tag:    https://plugins.svn.wordpress.org/gratis-ai-agent/tags/${VERSION}/"
echo ""
echo "    WP.org CDN may take up to 15 minutes to reflect the update."
