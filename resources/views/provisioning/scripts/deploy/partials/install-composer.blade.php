#
# Install Composer Dependencies
#

echo "Installing Composer dependencies..."
$NIP_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Generate APP_KEY if missing or empty (must be after composer install)
if [ -f "$NIP_SITE_ROOT/.env" ]; then
    if grep -qE "^APP_KEY=$|^APP_KEY=\"\"$|^APP_KEY=''$" "$NIP_SITE_ROOT/.env"; then
        echo "Generating APP_KEY..."
        $NIP_PHP artisan key:generate --force
    fi
fi
