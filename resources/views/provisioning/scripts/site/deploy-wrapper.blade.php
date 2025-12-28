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

export NIP_SITE_PATH="{{ $currentPath }}"
export NIP_SITE_BRANCH="{{ $branch }}"
export NIP_PHP="/usr/bin/php{{ $phpVersion }}"
export NIP_COMPOSER="composer"

#
# Run Site Type Specific Deploy Script
#

{{ $deployScriptContent }}

#
# Set Permissions
#

echo "Setting permissions..."
chown -R {{ $user }}:{{ $user }} "{{ $fullPath }}"

echo "Deployment completed successfully for {{ $domain }}!"
