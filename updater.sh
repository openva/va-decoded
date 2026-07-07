#!/usr/bin/env bash
#
# Re-scrape the Code of Virginia and, if anything changed, import it.
#
#   updater.sh                 Update the current edition in place (monthly).
#   updater.sh --new-edition   Create, populate, and promote an edition named
#                              for the current year (annual, July). See
#                              updater.md.
#
set -euo pipefail

NEW_EDITION=false
for arg in "$@"; do
    [ "$arg" = "--new-edition" ] && NEW_EDITION=true
done

APP_DIR=/var/www/vacode.org
IMPORT_DIR="$APP_DIR/import-data"
SCRATCH_DIR=$(mktemp -d)
trap 'rm -rf "$SCRATCH_DIR"' EXIT
LOG=/var/log/va-update-laws.log

exec >> "$LOG" 2>&1
echo "--- $(date -u +%Y-%m-%dT%H:%M:%SZ) new-edition=$NEW_EDITION ---"

sd() { sudo -u www-data php "$APP_DIR/statedecoded" "$@"; }

# Run the scraper. It writes to ./output relative to the working directory,
# so run it from the scratch directory; output lands in $SCRATCH_DIR/output.
( cd "$SCRATCH_DIR" && php "$APP_DIR/scraper.php" )
NEW_DIR="$SCRATCH_DIR/output"

# Sanity check: abort if output looks suspiciously small.
new_count=$(find "$NEW_DIR" -name '*.xml' | wc -l)
old_count=$(find "$IMPORT_DIR" -name '*.xml' 2>/dev/null | wc -l)
if [ "$new_count" -lt $(( old_count * 9 / 10 )) ]; then
    echo "ERROR: new XML count ($new_count) is more than 10% below current ($old_count). Aborting."
    exit 1
fi

# For regular updates, do nothing if the data is unchanged. (In new-edition
# mode we always import, so the yearly edition is created even without changes.)
if [ "$NEW_EDITION" = false ]; then
    if diff -rq "$NEW_DIR" "$IMPORT_DIR" > /dev/null 2>&1; then
        echo "No changes detected. Done."
        exit 0
    fi
fi

echo "Changes detected ($new_count files). Importing."

# Replace the import directory contents, then hand ownership to www-data
# (cron runs as root, so the freshly-written files are root-owned).
rsync -a --delete "$NEW_DIR/" "$IMPORT_DIR/"
chown -R www-data:www-data "$IMPORT_DIR"

if [ "$NEW_EDITION" = true ]; then
    YEAR=$(date +%Y)
    # Create the edition if it does not already exist (idempotent for re-runs).
    if ! sd edition list | grep -q "^${YEAR}\b"; then
        sd edition create "$YEAR" "$YEAR"
    fi
    sd import --edition="$YEAR"
    # Promote the new edition to current: demotes the prior edition, rebuilds
    # permalinks so the site root serves it, and purges Varnish. Idempotent —
    # a friendly no-op if it is already current.
    sd edition current "$YEAR"
else
    sd import
fi

echo "Done."
