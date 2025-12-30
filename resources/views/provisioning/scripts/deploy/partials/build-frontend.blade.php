#
# Build Frontend Assets
#

if [ -f "$NIP_NEW_RELEASE_PATH/package.json" ]; then
    echo "Building frontend assets..."
    npm ci || npm install
    npm run build
fi
