#!/bin/bash
set -e

# Netipar Cloud - Obtain Let's Encrypt Certificate
# Site: {{ $site->domain }}
# Domains: {{ implode(', ', $domains) }}

echo "Obtaining Let's Encrypt certificate for {{ implode(', ', $domains) }}..."

#
# Install dependencies
#

echo "Checking dependencies..."
apt-get update -qq
apt-get install -y -qq bsdmainutils dnsutils > /dev/null 2>&1 || true

@include('provisioning.scripts.certificate.partials.dehydrated-setup')

@include('provisioning.scripts.certificate.partials.hooks-script')

#
# Run dehydrated to obtain certificate
#

echo "Requesting certificate from Let's Encrypt..."

cd "$LE_DIR"

# Register account if needed
if [ ! -d ~/letsencrypt_accounts ]; then
    echo "Registering ACME account..."
    ./dehydrated --register --accept-terms
fi

# Request certificate
./dehydrated --cron --hook ./hooks/netipar.sh --challenge http-01

#
# Update Nginx configuration with inline SSL certificate paths
#

echo "Updating Nginx configuration for each domain..."

@foreach($domains as $domain)
NGINX_CONF="/etc/nginx/sites-available/{{ $domain }}"

if [ -f "$NGINX_CONF" ]; then
    echo "Updating SSL certificate paths for {{ $domain }}..."

    # Update inline SSL certificate paths
    sed -i 's|^    # ssl_certificate;$|    ssl_certificate '"$CERT_PATH"'/fullchain.crt;|' "$NGINX_CONF"
    sed -i 's|^    # ssl_certificate_key;$|    ssl_certificate_key '"$CERT_PATH"'/private.key;|' "$NGINX_CONF"

    echo "  SSL paths updated"
else
    echo "WARNING: Nginx config not found for {{ $domain }}, skipping..."
fi
@endforeach

echo "SSL configuration updated for all domains"

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

echo "Let's Encrypt certificate obtained successfully!"
