#!/bin/bash
set -e

# Netipar Cloud - Update Existing Certificate
# Site: {{ $site->domain }}
# Certificate ID: {{ $certificate->id }}

echo "Updating certificate for {{ $site->domain }}..."

# Variables
CERT_PATH="{{ $certPath }}"

#
# Create certificate directory if needed
#

mkdir -p "$CERT_PATH"

#
# Write certificate file
#

echo "Writing certificate file..."
cat > "$CERT_PATH/fullchain.crt" << 'CERTEOF'
{{ $certificateContent }}
CERTEOF

@if($privateKeyContent)
#
# Write private key file
#

echo "Writing private key file..."
cat > "$CERT_PATH/private.key" << 'KEYEOF'
{{ $privateKeyContent }}
KEYEOF

chmod 600 "$CERT_PATH/private.key"
@endif

chmod 644 "$CERT_PATH/fullchain.crt"

echo "Certificate files written"

#
# Parse certificate expiry
#

EXPIRY_DATE=$(openssl x509 -in "$CERT_PATH/fullchain.crt" -noout -enddate 2>/dev/null | cut -d= -f2)
if [ -n "$EXPIRY_DATE" ]; then
    FORMATTED_DATE=$(date -d "$EXPIRY_DATE" +%Y-%m-%d 2>/dev/null || date -j -f "%b %d %T %Y %Z" "$EXPIRY_DATE" +%Y-%m-%d 2>/dev/null || echo "")
    if [ -n "$FORMATTED_DATE" ]; then
        echo "CERT_EXPIRES:$FORMATTED_DATE"
    fi
fi

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

echo "Certificate updated successfully!"
