export DEBIAN_FRONTEND=noninteractive

function provisionPing {
  curl --insecure --data "status=$2&server_id=$1&token={{ $server->provisioning_token }}" {{ $callbackUrl }}
}

cat > /etc/apt/apt.conf.d/90lock-timeout << 'EOF'
DPkg::Lock::Timeout "300";
EOF

    if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root."

   exit 1
fi
    if [[ -f /root/.netipar-provisioned ]]; then
  echo "This server has already been provisioned."
  echo "If you need to re-provision, you may remove the /root/.netipar-provisioned file and try again."

  exit 1
fi
    AVAILABLE_VERSIONS=(22.04 24.04)
OS_NOT_SUPPORTED="Only Ubuntu ${AVAILABLE_VERSIONS[@]} is supported."

if [[ ! -f /etc/os-release ]]; then
  echo "$OS_NOT_SUPPORTED"
  exit 1
fi

UNAME=$(awk -F= '/^NAME/{print $2}' /etc/os-release | sed 's/\"//g')
VERSION=$(awk -F= '/^VERSION_ID/{print $2}' /etc/os-release | sed 's/\"//g')

if [[ "$UNAME" != "Ubuntu" || ! " ${AVAILABLE_VERSIONS[@]} " =~ " ${VERSION} " ]]; then
  echo "$OS_NOT_SUPPORTED"
  exit 1
fi

    apt-get install -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" -y --force-yes curl

provisionPing {{ $server->id }} 1
