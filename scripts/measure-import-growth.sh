#!/usr/bin/env bash
#
# measure-import-growth.sh — Report how much the application grew after a
# WordPress importer run.
#
# The importer writes into git submodules (content, public/assets,
# app/private/assets, users) plus a handful of top-level YAML files
# (resources/forms, resources/blueprints/forms). Because the submodule
# *contents* are not visible to a top-level `git diff` (only the submodule
# pointer is), this script measures growth inside each submodule by comparing
# its working tree against its own HEAD:
#
#   - new (untracked, non-ignored) files  -> counted at full size
#   - modified tracked files              -> counted as (working size - HEAD size)
#   - deleted tracked files               -> counted as -(HEAD size)
#
# It also tallies any new top-level untracked files (the importer's form /
# blueprint YAML). Run it AFTER an import but BEFORE committing the result,
# while the output is still uncommitted in the working trees.
#
# If you have already committed the import inside the submodules, change the
# baseline from HEAD to the pre-import commit, e.g.:
#   git -C public/assets diff --stat <pre-import-sha>..HEAD
#
# Usage:
#   scripts/measure-import-growth.sh            # human-readable + exact bytes
#
# macOS-native (uses `stat -f%z`); no GNU coreutils required.

set -euo pipefail

# Submodules the importer writes into.
SUBMODULES=(content public/assets app/private/assets users)

# Submodules considered "assets" (large binary media), reported separately so
# you can see growth with and without them.
ASSET_SUBMODULES=(public/assets app/private/assets)

# Resolve repo root so the script works from any cwd.
ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

# human BYTES -> "12.3 MB"
human() {
  awk -v b="${1:-0}" 'BEGIN{
    split("B KB MB GB TB PB", a, " "); n=6; s=b; i=1;
    while (s >= 1024 && i < n) { s /= 1024; i++ }
    printf (i==1 ? "%d %s" : "%.1f %s"), s, a[i]
  }'
}

# Bytes added by a single submodule (new + modified delta - deleted).
measure_submodule() {
  local dir="$1"
  [ -e "$dir/.git" ] || { echo 0; return; }
  (
    cd "$dir"
    # New untracked, non-ignored files at full size.
    local added
    added=$(git ls-files --others --exclude-standard \
      | while IFS= read -r f; do stat -f%z "$f"; done \
      | awk '{s+=$1} END{print s+0}')
    # Modified tracked files: working size minus committed blob size.
    local moddelta
    moddelta=$(git diff --name-only --diff-filter=M \
      | while IFS= read -r f; do
          now=$(stat -f%z "$f" 2>/dev/null || echo 0)
          was=$(git cat-file -s "HEAD:$f" 2>/dev/null || echo 0)
          echo $((now - was))
        done \
      | awk '{s+=$1} END{print s+0}')
    # Deleted tracked files: minus committed blob size.
    local deleted
    deleted=$(git diff --name-only --diff-filter=D \
      | while IFS= read -r f; do git cat-file -s "HEAD:$f" 2>/dev/null || echo 0; done \
      | awk '{s+=$1} END{print s+0}')
    echo $((added + moddelta - deleted))
  )
}

# Bytes added by new top-level untracked files (importer form/blueprint YAML).
measure_toplevel() {
  git ls-files --others --exclude-standard \
    | while IFS= read -r f; do stat -f%z "$f"; done \
    | awk '{s+=$1} END{print s+0}'
}

is_asset() {
  local needle="$1" s
  for s in "${ASSET_SUBMODULES[@]}"; do [ "$s" = "$needle" ] && return 0; done
  return 1
}

total=0
asset_total=0

printf "%-24s %18s %16s\n" "TARGET" "BYTES" "HUMAN"
printf -- "--------------------------------------------------------------\n"

for sub in "${SUBMODULES[@]}"; do
  n=$(measure_submodule "$sub")
  total=$((total + n))
  is_asset "$sub" && asset_total=$((asset_total + n))
  printf "%-24s %'18d %16s\n" "$sub" "$n" "$(human "$n")"
done

tl=$(measure_toplevel)
total=$((total + tl))
printf "%-24s %'18d %16s\n" "(top-level new files)" "$tl" "$(human "$tl")"

without_assets=$((total - asset_total))

printf -- "--------------------------------------------------------------\n"
printf "%-24s %'18d %16s\n" "TOTAL (with assets)"    "$total"          "$(human "$total")"
printf "%-24s %'18d %16s\n" "TOTAL (without assets)" "$without_assets" "$(human "$without_assets")"
printf "%-24s %'18d %16s\n" "  assets only"          "$asset_total"    "$(human "$asset_total")"
