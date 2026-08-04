#!/usr/bin/env bash
set -euo pipefail

apt-get install -y tidy php-tidy

mkdir -p /var/www/vacode.org
chown -R www-data:www-data /var/www/vacode.org

# Preserve API_KEY across deploys: save the current value before files are replaced.
existing_config=/var/www/vacode.org/includes/config.inc.php
api_key=""
if [ -f "$existing_config" ]; then
    extracted=$(grep "define('API_KEY'" "$existing_config" \
        | sed "s/.*define('API_KEY', '\([^']*\)').*/\1/" || true)
    # Treat the placeholder (parser hasn't run yet) the same as empty.
    if [ "$extracted" != "__API_KEY__" ]; then
        api_key="$extracted"
    fi
fi
printf '%s' "$api_key" > /tmp/api_key
