#!/bin/bash
set -e

# Netipar Cloud - Create Environment File
# Site: {{ $site->domain }}
# Step: Creating Environment File

echo "Creating environment file for {{ $site->domain }}..."

SITE_PATH="{{ $fullPath }}"
CURRENT_PATH="$SITE_PATH/current"

@if($environment)
#
# Create .env File
#

echo "Writing environment variables..."

cat > "$CURRENT_PATH/.env" << 'ENVEOF'
{!! $environment !!}
ENVEOF

chmod 644 "$CURRENT_PATH/.env"
chown {{ $user }}:{{ $user }} "$CURRENT_PATH/.env"

echo "Environment file created successfully!"
@else
echo "No environment variables configured, skipping..."

# Create empty .env file if it doesn't exist
if [ ! -f "$CURRENT_PATH/.env" ]; then
    touch "$CURRENT_PATH/.env"
    chmod 644 "$CURRENT_PATH/.env"
    chown {{ $user }}:{{ $user }} "$CURRENT_PATH/.env"
fi
@endif
