#!/bin/bash
set -e

# Netipar Cloud - Disable SSL Certificate
# Site: {{ $site->domain }}
# Domains: {{ implode(', ', $domains) }}
# Certificate ID: {{ $certificate->id }}

echo "Disabling SSL for {{ implode(', ', $domains) }}..."

# Variables
SITE_CONF_DIR="{{ $siteConfDir }}"
CERT_PATH="{{ $certPath }}"

#
# Determine nginx version for http2 syntax
#

NGINX_VERSION=$(nginx -v 2>&1 | grep -o '[0-9.]\+')
VERSION_NUM=$(echo "$NGINX_VERSION" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }')
MIN_VERSION=$(echo "1.26.0" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }')

#
# Update Nginx configuration for each domain in the certificate
#

echo "Updating Nginx configuration to disable SSL..."

@foreach($domains as $domain)
NGINX_CONF="/etc/nginx/sites-available/{{ $domain }}"

if [ -f "$NGINX_CONF" ]; then
    echo "Disabling SSL for {{ $domain }}..."

    if [ $VERSION_NUM -ge $MIN_VERSION ]; then
        # Nginx 1.26+ - comment SSL listen directives and remove http2 directive
        sed -i 's/^    listen 443 ssl;$/    # listen 443 ssl http2;/' "$NGINX_CONF"
        sed -i 's/^    http2 on;$//' "$NGINX_CONF"
        sed -i 's/^    listen \[::\]:443 ssl;$/    # listen [::]:443 ssl http2;/' "$NGINX_CONF"
    else
        # Older nginx versions - comment SSL listen directives with http2
        sed -i 's/^    listen 443 ssl http2;$/    # listen 443 ssl http2;/' "$NGINX_CONF"
        sed -i 's/^    listen \[::\]:443 ssl http2;$/    # listen [::]:443 ssl http2;/' "$NGINX_CONF"
    fi
else
    echo "WARNING: Nginx config not found for {{ $domain }}, skipping..."
fi
@endforeach

echo "SSL listen directives disabled in Nginx config"

#
# Remove SSL configuration file (but keep certificate files for reactivation)
#

echo "Removing SSL configuration..."

if [ -f "$SITE_CONF_DIR/server/ssl.conf" ]; then
    rm -f "$SITE_CONF_DIR/server/ssl.conf"
    echo "SSL configuration removed"
else
    echo "SSL configuration file not found (may have been removed already)"
fi

#
# Remove SSL redirect configuration
#

echo "Removing SSL redirect configuration..."

if [ -f "$SITE_CONF_DIR/before/ssl_redirect.conf" ]; then
    rm -f "$SITE_CONF_DIR/before/ssl_redirect.conf"
    echo "SSL redirect configuration removed"
else
    echo "SSL redirect configuration not found (may have been removed already)"
fi

# Note: We intentionally keep certificate files at $CERT_PATH
# This allows quick reactivation without re-obtaining the certificate
echo "Certificate files preserved at $CERT_PATH for future reactivation"

#
# Test and reload Nginx
#

echo "Testing Nginx configuration..."
if ! nginx -t; then
    echo "ERROR: Nginx configuration test failed"
    exit 1
fi

echo "Reloading Nginx..."
service nginx reload

echo "SSL disabled successfully for {{ implode(', ', $domains) }}!"
echo "Site is now accessible only via HTTP"
