#!/usr/bin/env bash
set -euo pipefail

cd /var/www/vacode.org

chown -R www-data:www-data .

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

# Install the updater's schedule into root's crontab. Idempotent: any existing
# updater entries are removed and replaced, so schedule changes made in this
# script take effect on the next deploy.
existing=$(crontab -l 2>/dev/null | grep -v '/var/www/vacode.org/updater.sh' || true)
{
    if [ -n "$existing" ]; then
        printf '%s\n' "$existing"
    fi
    echo '0 2 2 1-6,8-12 * /var/www/vacode.org/updater.sh'
    echo '0 2 2 7 * /var/www/vacode.org/updater.sh --new-edition'
} | crontab -
