#!/bin/bash
set -e

# Netipar Cloud - Configure Nginx
# Site: {{ $domain }}
# Step: Configuring Nginx

echo "Configuring Nginx for {{ $domain }}..."

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

echo "Creating Nginx configuration..."

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

echo "Nginx configuration completed successfully!"
