#!/bin/bash
set -e

# Netipar Cloud - Create WWW Redirect Configuration
# Site: {{ $domain }}

echo "Creating www redirect configuration..."

NGINX_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->domain }}"

cat > "$NGINX_CONF_DIR/before/redirect.conf" << 'EOF'
server {
    listen 80;
    listen [::]:80;
    server_tokens off;
    server_name www.{{ $domain }};

    if ($http_x_forwarded_proto = 'https') {
        return 301 https://{{ $domain }}$request_uri;
    }

    return 301 $scheme://{{ $domain }}$request_uri;
}
EOF

echo "WWW redirect configuration created"
