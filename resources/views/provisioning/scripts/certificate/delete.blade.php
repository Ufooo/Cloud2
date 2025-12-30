#!/bin/bash
set -e

# Netipar Cloud - Delete SSL Certificate
# Site: {{ $site->domain }}
# Domains: {{ implode(', ', $domains) }}
# Certificate ID: {{ $certificate->id }}

echo "Deleting SSL certificate for {{ implode(', ', $domains) }}..."

# Variables
CERT_PATH="{{ $certPath }}"
SITE_SSL_DIR="/etc/nginx/ssl/{{ $site->id }}"

#
# Remove certificate files
#

echo "Removing certificate files..."

if [ -d "$CERT_PATH" ]; then
    rm -rf "$CERT_PATH"
    echo "Certificate directory removed: $CERT_PATH"
else
    echo "Certificate directory not found (may have been removed already)"
fi

#
# Clean up empty SSL directory
#

if [ -d "$SITE_SSL_DIR" ]; then
    if [ -z "$(ls -A $SITE_SSL_DIR)" ]; then
        rmdir "$SITE_SSL_DIR"
        echo "Empty SSL directory removed: $SITE_SSL_DIR"
    else
        echo "SSL directory contains other certificates, keeping: $SITE_SSL_DIR"
    fi
fi

#
# Remove automatic renewal cron job
#

echo "Removing automatic renewal cron job..."

if [ -f "/etc/cron.d/letsencrypt-renew-{{ $certificate->id }}" ]; then
    rm -f "/etc/cron.d/letsencrypt-renew-{{ $certificate->id }}"
    echo "Renewal cron job removed"
else
    echo "Renewal cron job not found (may have been removed already)"
fi

#
# Remove renewal script and output files
#

echo "Removing renewal scripts..."

if [ -d "/home/{{ $siteUser }}/.letsencrypt-renew/{{ $certificate->id }}" ]; then
    rm -rf "/home/{{ $siteUser }}/.letsencrypt-renew/{{ $certificate->id }}"
    echo "Renewal scripts removed"
else
    echo "Renewal scripts not found (may have been removed already)"
fi

echo "SSL certificate deleted successfully for {{ implode(', ', $domains) }}!"
echo "All certificate files and configurations have been removed from the server"
