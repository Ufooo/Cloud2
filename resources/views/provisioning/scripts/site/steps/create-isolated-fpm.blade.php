#!/bin/bash
set -eo pipefail

# Netipar Cloud - Create Isolated PHP-FPM Pool
# Site: {{ $domain }}
# User: {{ $user }}
# PHP Version: {{ $phpVersion }}

POOL_CONF="/etc/php/{{ $phpVersion }}/fpm/pool.d/{{ $user }}.conf"
WWW_CONF="/etc/php/{{ $phpVersion }}/fpm/pool.d/www.conf"

if [[ -f "$POOL_CONF" ]]; then
    echo "Isolated PHP-FPM pool for {{ $user }} (PHP {{ $phpVersion }}) already exists."
    exit 0
fi

echo "Creating isolated PHP-FPM pool for {{ $user }} (PHP {{ $phpVersion }})..."

#
# Copy www.conf template and modify with sed
#

cp "$WWW_CONF" "$POOL_CONF"

# Pool name
sed -i "s/\[www\]/[{{ $user }}]/" "$POOL_CONF"

# User and group
sed -i "s/^user = www-data/user = {{ $user }}/" "$POOL_CONF"
sed -i "s/^group = www-data/group = {{ $user }}/" "$POOL_CONF"

# Socket path
sed -i "s|^listen = .*|listen = /var/run/php/php{{ $phpVersion }}-fpm-{{ $user }}.sock|" "$POOL_CONF"

# Listen owner/group (keep www-data for nginx compatibility)
sed -i "s/^listen.owner = .*/listen.owner = www-data/" "$POOL_CONF"
sed -i "s/^listen.group = .*/listen.group = www-data/" "$POOL_CONF"
sed -i "s/^listen.mode = .*/listen.mode = 0666/" "$POOL_CONF"

# Process manager settings (adjust for isolated pool)
sed -i "s/^pm.max_children = .*/pm.max_children = 10/" "$POOL_CONF"
sed -i "s/^pm.start_servers = .*/pm.start_servers = 2/" "$POOL_CONF"
sed -i "s/^pm.min_spare_servers = .*/pm.min_spare_servers = 1/" "$POOL_CONF"
sed -i "s/^pm.max_spare_servers = .*/pm.max_spare_servers = 3/" "$POOL_CONF"

# Add max_requests if not present
grep -q "^pm.max_requests" "$POOL_CONF" || echo "pm.max_requests = 500" >> "$POOL_CONF"

# Add request_terminate_timeout if not present
grep -q "^request_terminate_timeout" "$POOL_CONF" || echo "request_terminate_timeout = 60" >> "$POOL_CONF"

echo "Isolated PHP-FPM pool for {{ $user }} (PHP {{ $phpVersion }}) created successfully!"
