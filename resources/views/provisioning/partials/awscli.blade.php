    # Install AWSCLI
pip3 install httpie

case "$(dpkg --print-architecture)" in
    amd64) AWSCLI_ARCH="x86_64" ;;
    arm64) AWSCLI_ARCH="aarch64" ;;
    *) echo "Could not install AWS CLI. Unsupported architecture $(dpkg --print-architecture)"; exit 1 ;;
esac

AWSCLI_VERSION="2.22.35"
AWSCLI_INSTALL_TMP_DIR=$(mktemp -d)
cd "$AWSCLI_INSTALL_TMP_DIR" || exit 1

echo "Installing AWS CLI v${AWSCLI_VERSION} for ${AWSCLI_ARCH}..."

curl -fsS "https://awscli.amazonaws.com/awscli-exe-linux-${AWSCLI_ARCH}-${AWSCLI_VERSION}.zip" -o "awscliv2.zip"
unzip -q awscliv2.zip
sudo ./aws/install

cd - || exit 1
rm -rf "$AWSCLI_INSTALL_TMP_DIR"
