#!/bin/bash
set -e

# Netipar Cloud - Renew Let's Encrypt Certificate
# Site: {{ $site->domain }}
# Domains: {{ implode(', ', $domains) }}
# Certificate ID: {{ $certificate->id }}

echo "Renewing Let's Encrypt certificate for {{ implode(', ', $domains) }}..."

@include('provisioning.scripts.certificate.partials.dehydrated-setup')

@include('provisioning.scripts.certificate.partials.hooks-script')

#
# Run dehydrated to renew certificate
#

echo "Requesting certificate renewal from Let's Encrypt..."

cd "$LE_DIR"

# Force renewal
./dehydrated --cron --hook ./hooks/netipar.sh --challenge http-01 --force

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

echo "Let's Encrypt certificate renewed successfully!"
