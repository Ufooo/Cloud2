#!/bin/bash
set -e

# Netipar Cloud - Sync Redirect Rules
# Site: {{ $site->domain }}

echo "Syncing redirect rules for {{ $site->domain }}..."

SITE_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->domain }}"
REDIRECT_CONF="$SITE_CONF_DIR/server/redirects.conf"

mkdir -p "$SITE_CONF_DIR/server"

@if($rules->isEmpty())
# No redirect rules - remove config if exists
if [ -f "$REDIRECT_CONF" ]; then
    echo "Removing redirect configuration (no rules)..."
    rm -f "$REDIRECT_CONF"
fi
@else
echo "Writing {{ $rules->count() }} redirect rule(s)..."

cat > "$REDIRECT_CONF" << 'REDIRECTEOF'
# Netipar Cloud - Redirect Rules for {{ $site->domain }}
# Auto-generated - do not edit manually

@foreach($rules as $rule)
# Redirect: {{ $rule->from }} -> {{ $rule->to }} ({{ $rule->type->value }})
location = {{ $rule->from }} {
    return {{ $rule->type->httpCode() }} {{ $rule->to }};
}

@endforeach
REDIRECTEOF
@endif

echo "Testing Nginx configuration..."
if ! nginx -t; then
    echo "ERROR: Nginx configuration test failed, removing redirect config..."
    rm -f "$REDIRECT_CONF"
    nginx -t
    echo "Redirect config removed to restore working state"
    exit 1
fi

echo "Reloading Nginx..."
service nginx reload

echo "Redirect rules synced successfully!"
