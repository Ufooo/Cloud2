#!/bin/bash
set -e

# Netipar Cloud - Deploy Site
# Site: {{ $domain }}
# User: {{ $user }}
# Branch: {{ $branch }}

echo "Deploying site {{ $domain }}..."

#
# Set Environment Variables for Deploy Script
#

export NIP_SITE_ROOT="{{ $fullPath }}"
export NIP_RELEASES_PATH="{{ $fullPath }}/releases"
export NIP_SITE_PATH="{{ $currentPath }}"
export NIP_SITE_BRANCH="{{ $branch }}"
export NIP_SITE_REPOSITORY="{{ $site->getCloneUrl() }}"
export NIP_PHP="/usr/bin/php{{ $phpVersion }}"
export NIP_PHP_FPM="php{{ $phpVersion }}-fpm"
export NIP_COMPOSER="composer"

@if($site->type->supportsZeroDowntime())
#
# $CREATE_RELEASE() - Zero-Downtime Deployment Setup
#

# Generate release directory name
export NIP_RELEASE_NAME=$(date +%Y%m%d%H%M%S)
export NIP_NEW_RELEASE_PATH="$NIP_RELEASES_PATH/$NIP_RELEASE_NAME"

echo "Creating new release: $NIP_RELEASE_NAME"

# Clone repository
echo "Cloning repository..."
git clone --branch $NIP_SITE_BRANCH --depth 1 "$NIP_SITE_REPOSITORY" "$NIP_NEW_RELEASE_PATH"

# Link environment file
echo "Linking environment file..."
rm -f "$NIP_NEW_RELEASE_PATH/.env"
ln -sfn "$NIP_SITE_ROOT/.env" "$NIP_NEW_RELEASE_PATH/.env"

# Link auth.json if it exists (for Composer authentication)
if [ -f "$NIP_SITE_ROOT/auth.json" ]; then
    echo "Linking auth.json file..."
    rm -f "$NIP_NEW_RELEASE_PATH/auth.json"
    ln -sfn "$NIP_SITE_ROOT/auth.json" "$NIP_NEW_RELEASE_PATH/auth.json"
fi

# Link shared storage directory
echo "Linking storage directories..."
rm -rf "$NIP_NEW_RELEASE_PATH/storage"
ln -sfn "$NIP_SITE_ROOT/storage" "$NIP_NEW_RELEASE_PATH/storage"

cd "$NIP_NEW_RELEASE_PATH"

#
# Executing deployment script
#

{!! $deployScriptContent !!}

#
# $ACTIVATE_RELEASE() - Activate new release
#

echo "Activating new release..."
ln -s "$NIP_NEW_RELEASE_PATH" "$NIP_SITE_ROOT/current-temp" && mv -Tf "$NIP_SITE_ROOT/current-temp" "$NIP_SITE_ROOT/current"

# Clean up old releases (keep last 5)
echo "Purging old releases..."
cd "$NIP_RELEASES_PATH"
ls -1dt */ | tail -n +6 | xargs -r rm -rf
@else
#
# Simple Deployment (no zero-downtime)
#

cd "$NIP_SITE_PATH"

echo "Pulling latest changes..."
git pull origin $NIP_SITE_BRANCH

#
# Executing deployment script
#

{!! $deployScriptContent !!}
@endif

#
# Set Permissions
#

echo "Setting permissions..."
chown -R {{ $user }}:{{ $user }} "{{ $fullPath }}"

@if($site->type->supportsZeroDowntime())
# Ensure storage and cache directories are writable
chmod -R 775 "{{ $fullPath }}/storage"
chmod -R 775 "$NIP_NEW_RELEASE_PATH/bootstrap/cache" 2>/dev/null || true
@endif

echo "Deployment completed successfully for {{ $domain }}!"
