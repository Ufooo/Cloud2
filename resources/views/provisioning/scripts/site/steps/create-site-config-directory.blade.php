#!/bin/bash
set -e

# Netipar Cloud - Create Site Configuration Directory
# Site: {{ $domain }}

echo "Creating site configuration directory..."

#
# Ensure DH Parameters Exist
#

if [ ! -f /etc/nginx/dhparams.pem ]; then
    echo "Generating dhparams.pem file..."
    openssl dhparam -out /etc/nginx/dhparams.pem 2048
fi

#
# Create FastCGI Defaults
#

if [ ! -f /etc/nginx/netipar_fastcgi_defaults ]; then
    echo "Creating FastCGI defaults..."
    cat > /etc/nginx/netipar_fastcgi_defaults << 'EOF'
# Netipar FastCGI Defaults
fastcgi_buffers 32 32k;
fastcgi_buffer_size 64k;
EOF
fi

#
# Create Nginx Configuration Directory
#

NGINX_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->id }}"
mkdir -p "$NGINX_CONF_DIR"
mkdir -p "$NGINX_CONF_DIR/before"
mkdir -p "$NGINX_CONF_DIR/server"
mkdir -p "$NGINX_CONF_DIR/after"

echo "Site configuration directory created at $NGINX_CONF_DIR"
