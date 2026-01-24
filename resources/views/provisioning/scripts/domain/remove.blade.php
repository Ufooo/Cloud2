#!/bin/bash
set -e

# Netipar Cloud - Remove Domain
# Domain: {{ $domain }}
# Site: {{ $site->domain }}

echo "Removing domain {{ $domain }} from site {{ $site->domain }}..."

#
# Variables
#

SITE_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->domain }}"
DOMAIN_CONF_DIR="$SITE_CONF_DIR/{{ $domain }}"

#
# Disable Site in Nginx
#

if [ -L "/etc/nginx/sites-enabled/{{ $domain }}" ]; then
    echo "Disabling domain in Nginx..."
    rm -f /etc/nginx/sites-enabled/{{ $domain }}
fi

#
# Remove Nginx Site Configuration
#

if [ -f "/etc/nginx/sites-available/{{ $domain }}" ]; then
    echo "Removing Nginx site configuration..."
    rm -f /etc/nginx/sites-available/{{ $domain }}
fi

#
# Remove Domain Configuration Directory
#

if [ -d "$DOMAIN_CONF_DIR" ]; then
    echo "Removing domain configuration directory..."
    rm -rf "$DOMAIN_CONF_DIR"
fi

#
# Remove Error Log
#

if [ -f "/var/log/nginx/{{ $domain }}-error.log" ]; then
    echo "Removing Nginx error log..."
    rm -f /var/log/nginx/{{ $domain }}-error.log
fi

@include('provisioning.scripts.site.partials.cors-config')

#
# Test and Reload Nginx
#

echo "Testing Nginx configuration..."
nginx -t

echo "Reloading Nginx..."
service nginx reload

echo "Domain {{ $domain }} removed successfully!"
