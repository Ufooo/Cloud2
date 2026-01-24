#!/bin/bash
set -e

# Netipar Cloud - Sync Composer Auth
# Site: {{ $site->domain }}

echo "Syncing Composer auth.json for {{ $site->domain }}..."

SITE_ROOT="{{ $siteRoot }}"
APPLICATION_PATH="{{ $applicationPath }}"
AUTH_FILE="$SITE_ROOT/auth.json"

# Create auth.json with credentials
cat > "$AUTH_FILE" << 'AUTHJSONEOF'
{!! $authJson !!}
AUTHJSONEOF

# Set proper permissions
chmod 600 "$AUTH_FILE"

# Link to project directory (only if paths are different)
if [ "$APPLICATION_PATH" != "$SITE_ROOT" ]; then
    ln -sf "$AUTH_FILE" "$APPLICATION_PATH/auth.json"
fi

echo "Composer auth.json synced successfully!"
