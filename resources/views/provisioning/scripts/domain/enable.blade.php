#!/bin/bash
set -e

# Netipar Cloud - Enable Domain
# Domain: {{ $domain }}
# Site: {{ $site->domain }}

echo "Enabling domain {{ $domain }} for site {{ $site->domain }}..."

#
# Variables
#

SITES_AVAILABLE="/etc/nginx/sites-available/{{ $domain }}"
SITES_ENABLED="/etc/nginx/sites-enabled/{{ $domain }}"

@include('provisioning.scripts.partials.ensure-fastcgi-defaults')

#
# Verify Configuration Exists
#

if [ ! -f "$SITES_AVAILABLE" ]; then
    echo "ERROR: Configuration file does not exist at $SITES_AVAILABLE"
    exit 1
fi

#
# Create Symlink
#

echo "Creating symlink in sites-enabled..."
if [ -L "$SITES_ENABLED" ]; then
    echo "Symlink already exists, removing old one..."
    rm -f "$SITES_ENABLED"
fi

ln -s "$SITES_AVAILABLE" "$SITES_ENABLED"

#
# Test and Reload Nginx
#

echo "Testing Nginx configuration..."
nginx -t

echo "Reloading Nginx..."
service nginx reload

echo "Domain {{ $domain }} enabled successfully!"
