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
# Checksum of the most recently *successfully imported* scrape. Lives outside
# the CodeDeploy bundle (like import-data/), so it survives deploys.
STAMP="$APP_DIR/import-data.sha256"
SCRATCH_DIR=$(mktemp -d)
trap 'rm -rf "$SCRATCH_DIR"' EXIT
LOG="$APP_DIR/update.log"

# The statedecoded CLI locates its includes/ directory relative to the current
# working directory, so every invocation must run from the app root. Cron runs
# jobs from the user's home directory, where that detection would fail.
cd "$APP_DIR"

exec >> "$LOG" 2>&1
echo "--- $(date -u +%Y-%m-%dT%H:%M:%SZ) new-edition=$NEW_EDITION ---"

sd() { sudo -u www-data php "$APP_DIR/statedecoded" "$@"; }

# Count imported laws. With a slug argument, counts that edition; with none, the
# current edition. Used to confirm an import actually populated its edition
# before we treat it as a success: the CLI can exit 0 without importing anything
# (e.g., a failed environment test or parse). Runs as www-data, which owns the
# credentialed config.
law_count() {
    sudo -u www-data php -r '
        require "includes/config.inc.php";
        try {
            $db = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);
        } catch (Exception $e) {
            echo 0;
            exit;
        }
        $slug = $argv[1] ?? "";
        if ($slug !== "") {
            $q = $db->prepare(
                "SELECT COUNT(*) FROM laws l JOIN editions e ON l.edition_id = e.id WHERE e.slug = ?"
            );
            $q->execute([$slug]);
        } else {
            $q = $db->query(
                "SELECT COUNT(*) FROM laws l JOIN editions e ON l.edition_id = e.id WHERE e.current = 1"
            );
        }
        echo (int) $q->fetchColumn();
    ' "${1:-}"
}

# Run the scraper. It writes to ./output relative to the working directory,
# so run it from the scratch directory; output lands in $SCRATCH_DIR/output.
( cd "$SCRATCH_DIR" && php "$APP_DIR/scraper.php" )
NEW_DIR="$SCRATCH_DIR/output"

# Sanity check: abort if output looks suspiciously small. On the very first run
# import-data/ does not exist yet; find exits non-zero, and pipefail propagates
# that through wc, so the bare assignment would fail under `set -e`. Treat a
# missing directory as an empty one, which is what it means here.
new_count=$(find "$NEW_DIR" -name '*.xml' | wc -l)
if [ -d "$IMPORT_DIR" ]; then
    old_count=$(find "$IMPORT_DIR" -name '*.xml' | wc -l)
else
    old_count=0
fi
min_count=$(( old_count * 9 / 10 ))
if [ "$new_count" -lt "$min_count" ]; then
    echo "ERROR: new XML count ($new_count) is more than 10% below current ($old_count). Aborting."
    exit 1
fi

# Checksum the fresh scrape (deterministic: sorted relative paths + contents).
new_hash=$(cd "$NEW_DIR" && find . -type f -name '*.xml' | sort | xargs sha256sum | sha256sum | cut -d' ' -f1)

# For regular updates, do nothing if the scrape matches the last *successful*
# import. We compare against a stamp written only after a good import — not
# against import-data/ itself — so that a failed import can't cause the change
# to be skipped forever. (In new-edition mode we always import, so the yearly
# edition is created even without changes.)
if [ "$NEW_EDITION" = false ]; then
    if [ -f "$STAMP" ] && [ "$new_hash" = "$(cat "$STAMP")" ]; then
        echo "No changes detected. Done."
        exit 0
    fi
fi

echo "Changes detected ($new_count files). Importing."

# Stage the scraped files where the importer reads them. www-data only needs to
# read these, and files written by this script (running as the ubuntu cron user)
# are world-readable, so no ownership change is required.
rsync -a --delete "$NEW_DIR/" "$IMPORT_DIR/"

if [ "$NEW_EDITION" = true ]; then
    YEAR=$(date +%Y)
    # Create the edition if it does not already exist (idempotent for re-runs).
    if ! sd edition list | grep -q "^${YEAR}\b"; then
        sd edition create "$YEAR" "$YEAR"
    fi
    sd import --edition="$YEAR"

    # Refuse to promote an edition the import didn't actually fill: promoting an
    # empty edition would take the site down.
    imported=$(law_count "$YEAR" || true)
    min_imported=$(( new_count * 9 / 10 ))
    if [ "${imported:-0}" -lt "$min_imported" ]; then
        echo "ERROR: edition $YEAR has only ${imported:-0} laws after import (scraped $new_count). Not promoting."
        exit 1
    fi

    # Promote the new edition to current: demotes the prior edition, rebuilds
    # permalinks so the site root serves it, and purges Varnish. Idempotent —
    # a friendly no-op if it is already current.
    sd edition current "$YEAR"
else
    sd import

    # Confirm the in-place import actually repopulated the current edition
    # before recording success. If it didn't, exit non-zero (surfaced by cron)
    # without writing the stamp, so next month's run retries rather than seeing
    # "no changes."
    imported=$(law_count || true)
    min_imported=$(( new_count * 9 / 10 ))
    if [ "${imported:-0}" -lt "$min_imported" ]; then
        echo "ERROR: current edition has only ${imported:-0} laws after import (scraped $new_count)."
        exit 1
    fi
fi

# Record the scrape we just imported, so an unchanged future scrape is skipped.
echo "$new_hash" > "$STAMP"

echo "Done."
