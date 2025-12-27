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
# Create SSL nginx config
#

echo "Creating Nginx SSL configuration..."

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
