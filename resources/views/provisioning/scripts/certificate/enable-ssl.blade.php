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
# Update Nginx configuration for each domain in the certificate
#

echo "Updating Nginx configuration..."

@foreach($domains as $domain)
NGINX_CONF="/etc/nginx/sites-available/{{ $domain }}"

if [ -f "$NGINX_CONF" ]; then
    echo "Enabling SSL for {{ $domain }}..."

    if [ $VERSION_NUM -ge $MIN_VERSION ]; then
        # Nginx 1.26+ uses http2 as a separate directive
        sed -i 's/^    # listen 443 ssl http2;$/    listen 443 ssl;\n    http2 on;/' "$NGINX_CONF"
        sed -i 's/^    # listen \[::\]:443 ssl http2;$/    listen [::]:443 ssl;/' "$NGINX_CONF"
    else
        # Older nginx versions use http2 with listen directive
        sed -i 's/^    # listen 443 ssl http2;$/    listen 443 ssl http2;/' "$NGINX_CONF"
        sed -i 's/^    # listen \[::\]:443 ssl http2;$/    listen [::]:443 ssl http2;/' "$NGINX_CONF"
    fi
else
    echo "WARNING: Nginx config not found for {{ $domain }}, skipping..."
fi
@endforeach

echo "SSL listen directives enabled in Nginx config"

#
# Create SSL configuration file
#

echo "Creating SSL configuration..."

mkdir -p "$SITE_CONF_DIR/server"

cat > "$SITE_CONF_DIR/server/ssl.conf" << 'SSLEOF'
ssl_certificate {{ $certPath }}/fullchain.crt;
ssl_certificate_key {{ $certPath }}/private.key;

ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;

ssl_session_timeout 1d;
ssl_session_cache shared:SSL:10m;
ssl_session_tickets off;

ssl_stapling on;
ssl_stapling_verify on;
resolver 1.1.1.1 8.8.8.8 valid=300s;
resolver_timeout 5s;
SSLEOF

echo "SSL configuration created"

#
# Create SSL redirect configuration (HTTP to HTTPS)
#

echo "Creating SSL redirect configuration..."

@include('provisioning.scripts.certificate.partials.ssl-redirect-config')

echo "SSL redirect configuration created"

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
