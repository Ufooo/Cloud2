# Update subdomain redirect exclusions for {{ $domain }}
# This adds the domain to the exclusion list if redirect-subdomains.conf exists

SITE_CONF_DIR="/etc/nginx/netipar-conf/{{ $site->domain }}"
REDIRECT_CONF="$SITE_CONF_DIR/server/redirect-subdomains.conf"

if [ -f "$REDIRECT_CONF" ]; then
    echo "Found redirect-subdomains.conf, checking for exclusion..."

    # Check if this domain is already excluded
    if grep -q 'if (\$host = {{ $domain }})' "$REDIRECT_CONF"; then
        echo "Domain {{ $domain }} is already excluded from subdomain redirects"
    else
        echo "Adding {{ $domain }} to subdomain redirect exclusions..."

        # Create a temporary file with the new exclusion
        # Insert the exclusion before the final redirect check
        awk '
        /if \(\$redirect_to_main = 1\)/ {
            print "if ($host = {{ $domain }}) {"
            print "    set $redirect_to_main 0;"
            print "}"
        }
        { print }
        ' "$REDIRECT_CONF" > "$REDIRECT_CONF.tmp"

        mv "$REDIRECT_CONF.tmp" "$REDIRECT_CONF"

        echo "Domain {{ $domain }} added to exclusion list"

        # Test nginx configuration
        echo "Testing Nginx configuration..."
        if ! nginx -t; then
            echo "ERROR: Nginx configuration test failed after adding exclusion"
            exit 1
        fi

        echo "Reloading Nginx..."
        service nginx reload

        echo "Subdomain redirect exclusion updated successfully"
    fi
else
    echo "No redirect-subdomains.conf found, skipping exclusion update"
fi
