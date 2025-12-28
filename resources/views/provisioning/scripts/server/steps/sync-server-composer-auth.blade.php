#!/bin/bash
set -e

# Netipar Cloud - Sync Server Composer Auth
# Server: {{ $server->name }}
# User: {{ $user }}

echo "Syncing Composer auth.json for user {{ $user }}..."

# Ensure jq is installed
if ! type jq &> /dev/null; then
    echo "Installing jq..."
    apt-get update && apt-get install jq -y
fi

# Get composer config home path for the user
COMPOSER_PATH=$(su - {{ $user }} -c "php /usr/local/bin/composer config --global home")
AUTH_PATH="$COMPOSER_PATH/auth.json"

echo "Using auth.json path: ${AUTH_PATH}..."

# Create auth.json with standard composer structure if not exists or is empty/invalid
if [ ! -f "$AUTH_PATH" ] || [ ! -s "$AUTH_PATH" ] || ! jq empty "$AUTH_PATH" 2>/dev/null; then
    echo "Creating new auth.json..."
    cat > "$AUTH_PATH" << 'EOF'
{
    "bitbucket-oauth": {},
    "github-oauth": {},
    "gitlab-oauth": {},
    "gitlab-token": {},
    "http-basic": {},
    "bearer": {},
    "forgejo-token": {}
}
EOF
    chown {{ $user }}:{{ $user }} "$AUTH_PATH"
    chmod 600 "$AUTH_PATH"
fi

# Ensure http-basic key exists
if ! jq -e '.["http-basic"]' "$AUTH_PATH" > /dev/null 2>&1; then
    echo "Adding http-basic key..."
    TMP_FILE=$(mktemp)
    jq '. + {"http-basic": {}}' "$AUTH_PATH" > "$TMP_FILE" && mv "$TMP_FILE" "$AUTH_PATH"
fi

@foreach($jqCommands as $jqCommand)
echo "Updating credential..."
TMP_FILE=$(mktemp)
jq '{!! $jqCommand !!}' "$AUTH_PATH" > "$TMP_FILE" && mv "$TMP_FILE" "$AUTH_PATH"
@endforeach

# Set proper permissions
chown {{ $user }}:{{ $user }} "$AUTH_PATH"
chmod 600 "$AUTH_PATH"

echo "Composer auth.json synced successfully for user {{ $user }}!"
