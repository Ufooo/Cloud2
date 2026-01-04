#!/bin/bash
set -e

# Netipar Cloud - Renew Let's Encrypt Certificate (DNS-01 Challenge)
# Site: {{ $site->domain }}
# Domains: {{ implode(', ', $domains) }}
# Certificate ID: {{ $certificate->id }}
# Verification Method: DNS-01 (Wildcard support)

echo "Renewing Let's Encrypt certificate via DNS-01 challenge for {{ implode(', ', $domains) }}..."

#
# Install dependencies
#

echo "Checking dependencies..."
apt-get update -qq
apt-get install -y -qq bsdmainutils dnsutils curl jq > /dev/null 2>&1 || true

@include('provisioning.scripts.certificate.partials.dehydrated-setup')

@include('provisioning.scripts.certificate.partials.hooks-script-dns01')

#
# Run dehydrated to renew certificate
#

echo "Requesting certificate renewal from Let's Encrypt using DNS-01..."

cd "$LE_DIR"

# Force renewal with DNS-01 challenge
./dehydrated --cron --hook ./hooks/netipar-dns.sh --challenge dns-01 --force

#
# Remove legacy ssl.conf (SSL settings are now in site.conf)
#

if [ -f "$SITE_CONF_DIR/server/ssl.conf" ]; then
    echo "Removing legacy ssl.conf (SSL settings moved to site.conf)..."
    rm -f "$SITE_CONF_DIR/server/ssl.conf"
fi

#
# Reload Nginx
#

echo "Reloading Nginx with renewed certificate..."
nginx -t
service nginx reload

@include('provisioning.scripts.certificate.partials.parse-expiry')

#
# Cleanup
#

rm -rf "$LE_DIR"

echo "Let's Encrypt certificate (DNS-01) renewed successfully!"
