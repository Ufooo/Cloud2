#
# Variables
#

TIMESTAMP=$(date +%s)
LE_DIR="/root/letsencrypt${TIMESTAMP}"
CERT_PATH="{{ $certPath }}"
SITE_CONF_DIR="{{ $siteConfDir }}"
WELLKNOWN="{{ $wellknown }}"

#
# Create directories
#

echo "Creating directories..."
mkdir -p "$WELLKNOWN"
mkdir -p "$CERT_PATH"
chown -R {{ $siteUser }}:{{ $siteUser }} "$WELLKNOWN"

#
# Clone dehydrated ACME client
#

echo "Installing ACME client..."
git clone --depth 1 https://github.com/dehydrated-io/dehydrated.git "$LE_DIR" 2>/dev/null || {
    echo "Failed to clone dehydrated, trying alternative..."
    mkdir -p "$LE_DIR"
    curl -sL https://raw.githubusercontent.com/dehydrated-io/dehydrated/master/dehydrated -o "$LE_DIR/dehydrated"
    chmod +x "$LE_DIR/dehydrated"
}

#
# Create configuration
#

echo "Configuring ACME client..."

cat > "$LE_DIR/config" << 'CONFIGEOF'
CA="https://acme-v02.api.letsencrypt.org/directory"
WELLKNOWN="{{ $wellknown }}"
ACCOUNTDIR="/root/letsencrypt_accounts"
@if($keyAlgorithm === 'ecdsa')
KEY_ALGO="secp384r1"
@else
KEY_ALGO="rsa"
KEYSIZE="4096"
@endif
CONFIGEOF

#
# Register account with Let's Encrypt (if not already registered)
#

if [ ! -d ~/letsencrypt_accounts ]; then
    echo "Registering account with Let's Encrypt..."
    cd "$LE_DIR"
    ./dehydrated --register --accept-terms
fi

#
# Create domains.txt
#

echo "{{ implode(' ', $domains) }} > domain-{{ $site->id }}-{{ $certificate->id }}" > "$LE_DIR/domains.txt"
