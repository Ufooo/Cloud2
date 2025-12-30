#
# Clone Repository
#

echo "Creating new release: $NIP_RELEASE_NAME"

echo "Cloning repository..."
git clone --branch $NIP_SITE_BRANCH --depth 1 "$NIP_SITE_REPOSITORY" "$NIP_NEW_RELEASE_PATH"
cd "$NIP_NEW_RELEASE_PATH"
