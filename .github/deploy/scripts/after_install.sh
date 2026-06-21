#!/usr/bin/env bash
set -euo pipefail

cd /var/www/vacode.org

chown -R www-data:www-data .

mkdir -p htdocs/admin/import-data htdocs/downloads htdocs/content

# Restrict config so credentials aren't world-readable
chmod 640 includes/config.inc.php
