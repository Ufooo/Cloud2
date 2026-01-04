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
# Update Nginx configuration for each domain
#

echo "Updating Nginx configuration..."

@foreach($domains as $domain)
NGINX_CONF="/etc/nginx/sites-available/{{ $domain }}"
DOMAIN_CONF_DIR="$SITE_CONF_DIR/{{ $domain }}"

if [ -f "$NGINX_CONF" ]; then
    echo "Disabling SSL for {{ $domain }}..."

    # Update listen directives: change 443 ssl back to 80
    if [ $VERSION_NUM -ge $MIN_VERSION ]; then
        # Nginx 1.26+ - remove http2 directive and change to port 80
        sed -i 's/^    listen 443 ssl;$/    listen 80;/' "$NGINX_CONF"
        sed -i '/^    http2 on;$/d' "$NGINX_CONF"
        sed -i 's/^    listen \[::\]:443 ssl;$/    listen [::]:80;/' "$NGINX_CONF"
    else
        # Older nginx versions
        sed -i 's/^    listen 443 ssl http2;$/    listen 80;/' "$NGINX_CONF"
        sed -i 's/^    listen \[::\]:443 ssl http2;$/    listen [::]:80;/' "$NGINX_CONF"
    fi

    # Comment out inline SSL certificate paths
    sed -i 's|^    ssl_certificate .*$|    # ssl_certificate;|' "$NGINX_CONF"
    sed -i 's|^    ssl_certificate_key .*$|    # ssl_certificate_key;|' "$NGINX_CONF"

    echo "  SSL disabled in nginx config"

    # Remove SSL redirect configuration
    if [ -f "$DOMAIN_CONF_DIR/before/ssl_redirect.conf" ]; then
        rm -f "$DOMAIN_CONF_DIR/before/ssl_redirect.conf"
        echo "  SSL redirect removed"
    fi

else
    echo "WARNING: Nginx config not found for {{ $domain }}, skipping..."
fi
@endforeach

echo "SSL configuration disabled for all domains"

#
# Remove legacy ssl.conf (SSL settings are now in site.conf)
#

if [ -f "$SITE_CONF_DIR/server/ssl.conf" ]; then
    echo "Removing legacy ssl.conf (SSL settings moved to site.conf)..."
    rm -f "$SITE_CONF_DIR/server/ssl.conf"
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
