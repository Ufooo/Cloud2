#!/bin/bash
set -e

# Netipar Cloud - Configure WWW Redirect
# Site: {{ $site->domain }}

echo "Configuring www redirect..."

SITE_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->domain }}"
DOMAIN_CONF_DIR="$SITE_CONF_DIR/{{ $domain }}"

@if($wwwRedirectConfig)
mkdir -p "$DOMAIN_CONF_DIR/before"
cat > "$DOMAIN_CONF_DIR/before/redirect.conf" << 'REDIRECTEOF'
{!! $wwwRedirectConfig !!}
REDIRECTEOF

echo "WWW redirect configuration created"
@else
echo "No www redirect configured, skipping"
@endif
