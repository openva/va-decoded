#!/usr/bin/env bash
set -euo pipefail

apt-get install -y tidy php-tidy

mkdir -p /var/www/vacode.org
chown -R www-data:www-data /var/www/vacode.org
