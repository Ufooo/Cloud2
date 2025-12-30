#
# Create hooks directory and script
#

mkdir -p "$LE_DIR/hooks"

cat > "$LE_DIR/hooks/netipar.sh" << 'HOOKEOF'
#!/usr/bin/env bash

SITE_CONF_DIR="{{ $siteConfDir }}"

HOOK_NGINX_CONF="$(cat << 'NGINXHOOK'
location ^~ /.well-known/acme-challenge {
    auth_basic off;
    allow all;
    alias {{ $wellknown }};
    try_files $uri =404;
    default_type "text/plain";
}
NGINXHOOK
)"

deploy_challenge() {
    local FQDN="$1"

    echo "Deploying HTTP-01 challenge for domain: $FQDN"

    mkdir -p "$SITE_CONF_DIR/server"
    printf '%s\n' "$HOOK_NGINX_CONF" > "$SITE_CONF_DIR/server/letsencrypt.conf"

    nginx -t && service nginx reload
    sleep 5
}

clean_challenge() {
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

    chmod 600 "$CERT_PATH/private.key"
    chmod 644 "$CERT_PATH/certificate.crt"
    chmod 644 "$CERT_PATH/fullchain.crt"

    echo "Certificate deployed to $CERT_PATH"
}

HANDLER="$1"; shift
if [[ "${HANDLER}" =~ ^(deploy_challenge|clean_challenge|deploy_cert)$ ]]; then
    "$HANDLER" "$@"
fi
HOOKEOF

chmod +x "$LE_DIR/hooks/netipar.sh"
