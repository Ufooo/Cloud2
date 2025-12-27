#
# Parse certificate expiry
#

CERT_FILE="$CERT_PATH/certificate.crt"
if [ -f "$CERT_FILE" ]; then
    EXPIRY_DATE=$(openssl x509 -enddate -noout -in "$CERT_FILE" | cut -d= -f2)
    EXPIRY_FORMATTED=$(date -d "$EXPIRY_DATE" +%Y-%m-%d 2>/dev/null || echo "")
    if [ -n "$EXPIRY_FORMATTED" ]; then
        echo "CERT_EXPIRES:$EXPIRY_FORMATTED"
    fi
fi
