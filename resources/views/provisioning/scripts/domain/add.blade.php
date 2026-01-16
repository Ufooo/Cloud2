#!/bin/bash
set -e

# Netipar Cloud - Add Domain
# Domain: {{ $domain }}
# Site: {{ $site->domain }}
# Type: {{ $domainRecord->type->value }}

echo "Adding domain {{ $domain }} to site {{ $site->domain }}..."

#
# Variables
#

SITE_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->id }}"
DOMAIN_CONF_DIR="$SITE_CONF_DIR/{{ $domain }}"

@include('provisioning.scripts.partials.ensure-fastcgi-defaults')

#
# Create Domain Configuration Directory
#

echo "Creating domain configuration directory..."
mkdir -p "$DOMAIN_CONF_DIR/before"
mkdir -p "$DOMAIN_CONF_DIR/after"

#
# Create WWW Redirect Configuration
#

@if($wwwRedirectConfig)
echo "Creating WWW redirect configuration..."
cat > "$DOMAIN_CONF_DIR/before/redirect.conf" << 'REDIRECTEOF'
{!! $wwwRedirectConfig !!}
REDIRECTEOF
@endif

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

echo "Enabling domain in Nginx..."
if [ ! -L "/etc/nginx/sites-enabled/{{ $domain }}" ]; then
    ln -s /etc/nginx/sites-available/{{ $domain }} /etc/nginx/sites-enabled/{{ $domain }}
fi

@include('provisioning.scripts.site.partials.cors-config')

#
# Test and Reload Nginx
#

echo "Testing Nginx configuration..."
nginx -t

echo "Reloading Nginx..."
service nginx reload

echo "Domain {{ $domain }} added successfully!"
