#!/bin/bash
set -e

echo -e '\e[32m=> Finalizing installation for {{ $domain }}\e[0m'

SITE_ROOT="{{ $siteRoot }}"
APPLICATION_PATH="{{ $applicationPath }}"

echo -e '\e[32m=> Setting permissions\e[0m'

chmod -R 755 "$SITE_ROOT"

if [ -d "$SITE_ROOT/storage" ]; then
    chmod -R 775 "$SITE_ROOT/storage"
fi

if [ -d "$APPLICATION_PATH/bootstrap/cache" ]; then
    chmod -R 775 "$APPLICATION_PATH/bootstrap/cache"
fi

cd "$APPLICATION_PATH"

if [ -f "artisan" ]; then
    echo -e '\e[32m=> Clearing application caches\e[0m'
    php artisan config:clear || true
    php artisan cache:clear || true
    php artisan view:clear || true
    php artisan route:clear || true

    echo -e '\e[32m=> Optimizing application\e[0m'
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

if [ -d "$SITE_ROOT/releases" ]; then
    echo -e '\e[32m=> Purging old releases\e[0m'
    cd "$SITE_ROOT/releases"
    CURRENT_RELEASE=$(readlink -f "$SITE_ROOT/current" | xargs basename)
    ls -t | tail -n +6 | grep -v "^${CURRENT_RELEASE}$" | xargs -r rm -rf
fi

echo -e '\e[32m=> Site installation completed successfully!\e[0m'
echo -e "Your site is now available at: \e[1mhttps://{{ $domain }}\e[0m"
