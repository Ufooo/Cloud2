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
# Create FastCGI Defaults (only once per server)
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
# Create Site Nginx Configuration Directory
#

NGINX_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->id }}"
DOMAIN_CONF_DIR="$NGINX_CONF_DIR/{{ $domain }}"

echo "Creating site configuration directories..."
mkdir -p "$NGINX_CONF_DIR/before"
mkdir -p "$NGINX_CONF_DIR/after"
mkdir -p "$NGINX_CONF_DIR/server"

echo "Creating primary domain configuration directories..."
mkdir -p "$DOMAIN_CONF_DIR/before"
mkdir -p "$DOMAIN_CONF_DIR/after"

#
# Create site.conf with SSL settings and common configuration
#

echo "Creating site.conf with SSL settings and common configuration..."
cat > "$NGINX_CONF_DIR/site.conf" << 'EOF'
# Netipar Cloud - Common Site Configuration
# This file contains settings shared across all domains for this site

# SSL Configuration
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;
ssl_dhparam /etc/nginx/dhparams.pem;

ssl_session_timeout 1d;
ssl_session_cache shared:SSL:10m;
ssl_session_tickets off;

ssl_stapling on;
ssl_stapling_verify on;
resolver 1.1.1.1 8.8.8.8 valid=300s;
resolver_timeout 5s;

# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Default index files
index index.html index.htm index.php;

# Character encoding
charset utf-8;

# NETIPAR CONFIG (DO NOT REMOVE!)
include netipar-conf/{{ $site->id }}/server/*;

# Common locations
location = /favicon.ico { access_log off; log_not_found off; }
location = /favicon.svg { access_log off; log_not_found off; }
location = /robots.txt  { access_log off; log_not_found off; }

# Deny access to hidden files (except .well-known)
location ~ /\.(?!well-known).* {
    deny all;
}
EOF

echo "Site configuration directory created at $NGINX_CONF_DIR"
