#!/bin/bash
set -e

# Netipar Cloud - Install Site
# Site: {{ $domain }}
# User: {{ $user }}
# Type: {{ $siteType->value }}

echo "Installing site {{ $domain }}..."

#
# Create Directory Structure
#

SITE_PATH="{{ $fullPath }}"
WEB_PATH="{{ $webPath }}"

if [ -d "$SITE_PATH" ]; then
    echo "Site directory already exists at $SITE_PATH"
else
    echo "Creating site directory at $SITE_PATH..."
    mkdir -p "$SITE_PATH"

    # Create standard directories
    mkdir -p "$SITE_PATH/releases"
    mkdir -p "$SITE_PATH/storage"
    mkdir -p "$SITE_PATH/storage/app/public"
    mkdir -p "$SITE_PATH/storage/framework/cache"
    mkdir -p "$SITE_PATH/storage/framework/sessions"
    mkdir -p "$SITE_PATH/storage/framework/views"
    mkdir -p "$SITE_PATH/storage/logs"

    # Create initial release directory
    RELEASE_DIR="$SITE_PATH/releases/initial"
    mkdir -p "$RELEASE_DIR"

    # Create public directory
    mkdir -p "$RELEASE_DIR{{ $webDirectory }}"

    # Create default index file
{!! $defaultIndexScript !!}

    # Create symlink to current release
    ln -sfn "$RELEASE_DIR" "$SITE_PATH/current"

    # Set proper ownership
    chown -R {{ $user }}:{{ $user }} "$SITE_PATH"
    chmod -R 755 "$SITE_PATH"
    chmod -R 775 "$SITE_PATH/storage"

    echo "Site directory structure created successfully"
fi

#
# Create Nginx Configuration Directory
#

NGINX_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->id }}"
mkdir -p "$NGINX_CONF_DIR"
mkdir -p "$NGINX_CONF_DIR/before"
mkdir -p "$NGINX_CONF_DIR/server"
mkdir -p "$NGINX_CONF_DIR/after"

#
# Create Nginx Site Configuration
#

echo "Creating Nginx configuration for {{ $domain }}..."

cat > /etc/nginx/sites-available/{{ $domain }} << 'NGINXEOF'
{!! $nginxConfig !!}
NGINXEOF

#
# Enable Site
#

if [ ! -L "/etc/nginx/sites-enabled/{{ $domain }}" ]; then
    ln -s /etc/nginx/sites-available/{{ $domain }} /etc/nginx/sites-enabled/{{ $domain }}
    echo "Site enabled in Nginx"
fi

#
# Test and Reload Nginx
#

echo "Testing Nginx configuration..."
nginx -t

echo "Reloading Nginx..."
service nginx reload

{!! $isolatedFpmScript !!}

echo "Site {{ $domain }} installed successfully!"
