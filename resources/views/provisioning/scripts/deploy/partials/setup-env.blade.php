#
# Setup Environment
#

echo "Linking shared .env..."
rm -f "$NIP_NEW_RELEASE_PATH/.env"
ln -sfn "$NIP_SITE_ROOT/.env" "$NIP_NEW_RELEASE_PATH/.env"

# Copy .env.example to shared .env if it doesn't have content
if [ ! -s "$NIP_SITE_ROOT/.env" ] && [ -f "$NIP_NEW_RELEASE_PATH/.env.example" ]; then
    echo "Initializing .env from .env.example..."
    cp "$NIP_NEW_RELEASE_PATH/.env.example" "$NIP_SITE_ROOT/.env"
fi

# Configure database in .env if database credentials are provided
if [ -n "$NIP_DB_DATABASE" ]; then
    echo "Configuring database in .env..."
    sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=$NIP_DB_CONNECTION/" "$NIP_SITE_ROOT/.env"
    sed -i "s/^DB_HOST=.*/DB_HOST=$NIP_DB_HOST/" "$NIP_SITE_ROOT/.env"
    sed -i "s/^DB_PORT=.*/DB_PORT=$NIP_DB_PORT/" "$NIP_SITE_ROOT/.env"
    sed -i "s/^DB_DATABASE=.*/DB_DATABASE=$NIP_DB_DATABASE/" "$NIP_SITE_ROOT/.env"
    sed -i "s/^DB_USERNAME=.*/DB_USERNAME=$NIP_DB_USERNAME/" "$NIP_SITE_ROOT/.env"
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$NIP_DB_PASSWORD/" "$NIP_SITE_ROOT/.env"
fi

# Link shared storage directory
echo "Linking shared storage..."
rm -rf "$NIP_NEW_RELEASE_PATH/storage"
ln -sfn "$NIP_SITE_ROOT/storage" "$NIP_NEW_RELEASE_PATH/storage"
