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
# Setup SSH Key for Repository Access
#

@if($deployKey)
echo "Setting up deploy key..."

SSH_DIR="/home/{{ $user }}/.ssh"
mkdir -p "$SSH_DIR"

cat > "$SSH_DIR/deploy_key_{{ $site->id }}" << 'DEPLOYKEYEOF'
{!! $deployKey !!}
DEPLOYKEYEOF

chmod 600 "$SSH_DIR/deploy_key_{{ $site->id }}"
chown {{ $user }}:{{ $user }} "$SSH_DIR/deploy_key_{{ $site->id }}"

# Configure SSH to use the deploy key
cat > "$SSH_DIR/config" << 'SSHCONFIGEOF'
Host github.com
    IdentityFile ~/.ssh/deploy_key_{{ $site->id }}
    StrictHostKeyChecking no
    UserKnownHostsFile /dev/null

Host gitlab.com
    IdentityFile ~/.ssh/deploy_key_{{ $site->id }}
    StrictHostKeyChecking no
    UserKnownHostsFile /dev/null

Host bitbucket.org
    IdentityFile ~/.ssh/deploy_key_{{ $site->id }}
    StrictHostKeyChecking no
    UserKnownHostsFile /dev/null
SSHCONFIGEOF

chmod 600 "$SSH_DIR/config"
chown {{ $user }}:{{ $user }} "$SSH_DIR/config"
@endif

#
# Clone Repository
#

echo "Cloning {{ $repository }} (branch: {{ $branch }})..."
mkdir -p "$RELEASE_DIR"

sudo -u {{ $user }} git clone --branch {{ $branch }} --depth 1 {{ $repository }} "$RELEASE_DIR"

#
# Update Current Symlink
#

ln -sfn "$RELEASE_DIR" "$SITE_PATH/current"

#
# Set Ownership
#

chown -R {{ $user }}:{{ $user }} "$RELEASE_DIR"

echo "Repository cloned successfully!"
@endif
