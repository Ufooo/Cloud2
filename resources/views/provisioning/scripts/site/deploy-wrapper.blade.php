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
export NIP_SITE_REPOSITORY="{{ $site->repository }}"
export NIP_PHP="/usr/bin/php{{ $phpVersion }}"
export NIP_COMPOSER="composer"

# Database configuration
@if($database && $databaseUser)
export NIP_DB_CONNECTION="mysql"
export NIP_DB_HOST="{{ $database->host ?? '127.0.0.1' }}"
export NIP_DB_PORT="{{ $database->port ?? '3306' }}"
export NIP_DB_DATABASE="{{ $database->name }}"
export NIP_DB_USERNAME="{{ $databaseUser->username }}"
export NIP_DB_PASSWORD="{{ $databaseUser->password }}"
@endif

# Generate release directory name
export NIP_RELEASE_NAME=$(date +%Y%m%d%H%M%S)
export NIP_NEW_RELEASE_PATH="$NIP_RELEASES_PATH/$NIP_RELEASE_NAME"

#
# Run Site Type Specific Deploy Script
#

{!! $deployScriptContent !!}

#
# Set Permissions
#

echo "Setting permissions..."
chown -R {{ $user }}:{{ $user }} "{{ $fullPath }}"

# Ensure storage and cache directories are writable
chmod -R 775 "{{ $fullPath }}/storage"
chmod -R 775 "$NIP_NEW_RELEASE_PATH/bootstrap/cache" 2>/dev/null || true

echo "Deployment completed successfully for {{ $domain }}!"
