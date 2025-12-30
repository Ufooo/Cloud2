#!/bin/bash
set -e

# Netipar Cloud - Disable Domain
# Domain: {{ $domain }}
# Site: {{ $site->domain }}

echo "Disabling domain {{ $domain }} for site {{ $site->domain }}..."

#
# Variables
#

SITES_ENABLED="/etc/nginx/sites-enabled/{{ $domain }}"

#
# Remove Symlink
#

if [ -L "$SITES_ENABLED" ]; then
    echo "Removing symlink from sites-enabled..."
    rm -f "$SITES_ENABLED"
else
    echo "Symlink does not exist, nothing to remove."
fi

#
# Test and Reload Nginx
#

echo "Testing Nginx configuration..."
nginx -t

echo "Reloading Nginx..."
service nginx reload

echo "Domain {{ $domain }} disabled successfully!"
echo "Note: Configuration file remains in sites-available for future re-enabling."
