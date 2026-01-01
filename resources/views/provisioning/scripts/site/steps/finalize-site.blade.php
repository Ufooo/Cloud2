#!/bin/bash
set -e

# Netipar Cloud - Finalize Site
# Site: {{ $domain }}
# Step: Finishing Up

echo "Finalizing installation for {{ $domain }}..."

SITE_PATH="{{ $fullPath }}"
CURRENT_PATH="$SITE_PATH/current"

#
# Set Final Permissions
#

echo "Setting final permissions..."

chmod -R 755 "$SITE_PATH"

# Set storage directory permissions if it exists
if [ -d "$SITE_PATH/storage" ]; then
    chmod -R 775 "$SITE_PATH/storage"
fi

# Set bootstrap/cache permissions if it exists (Laravel specific)
if [ -d "$CURRENT_PATH/bootstrap/cache" ]; then
    chmod -R 775 "$CURRENT_PATH/bootstrap/cache"
fi

#
# Clear Caches
#

echo "Clearing application caches..."

cd "$CURRENT_PATH"

# Clear Laravel caches if artisan exists
if [ -f "artisan" ]; then
    php artisan config:clear || true
    php artisan cache:clear || true
    php artisan view:clear || true
    php artisan route:clear || true
fi

#
# Optimize Application
#

echo "Optimizing application..."

# Optimize Laravel if artisan exists
if [ -f "artisan" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

#
# Cleanup
#

echo "Performing cleanup..."

# Remove old releases (keep last 3)
if [ -d "$SITE_PATH/releases" ]; then
    cd "$SITE_PATH/releases"
    ls -t | tail -n +4 | xargs -r rm -rf
fi

echo "Site installation completed successfully!"
echo "Your site is now available at: https://{{ $domain }}"
