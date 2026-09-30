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

@include('provisioning.scripts.partials.nginx-http2-syntax')

if [ ! -f "$NGINX_CONF" ]; then
    echo "ERROR: Nginx config not found at $NGINX_CONF"
    exit 1
fi

#
# Back up everything this script writes or deletes
#
# A failed update has to leave the server exactly as it found it. The main config, both redirect
# includes and the site-level include all change below, and a config that fails nginx -t but stays
# on disk breaks every later reload of the server. A file that did not exist before is removed
# again on rollback.
#
# The copies live outside /etc/nginx on purpose: nginx includes every file in a before/ directory,
# so a backup kept there would be loaded as configuration.
#

BACKUP_DIR=$(mktemp -d "${TMPDIR:-/tmp}/netipar-domain-update.XXXXXX")
ROLLBACK_ON_EXIT=false

TOUCHED_FILES=(
    "$NGINX_CONF"
    "$DOMAIN_CONF_DIR/before/redirect.conf"
    "$DOMAIN_CONF_DIR/before/ssl_redirect.conf"
@if($domain === $site->domain)
    "$SITE_CONF_DIR/before/redirect.conf"
@endif
)

restore_touched_files() {
    local touched_file

    for touched_file in "${TOUCHED_FILES[@]}"; do
        if [ -f "$BACKUP_DIR$touched_file" ]; then
            cp -p "$BACKUP_DIR$touched_file" "$touched_file"
        else
            rm -f "$touched_file"
        fi
    done
}

# Whatever stops the script before nginx has accepted the new files puts the old ones back.
#
# SIGPIPE is ignored and the message cannot fail the handler: when the SSH channel has already
# gone, writing the line would kill the shell a second time and nothing would be restored -
# exactly the case the rollback exists for.
finish() {
    if [ "$ROLLBACK_ON_EXIT" = true ]; then
        echo "Update did not complete, restoring backup..." || true
        restore_touched_files
    fi

    rm -rf "$BACKUP_DIR"
}
trap '' PIPE
trap finish EXIT

for TOUCHED_FILE in "${TOUCHED_FILES[@]}"; do
    if [ -f "$TOUCHED_FILE" ]; then
        mkdir -p "$BACKUP_DIR$(dirname "$TOUCHED_FILE")"
        cp -p "$TOUCHED_FILE" "$BACKUP_DIR$TOUCHED_FILE"
    fi
done

# Only now can a missing copy mean "did not exist before", so only now does a failure roll back.
ROLLBACK_ON_EXIT=true

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

@if($domain === $site->domain)
#
# Retire the site-level WWW redirect
#
# Provisioning used to write a plain http redirect into the include shared by every domain of
# the site, whatever the WWW redirect setting was. The Domain path owns the redirect now, and
# two files serving one name conflict. That file serves www.<site domain>, so it belongs to the
# record named after the site, whatever its type: marking another record as primary swaps the
# types but never changes the site domain. Only that record's update retires it; every other
# update has to leave it alone.
#

if [ -f "$SITE_CONF_DIR/before/redirect.conf" ]; then
    echo "Removing site-level WWW redirect configuration..."
    rm -f "$SITE_CONF_DIR/before/redirect.conf"
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
# Match the http2 syntax to the installed nginx
#
# The main config and the www redirect carry 443 blocks that the templates write with the
# standalone "http2 on;" directive, which nginx only knows from 1.25.1.
#

nginx_http2_syntax "$NGINX_CONF" "$DOMAIN_CONF_DIR/before/redirect.conf"

#
# Test and Reload Nginx
#

echo "Testing Nginx configuration..."
if ! nginx -t; then
    echo "ERROR: Nginx configuration test failed, restoring backup..."
    restore_touched_files
    ROLLBACK_ON_EXIT=false
    nginx -t
    echo "Backup restored"
    exit 1
fi

ROLLBACK_ON_EXIT=false

echo "Reloading Nginx..."
service nginx reload

echo "Domain {{ $domain }} updated successfully!"
