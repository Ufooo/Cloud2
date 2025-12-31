#!/bin/bash
set -e

# Netipar Cloud - Create Nginx Server Block
# Site: {{ $domain }}

echo "Creating Nginx server block for {{ $domain }}..."

#
# Create Nginx Site Configuration
#

cat > /etc/nginx/sites-available/{{ $domain }} << 'NGINXEOF'
{!! $nginxConfig !!}
NGINXEOF

echo "Nginx server block created for {{ $domain }}"
