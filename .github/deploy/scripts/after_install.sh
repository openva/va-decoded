#!/usr/bin/env bash
set -euo pipefail

cd /var/www/vacode.org

chown -R www-data:ubuntu .
chmod -R g+w .

mkdir -p htdocs/admin/import-data htdocs/downloads htdocs/content

# Restore the preserved API_KEY value into the freshly-deployed config.
api_key=""
if [ -f /tmp/api_key ]; then
    api_key=$(cat /tmp/api_key)
    rm -f /tmp/api_key
fi
sed -i "s|__API_KEY__|${api_key}|" includes/config.inc.php

# Restrict config so credentials aren't world-readable
chmod 640 includes/config.inc.php

# Install the updater's schedule into the ubuntu user's crontab. Idempotent: our
# own managed lines (the updater entries and the MAILTO we set) are stripped and
# re-added, so schedule changes made in this script take effect on the next
# deploy, while any unrelated entries are preserved.
existing=$(sudo -u ubuntu crontab -l 2>/dev/null \
    | grep -vE '/var/www/vacode.org/updater.sh|^MAILTO=waldo@jaquith\.org' || true)
{
    if [ -n "$existing" ]; then
        printf '%s\n' "$existing"
    fi
    echo '0 2 2 1-6,8-12 * /var/www/vacode.org/updater.sh'
    echo '0 2 2 7 * /var/www/vacode.org/updater.sh --new-edition'
} | sudo -u ubuntu crontab -

# Rotate the updater's log so it doesn't grow without bound. This script runs as
# root, so it can write to /etc/logrotate.d.
cp .github/deploy/logrotate-vacode /etc/logrotate.d/vacode
chmod 644 /etc/logrotate.d/vacode
