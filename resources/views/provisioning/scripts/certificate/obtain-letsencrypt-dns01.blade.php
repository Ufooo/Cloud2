#!/bin/bash
set -e

# Netipar Cloud - Obtain Let's Encrypt Certificate (DNS-01 Challenge)
# Site: {{ $site->domain }}
# Domains: {{ implode(', ', $domains) }}
# Verification Method: DNS-01 (Wildcard support)

echo "Obtaining Let's Encrypt certificate via DNS-01 challenge for {{ implode(', ', $domains) }}..."

#
# Install dependencies
#

echo "Checking dependencies..."
apt-get update -qq
apt-get install -y -qq bsdmainutils dnsutils curl jq > /dev/null 2>&1 || true

@include('provisioning.scripts.certificate.partials.dehydrated-setup')

@include('provisioning.scripts.certificate.partials.hooks-script-dns01')

#
# Run dehydrated to obtain certificate
#

echo "Requesting certificate from Let's Encrypt using DNS-01 challenge..."

cd "$LE_DIR"

# Register account if needed
if [ ! -d ~/letsencrypt_accounts ]; then
    echo "Registering ACME account..."
    ./dehydrated --register --accept-terms
fi

# Request certificate with DNS-01 challenge
./dehydrated --cron --hook ./hooks/netipar-dns.sh --challenge dns-01

#
# Update Nginx configuration with inline SSL certificate paths
#

echo "Updating Nginx configuration for each domain..."

@foreach($domains as $domain)
@php
// Skip wildcard domains - they don't have their own nginx config
$displayDomain = str_starts_with($domain, '*.') ? substr($domain, 2) : $domain;
@endphp
NGINX_CONF="/etc/nginx/sites-available/{{ $displayDomain }}"

if [ -f "$NGINX_CONF" ]; then
    echo "Updating SSL certificate paths for {{ $displayDomain }}..."

    # Update inline SSL certificate paths
    sed -i 's|^    # ssl_certificate;$|    ssl_certificate '"$CERT_PATH"'/fullchain.crt;|' "$NGINX_CONF"
    sed -i 's|^    # ssl_certificate_key;$|    ssl_certificate_key '"$CERT_PATH"'/private.key;|' "$NGINX_CONF"

    echo "  SSL paths updated"
else
    echo "WARNING: Nginx config not found for {{ $displayDomain }}, skipping..."
fi
@endforeach

echo "SSL configuration updated for all domains"

#
# Remove legacy ssl.conf (SSL settings are now in site.conf)
#

if [ -f "$SITE_CONF_DIR/server/ssl.conf" ]; then
    echo "Removing legacy ssl.conf (SSL settings moved to site.conf)..."
    rm -f "$SITE_CONF_DIR/server/ssl.conf"
fi

#
# Test and reload Nginx
#

echo "Testing Nginx configuration..."
nginx -t

echo "Reloading Nginx..."
service nginx reload

@include('provisioning.scripts.certificate.partials.parse-expiry')

#
# Cleanup
#

echo "Cleaning up temporary files..."
rm -rf "$LE_DIR"

echo "Let's Encrypt certificate (DNS-01) obtained successfully!"
