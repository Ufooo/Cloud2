#!/bin/bash
set -e

# Netipar Cloud - Run Migrations
# Site: {{ $site->domain }}
# Step: Running Database Migrations

@if(!$hasRepository)
echo "No repository configured, skipping migrations..."
exit 0
@else
echo "Running database migrations for {{ $site->domain }}..."

SITE_PATH="{{ $fullPath }}"
CURRENT_PATH="$SITE_PATH/current"

cd "$CURRENT_PATH"

#
# Run Laravel Migrations
#

@if($siteType->value === 'laravel' || $siteType->value === 'statamic')
if [ -f "artisan" ]; then
    echo "Running Laravel migrations..."

    php artisan migrate --force --no-interaction

    echo "Migrations completed successfully!"
else
    echo "No artisan file found, skipping migrations..."
fi
@else
echo "Site type {{ $siteType->value }} does not require migrations, skipping..."
@endif
@endif
