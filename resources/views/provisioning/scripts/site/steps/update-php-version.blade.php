#!/bin/bash
set -eo pipefail

# Netipar Cloud - Update Site PHP Version
# Site: {{ $domain }}
# Old Version: {{ $oldVersion }}
# New Version: {{ $newVersion }}

echo "Updating PHP version for {{ $domain }} from {{ $oldVersion }} to {{ $newVersion }}..."

NGINX_CONF="/etc/nginx/sites-available/{{ $domain }}"

if [[ ! -f "$NGINX_CONF" ]]; then
    echo "Error: Nginx configuration not found at $NGINX_CONF"
    exit 1
fi

#
# Update Nginx Configuration
#

echo "Updating Nginx configuration..."

# Update socket path (all sites use isolated PHP-FPM pools)
OLD_SOCKET="php{{ $oldVersion }}-fpm-{{ $user }}.sock"
NEW_SOCKET="php{{ $newVersion }}-fpm-{{ $user }}.sock"

sed -i.bak "s|$OLD_SOCKET|$NEW_SOCKET|g" "$NGINX_CONF"

#
# Test Nginx Configuration
#

echo "Testing Nginx configuration..."
if ! nginx -t; then
    echo "Nginx configuration test failed, restoring backup..."
    mv "$NGINX_CONF.bak" "$NGINX_CONF"
    exit 1
fi

#
# Reload Nginx
#

echo "Reloading Nginx..."
systemctl reload nginx

#
# Note: Isolated PHP-FPM pool management is handled by separate jobs
# (CreateIsolatedPhpFpmPoolJob / DeleteIsolatedPhpFpmPoolJob)
#

#
# Cleanup
#

rm -f "$NGINX_CONF.bak"

echo "PHP version updated successfully to {{ $newVersion }} for {{ $domain }}!"
