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
