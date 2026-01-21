#
# Create hooks directory and script for DNS-01 challenge
#

mkdir -p "$LE_DIR/hooks"

cat > "$LE_DIR/hooks/netipar-dns.sh" << 'HOOKEOF'
#!/usr/bin/env bash

# Cloudflare configuration for DNS-01 challenge
CF_API_TOKEN="{{ config('services.cloudflare.api_token') }}"
CF_ZONE_ID="{{ config('services.cloudflare.zone_id') }}"
ACME_DNS_DOMAIN="{{ config('services.cloudflare.acme_dns_domain') }}"
CERT_PATH="{{ $certPath }}"

# ACME subdomains mapping (domain => subdomain)
declare -A ACME_SUBDOMAINS
@foreach($certificate->acme_subdomains ?? [] as $domain => $subdomain)
ACME_SUBDOMAINS["{{ $domain }}"]="{{ $subdomain }}"
@endforeach

# DNS API base URL
CF_API_URL="https://api.cloudflare.com/client/v4"

# Get ACME subdomain for a domain (handles wildcards)
get_acme_subdomain() {
    local DOMAIN="$1"

    # Try exact match first
    if [ -n "${ACME_SUBDOMAINS[$DOMAIN]}" ]; then
        echo "${ACME_SUBDOMAINS[$DOMAIN]}"
        return 0
    fi

    # For wildcards, try the base domain (*.example.com -> example.com)
    if [[ "$DOMAIN" == \*.* ]]; then
        local BASE_DOMAIN="${DOMAIN#\*.}"
        if [ -n "${ACME_SUBDOMAINS[$BASE_DOMAIN]}" ]; then
            echo "${ACME_SUBDOMAINS[$BASE_DOMAIN]}"
            return 0
        fi
    fi

    # Fallback: use first available subdomain
    for key in "${!ACME_SUBDOMAINS[@]}"; do
        echo "${ACME_SUBDOMAINS[$key]}"
        return 0
    done

    echo ""
}

deploy_challenge() {
    local DOMAIN="${1}" TOKEN_FILENAME="${2}" TOKEN_VALUE="${3}"

    local ACME_SUBDOMAIN=$(get_acme_subdomain "$DOMAIN")

    if [ -z "$ACME_SUBDOMAIN" ]; then
        echo "ERROR: No ACME subdomain found for domain: $DOMAIN"
        exit 1
    fi

    echo "Deploying DNS-01 challenge for domain: $DOMAIN"
    echo "Creating TXT record at ${ACME_SUBDOMAIN}.${ACME_DNS_DOMAIN}"

    # Create TXT record via Cloudflare API
    RESPONSE=$(curl -s -X POST "${CF_API_URL}/zones/${CF_ZONE_ID}/dns_records" \
        -H "Authorization: Bearer ${CF_API_TOKEN}" \
        -H "Content-Type: application/json" \
        --data "{
            \"type\": \"TXT\",
            \"name\": \"${ACME_SUBDOMAIN}.${ACME_DNS_DOMAIN}\",
            \"content\": \"${TOKEN_VALUE}\",
            \"ttl\": 60
        }")

    # Check if successful
    SUCCESS=$(echo "$RESPONSE" | grep -o '"success":true')
    if [ -z "$SUCCESS" ]; then
        echo "Failed to create DNS record: $RESPONSE"
        exit 1
    fi

    # Store the record ID for cleanup (use domain-specific file)
    RECORD_ID=$(echo "$RESPONSE" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
    echo "$RECORD_ID" > "/tmp/cf_record_${ACME_SUBDOMAIN}.txt"

    echo "TXT record created successfully (ID: $RECORD_ID)"
    echo "Waiting for DNS propagation..."

    # Wait for DNS propagation (up to 2 minutes)
    for i in {1..24}; do
        sleep 5
        RESOLVED=$(dig +short TXT "${ACME_SUBDOMAIN}.${ACME_DNS_DOMAIN}" @1.1.1.1 2>/dev/null | tr -d '"')
        if [ "$RESOLVED" = "$TOKEN_VALUE" ]; then
            echo "DNS record propagated successfully!"
            return 0
        fi
        echo "Waiting for propagation... ($i/24)"
    done

    echo "Warning: DNS propagation timeout, but continuing anyway..."
}

clean_challenge() {
    local DOMAIN="${1}" TOKEN_FILENAME="${2}" TOKEN_VALUE="${3}"

    local ACME_SUBDOMAIN=$(get_acme_subdomain "$DOMAIN")

    if [ -z "$ACME_SUBDOMAIN" ]; then
        echo "Warning: No ACME subdomain found for cleanup: $DOMAIN"
        return 0
    fi

    echo "Cleaning up DNS-01 challenge for domain: $DOMAIN"

    # Get the stored record ID
    RECORD_ID_FILE="/tmp/cf_record_${ACME_SUBDOMAIN}.txt"
    if [ -f "$RECORD_ID_FILE" ]; then
        RECORD_ID=$(cat "$RECORD_ID_FILE")

        # Delete TXT record via Cloudflare API
        curl -s -X DELETE "${CF_API_URL}/zones/${CF_ZONE_ID}/dns_records/${RECORD_ID}" \
            -H "Authorization: Bearer ${CF_API_TOKEN}" \
            -H "Content-Type: application/json" > /dev/null

        rm -f "$RECORD_ID_FILE"
        echo "TXT record cleaned up successfully"
    else
        # Fallback: Find and delete by name
        echo "Record ID not found, searching by name..."
        RECORDS=$(curl -s -X GET "${CF_API_URL}/zones/${CF_ZONE_ID}/dns_records?type=TXT&name=${ACME_SUBDOMAIN}.${ACME_DNS_DOMAIN}" \
            -H "Authorization: Bearer ${CF_API_TOKEN}" \
            -H "Content-Type: application/json")

        RECORD_ID=$(echo "$RECORDS" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
        if [ -n "$RECORD_ID" ]; then
            curl -s -X DELETE "${CF_API_URL}/zones/${CF_ZONE_ID}/dns_records/${RECORD_ID}" \
                -H "Authorization: Bearer ${CF_API_TOKEN}" \
                -H "Content-Type: application/json" > /dev/null
            echo "TXT record found and cleaned up"
        fi
    fi
}

deploy_cert() {
    local DOMAIN="${1}"
    local KEYFILE="${2}"
    local CERTFILE="${3}"
    local FULLCHAINFILE="${4}"
    local CHAINFILE="${5}"

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

chmod +x "$LE_DIR/hooks/netipar-dns.sh"
