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

echo "Creating/Updating FastCGI defaults..."
cat > /etc/nginx/netipar_fastcgi_defaults << 'EOF'
# Netipar Cloud - FastCGI Defaults
# Common FastCGI parameters for PHP applications

# Script path parameters
fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
fastcgi_param PATH_INFO $fastcgi_path_info;

# Buffer settings for optimal performance
fastcgi_buffers 16 16k;
fastcgi_buffer_size 32k;

# Timeouts
fastcgi_connect_timeout 60;
fastcgi_send_timeout 180;
fastcgi_read_timeout 180;

# Intercept errors for custom error pages
fastcgi_intercept_errors off;
EOF

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

# Security Headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), geolocation=(), magnetometer=(), microphone=(), payment=(), usb=()" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

# CORS - Allow cross-origin requests between site domains
include netipar-conf/{{ $site->id }}/cors.conf;

# Default index files
index index.html index.htm index.php;

# Character encoding
charset utf-8;

# NETIPAR CONFIG (DO NOT REMOVE!)
include netipar-conf/{{ $site->id }}/server/*;
EOF

SITE_CONF_DIR="$NGINX_CONF_DIR"
@include('provisioning.scripts.site.partials.cors-config')

echo "Site configuration directory created at $NGINX_CONF_DIR"
