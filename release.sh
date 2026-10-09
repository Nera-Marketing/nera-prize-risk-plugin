#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# release.sh — Tag main and publish nera-prize-risk to its GitHub releases.
#
# Usage:
#   ./release.sh          # reads version from nera-prize-risk.php (Version header)
#   ./release.sh 0.2.0    # must match the version in the files (optional leading v)
#
# Every change reaches main through a PR, so this script never commits. Bump the
# version in a PR first, in all three places:
#   - nera-prize-risk.php: " * Version:" header and NERA_PRIZE_RISK_VERSION
#   - readme.txt: "Stable tag:" and a changelog entry
# Merge it, then run this script from an up-to-date main.
#
# What it does:
#   1. Checks: on main, clean tree, in sync with origin/main, the three versions agree, tag is new
#   2. Builds nera-prize-risk-VERSION.zip from HEAD with git archive (folder nera-prize-risk/,
#      `/` paths; files marked export-ignore in .gitattributes are left out)
#   3. Pushes tag vVERSION and creates the GitHub release with the zip attached (gh)
#
# Sites pick the release up through Plugin Update Checker (see nera-prize-risk.php).
# The repo is private: each site needs NERA_PRIZE_RISK_GITHUB_TOKEN in wp-config.php.
#
# Requirements: git, gh (logged in to github.com with access to the repo).
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_SLUG="nera-prize-risk"
GITHUB_REPO="Nera-Marketing/nera-prize-risk-plugin"
RELEASE_BRANCH="${RELEASE_GIT_BRANCH:-main}"

cd "$PLUGIN_DIR"

fail() {
  echo "ERROR: $*" >&2
  exit 1
}

for cmd in git gh grep sed; do
  command -v "$cmd" >/dev/null 2>&1 || fail "required command not found: $cmd"
done

# ── 1. Checks ────────────────────────────────────────────────────────────────
HEADER_VERSION=$(grep -m1 '^ \* Version:' "${PLUGIN_SLUG}.php" | sed 's/.*Version: *//' | tr -d '\r')
CONST_VERSION=$(grep -m1 "define( 'NERA_PRIZE_RISK_VERSION'" "${PLUGIN_SLUG}.php" | sed "s/.*'NERA_PRIZE_RISK_VERSION', '\([^']*\)'.*/\1/")
README_VERSION=$(grep -m1 '^Stable tag:' readme.txt | sed 's/^Stable tag: *//' | tr -d '\r')

VERSION="${1:-$HEADER_VERSION}"
VERSION="${VERSION#v}"
[ -n "$VERSION" ] || fail "could not determine version. Pass it as an argument: ./release.sh 0.2.0"
TAG="v${VERSION}"

echo "──────────────────────────────────────────"
echo " Releasing $PLUGIN_SLUG $TAG"
echo "──────────────────────────────────────────"

if [ "$HEADER_VERSION" != "$VERSION" ] || [ "$CONST_VERSION" != "$VERSION" ] || [ "$README_VERSION" != "$VERSION" ]; then
  echo "  Version header:          $HEADER_VERSION"
  echo "  NERA_PRIZE_RISK_VERSION: $CONST_VERSION"
  echo "  readme.txt Stable tag:   $README_VERSION"
  fail "versions don't all match $VERSION. Bump them in a PR, merge it, then release."
fi

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
[ "$CURRENT_BRANCH" = "$RELEASE_BRANCH" ] || fail "on '$CURRENT_BRANCH'. Check out $RELEASE_BRANCH first."
[ -z "$(git status --porcelain)" ] || fail "working tree has changes. Commit them through a PR or stash them."

echo "▶ Fetching origin..."
git fetch -q origin "$RELEASE_BRANCH" --tags
[ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/${RELEASE_BRANCH}")" ] \
  || fail "$RELEASE_BRANCH is not in sync with origin/${RELEASE_BRANCH}. Run: git pull --ff-only"

if git rev-parse -q --verify "refs/tags/${TAG}" >/dev/null; then
  fail "tag $TAG already exists. Bump the version for a new release."
fi

( export GH_HOST=github.com && gh auth status -h github.com >/dev/null 2>&1 ) \
  || fail "gh is not logged in for github.com. Run: gh auth login -h github.com"

# ── 2. Build zip ─────────────────────────────────────────────────────────────
ZIP_PATH="$PLUGIN_DIR/${PLUGIN_SLUG}-${VERSION}.zip"
echo "▶ Creating zip from HEAD..."
rm -f "$ZIP_PATH"
git archive --format=zip --prefix="${PLUGIN_SLUG}/" -o "$ZIP_PATH" HEAD
[ -s "$ZIP_PATH" ] || fail "zip is missing or empty: $ZIP_PATH"
echo "▶ Zip OK ($(wc -c < "$ZIP_PATH" | tr -d ' ') bytes)"

# ── 3. Tag + GitHub release ──────────────────────────────────────────────────
echo "▶ Tagging $TAG and pushing it..."
git tag -a "$TAG" -m "Release $TAG"
git push -q origin "refs/tags/${TAG}"

echo "▶ Publishing GitHub release $TAG..."
GH_HOST=github.com gh release create "$TAG" \
  --repo "$GITHUB_REPO" \
  --title "$TAG" \
  --generate-notes \
  "$ZIP_PATH"
rm -f "$ZIP_PATH"

echo ""
echo "✅ Done! Release: https://github.com/${GITHUB_REPO}/releases/tag/${TAG}"
echo "Sites pick it up when PUC next checks GitHub (every 6 hours)."
echo "   Tip: Dashboard → Updates → Check again. Stale cache: wp transient delete update_plugins"
