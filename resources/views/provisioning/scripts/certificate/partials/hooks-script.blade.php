#
# Create hooks directory and script
#

mkdir -p "$LE_DIR/hooks"

cat > "$LE_DIR/hooks/netipar.sh" << 'HOOKEOF'
#!/usr/bin/env bash

SITE_CONF_DIR="{{ $siteConfDir }}"

deploy_challenge() {
    local FQDN="$1"

    echo "Deploying HTTP-01 challenge configuration..."

    # Create a standalone HTTP server block so the challenge is served on port 80
    # regardless of whether the site's nginx config has a port 80 listener
    cat > "/etc/nginx/sites-enabled/acme-challenge-${FQDN}.conf" << NGINXEOF
server {
    listen 80;
    listen [::]:80;
    server_name ${FQDN};

    location ^~ /.well-known/acme-challenge {
        auth_basic off;
        allow all;
        alias {{ $wellknown }};
        try_files \$uri =404;
        default_type "text/plain";
    }

    location / {
        return 404;
    }
}
NGINXEOF

    nginx -t && service nginx reload
    sleep 5
}

clean_challenge() {
    local FQDN="$1"

    echo "Cleaning up ACME challenge configuration..."

    # Remove standalone challenge server block
    if [ -f "/etc/nginx/sites-enabled/acme-challenge-${FQDN}.conf" ]; then
        rm "/etc/nginx/sites-enabled/acme-challenge-${FQDN}.conf"
        echo "  Removed ACME config"
    fi

    # Also clean up legacy server-include style config if present
    if [ -f "$SITE_CONF_DIR/server/letsencrypt.conf" ]; then
        rm "$SITE_CONF_DIR/server/letsencrypt.conf"
    fi

    nginx -t && service nginx reload
}

deploy_cert() {
    local DOMAIN="${1}"
    local KEYFILE="${2}"
    local CERTFILE="${3}"
    local FULLCHAINFILE="${4}"
    local CHAINFILE="${5}"

    CERT_PATH="{{ $certPath }}"

    mkdir -p "$CERT_PATH"

    cp "$KEYFILE" "$CERT_PATH/private.key"
    cp "$CERTFILE" "$CERT_PATH/certificate.crt"
    cp "$FULLCHAINFILE" "$CERT_PATH/fullchain.crt"
    cp "$CHAINFILE" "$CERT_PATH/chain.crt" 2>/dev/null || true

    # Backward compatibility: create .pem copies for Forge-migrated configs
    cp "$KEYFILE" "$CERT_PATH/privkey.pem"
    cp "$FULLCHAINFILE" "$CERT_PATH/fullchain.pem"

    chmod 600 "$CERT_PATH/private.key"
    chmod 600 "$CERT_PATH/privkey.pem"
    chmod 644 "$CERT_PATH/certificate.crt"
    chmod 644 "$CERT_PATH/fullchain.crt"
    chmod 644 "$CERT_PATH/fullchain.pem"

    echo "Certificate deployed to $CERT_PATH"
}

HANDLER="$1"; shift
if [[ "${HANDLER}" =~ ^(deploy_challenge|clean_challenge|deploy_cert)$ ]]; then
    "$HANDLER" "$@"
fi
HOOKEOF

chmod +x "$LE_DIR/hooks/netipar.sh"
