#!/bin/bash
set -e

# Netipar Cloud - Update Domain Configuration
# Domain: {{ $domain }}
# Site: {{ $site->domain }}

echo "Updating domain {{ $domain }} on site {{ $site->domain }}..."

#
# Variables
#

NGINX_CONF="/etc/nginx/sites-available/{{ $domain }}"
SITE_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->domain }}"
DOMAIN_CONF_DIR="$SITE_CONF_DIR/{{ $domain }}"

if [ ! -f "$NGINX_CONF" ]; then
    echo "ERROR: Nginx config not found at $NGINX_CONF"
    exit 1
fi

#
# Backup current config
#

cp "$NGINX_CONF" "$NGINX_CONF.bak"

#
# Preserve current SSL state (listen directives and certificate paths)
#

CURRENT_LISTEN=$(grep -m1 '^\s*listen ' "$NGINX_CONF" | sed 's/^\s*//')
HAS_SSL=false
if echo "$CURRENT_LISTEN" | grep -q '443'; then
    HAS_SSL=true
fi

SSL_CERT_LINE=""
SSL_KEY_LINE=""
if [ "$HAS_SSL" = true ]; then
    SSL_CERT_LINE=$(grep '^\s*ssl_certificate ' "$NGINX_CONF" | head -1 | sed 's/^\s*//')
    SSL_KEY_LINE=$(grep '^\s*ssl_certificate_key ' "$NGINX_CONF" | head -1 | sed 's/^\s*//')
fi

#
# Write new Nginx configuration
#

echo "Regenerating Nginx configuration..."

cat > "$NGINX_CONF" << 'NGINXEOF'
{!! $nginxConfig !!}
NGINXEOF

#
# Restore SSL state if it was active
#

if [ "$HAS_SSL" = true ]; then
    echo "Restoring SSL configuration..."

    NGINX_VERSION=$(nginx -v 2>&1 | grep -o '[0-9.]\+')
    VERSION_NUM=$(echo "$NGINX_VERSION" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }')
    MIN_VERSION=$(echo "1.26.0" | awk -F. '{ printf("%d%03d%03d%03d\n", $1,$2,$3,$4); }')

    if [ $VERSION_NUM -ge $MIN_VERSION ]; then
        sed -i 's/^    listen 80;$/    listen 443 ssl;\n    http2 on;/' "$NGINX_CONF"
        sed -i 's/^    listen \[::\]:80;$/    listen [::]:443 ssl;/' "$NGINX_CONF"
    else
        sed -i 's/^    listen 80;$/    listen 443 ssl http2;/' "$NGINX_CONF"
        sed -i 's/^    listen \[::\]:80;$/    listen [::]:443 ssl http2;/' "$NGINX_CONF"
    fi

    if [ -n "$SSL_CERT_LINE" ]; then
        sed -i "s|^    # ssl_certificate;$|    $SSL_CERT_LINE|" "$NGINX_CONF"
    fi
    if [ -n "$SSL_KEY_LINE" ]; then
        sed -i "s|^    # ssl_certificate_key;$|    $SSL_KEY_LINE|" "$NGINX_CONF"
    fi
fi

#
# Update WWW redirect configuration
#

@if($wwwRedirectConfig)
echo "Updating WWW redirect configuration..."
mkdir -p "$DOMAIN_CONF_DIR/before"
cat > "$DOMAIN_CONF_DIR/before/redirect.conf" << 'REDIRECTEOF'
{!! $wwwRedirectConfig !!}
REDIRECTEOF
@else
if [ -f "$DOMAIN_CONF_DIR/before/redirect.conf" ]; then
    echo "Removing WWW redirect configuration..."
    rm -f "$DOMAIN_CONF_DIR/before/redirect.conf"
fi
@endif

#
# Update SSL redirect if active
#

if [ "$HAS_SSL" = true ] && [ -f "$DOMAIN_CONF_DIR/before/ssl_redirect.conf" ]; then
    echo "Updating SSL redirect configuration..."
    cat > "$DOMAIN_CONF_DIR/before/ssl_redirect.conf" << 'REDIRECTEOF'
# HTTP to HTTPS redirect for {{ $domain }}
server {
    listen 80;
    listen [::]:80;
    server_tokens off;
@if($domainRecord->allow_wildcard)
    server_name {{ $domain }} *.{{ $domain }};
@else
    server_name {{ $domain }};
@endif

    location / {
        return 301 https://$host$request_uri;
    }

    # Allow Let's Encrypt renewals via HTTP
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        alias /home/{{ $site->user }}/.letsencrypt/;
    }
}
REDIRECTEOF
fi

#
# Test and Reload Nginx
#

echo "Testing Nginx configuration..."
if ! nginx -t; then
    echo "ERROR: Nginx configuration test failed, restoring backup..."
    cp "$NGINX_CONF.bak" "$NGINX_CONF"
    nginx -t
    echo "Backup restored"
    exit 1
fi

rm -f "$NGINX_CONF.bak"

echo "Reloading Nginx..."
service nginx reload

echo "Domain {{ $domain }} updated successfully!"
