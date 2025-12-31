#!/bin/bash
set -e

# Netipar Cloud - Clone Repository
# Site: {{ $site->domain }}
# Step: Cloning Repository

@if($skipClone)
echo "No repository configured, skipping clone step..."
exit 0
@else
echo "Cloning repository for {{ $site->domain }}..."

SITE_PATH="{{ $fullPath }}"
RELEASE_TIMESTAMP=$(date +%Y%m%d%H%M%S)
RELEASE_DIR="$SITE_PATH/releases/$RELEASE_TIMESTAMP"

#
# Clone Repository (using server's pre-configured git SSH key)
#

echo "Cloning {{ $repository }} (branch: {{ $branch }})..."
mkdir -p "$RELEASE_DIR"

sudo -u {{ $user }} git clone --branch {{ $branch }} --depth 1 {{ $repository }} "$RELEASE_DIR"

#
# Update Current Symlink (atomic swap)
#

ln -s "$RELEASE_DIR" "$SITE_PATH/current-temp" && mv -Tf "$SITE_PATH/current-temp" "$SITE_PATH/current"

#
# Set Ownership
#

chown -R {{ $user }}:{{ $user }} "$RELEASE_DIR"

echo "Repository cloned successfully!"
@endif
