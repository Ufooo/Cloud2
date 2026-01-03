#!/bin/bash
set -e

# Netipar Cloud - Enable SSL Certificate
# Site: {{ $site->domain }}
# Domains: {{ implode(', ', $domains) }}
# Certificate ID: {{ $certificate->id }}

echo "Enabling SSL for {{ implode(', ', $domains) }}..."

# Variables
SITE_CONF_DIR="{{ $siteConfDir }}"
CERT_PATH="{{ $certPath }}"

#
# Verify certificate files exist
#

if [ ! -f "$CERT_PATH/fullchain.crt" ]; then
    echo "ERROR: Certificate file not found at $CERT_PATH/fullchain.crt"
    exit 1
fi

if [ ! -f "$CERT_PATH/private.key" ]; then
    echo "ERROR: Private key not found at $CERT_PATH/private.key"
    exit 1
fi

echo "Certificate files verified"

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
    echo "Enabling SSL for {{ $domain }}..."

    # Update listen directives: remove port 80, add port 443 with SSL
    if [ $VERSION_NUM -ge $MIN_VERSION ]; then
        # Nginx 1.26+ uses http2 as a separate directive
        sed -i 's/^    listen 80;$/    listen 443 ssl;\n    http2 on;/' "$NGINX_CONF"
        sed -i 's/^    listen \[::\]:80;$/    listen [::]:443 ssl;/' "$NGINX_CONF"
    else
        # Older nginx versions use http2 with listen directive
        sed -i 's/^    listen 80;$/    listen 443 ssl http2;/' "$NGINX_CONF"
        sed -i 's/^    listen \[::\]:80;$/    listen [::]:443 ssl http2;/' "$NGINX_CONF"
    fi

    # Update inline SSL certificate paths
    sed -i 's|^    # ssl_certificate;$|    ssl_certificate '"$CERT_PATH"'/fullchain.crt;|' "$NGINX_CONF"
    sed -i 's|^    # ssl_certificate_key;$|    ssl_certificate_key '"$CERT_PATH"'/private.key;|' "$NGINX_CONF"

    echo "  SSL enabled in nginx config"

    # Create SSL redirect configuration
    echo "  Creating SSL redirect..."
    mkdir -p "$DOMAIN_CONF_DIR/before"

    cat > "$DOMAIN_CONF_DIR/before/ssl_redirect.conf" << 'REDIRECTEOF'
# HTTP to HTTPS redirect for {{ $domain }}
server {
    listen 80;
    listen [::]:80;
    server_tokens off;
    server_name {{ $domain }};

    location / {
        return 301 https://$host$request_uri;
    }

    # Allow Let's Encrypt renewals via HTTP
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root {{ $wellknown }};
    }
}
REDIRECTEOF

    echo "  SSL redirect created"

else
    echo "WARNING: Nginx config not found for {{ $domain }}, skipping..."
fi
@endforeach

echo "SSL configuration updated for all domains"

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

echo "SSL enabled successfully for {{ implode(', ', $domains) }}!"
echo "Site is now accessible via HTTPS"
