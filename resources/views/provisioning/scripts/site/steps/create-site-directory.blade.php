#!/bin/bash
set -e

# Netipar Cloud - Create Site Directory
# Site: {{ $domain }}
# User: {{ $user }}

echo "Creating site directory structure..."

SITE_PATH="{{ $fullPath }}"

if [ -d "$SITE_PATH" ]; then
    echo "Site directory already exists at $SITE_PATH"
    exit 0
fi

echo "Creating site directory at $SITE_PATH..."
mkdir -p "$SITE_PATH"

@if($siteType->supportsZeroDowntime())
# Zero-downtime deployment structure (Laravel, Statamic, Symfony)
mkdir -p "$SITE_PATH/releases"
mkdir -p "$SITE_PATH/storage"
mkdir -p "$SITE_PATH/storage/app/public"
mkdir -p "$SITE_PATH/storage/framework/cache"
mkdir -p "$SITE_PATH/storage/framework/sessions"
mkdir -p "$SITE_PATH/storage/framework/views"
mkdir -p "$SITE_PATH/storage/logs"

# Create initial release directory
TARGET_DIR="$SITE_PATH/releases/initial"
mkdir -p "$TARGET_DIR"

# Create public directory
mkdir -p "$TARGET_DIR{{ $webDirectory }}"

# Create default index file
{!! $defaultIndexScript !!}

# Create symlink to current release
ln -sfn "$TARGET_DIR" "$SITE_PATH/current"

# Create shared .env file
if [ ! -f "$SITE_PATH/.env" ]; then
    touch "$SITE_PATH/.env"
fi

# Symlink .env to initial release
ln -sfn "$SITE_PATH/.env" "$TARGET_DIR/.env"

# Symlink storage to initial release
ln -sfn "$SITE_PATH/storage" "$TARGET_DIR/storage"

# Set storage permissions
chmod -R 775 "$SITE_PATH/storage"
@else
# Simple directory structure (WordPress, HTML, PHP, etc.)
mkdir -p "$SITE_PATH{{ $webDirectory }}"

# Create default index file
{!! $defaultIndexScript !!}
@endif

echo "Site directory structure created successfully"
