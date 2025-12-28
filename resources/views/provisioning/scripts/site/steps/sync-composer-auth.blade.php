#!/bin/bash
set -e

# Netipar Cloud - Sync Composer Auth
# Site: {{ $site->domain }}

echo "Syncing Composer auth.json for {{ $site->domain }}..."

SITE_PATH="{{ $fullPath }}"
AUTH_FILE="$SITE_PATH/auth.json"

# Create auth.json with credentials
cat > "$AUTH_FILE" << 'AUTHJSONEOF'
{!! $authJson !!}
AUTHJSONEOF

# Set proper permissions
chmod 600 "$AUTH_FILE"

# Link to current release if exists
if [ -d "$SITE_PATH/current" ]; then
    ln -sf "$AUTH_FILE" "$SITE_PATH/current/auth.json"
fi

echo "Composer auth.json synced successfully!"
