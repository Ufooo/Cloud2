#!/bin/bash
set -eo pipefail

# Netipar Cloud - Delete Isolated PHP-FPM Pool
# User: {{ $user }}
# PHP Version: {{ $phpVersion }}

POOL_CONF="/etc/php/{{ $phpVersion }}/fpm/pool.d/{{ $user }}.conf"

if [[ ! -f "$POOL_CONF" ]]; then
    echo "Isolated PHP-FPM pool for {{ $user }} (PHP {{ $phpVersion }}) does not exist."
    exit 0
fi

echo "Deleting isolated PHP-FPM pool for {{ $user }} (PHP {{ $phpVersion }})..."

rm -f "$POOL_CONF"

echo "Reloading PHP {{ $phpVersion }} FPM..."
systemctl reload php{{ $phpVersion }}-fpm

echo "Isolated PHP-FPM pool for {{ $user }} (PHP {{ $phpVersion }}) deleted successfully!"
