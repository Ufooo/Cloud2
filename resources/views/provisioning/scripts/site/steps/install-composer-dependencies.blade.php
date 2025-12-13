#!/bin/bash
set -e

# Netipar Cloud - Install Composer Dependencies
# Site: {{ $site->domain }}
# Step: Installing Dependencies

@if(!$hasRepository)
echo "No repository configured, skipping dependency installation..."
exit 0
@else
echo "Installing dependencies for {{ $site->domain }}..."

SITE_PATH="{{ $fullPath }}"
CURRENT_PATH="$SITE_PATH/current"

cd "$CURRENT_PATH"

#
# Install Composer Dependencies
#

if [ -f "composer.json" ]; then
    echo "Installing Composer dependencies..."

    sudo -u {{ $user }} composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

    echo "Composer dependencies installed successfully!"
else
    echo "No composer.json found, skipping Composer installation..."
fi

#
# Install NPM Dependencies
#

if [ -f "package.json" ]; then
    echo "Installing NPM dependencies..."

    @if($packageManager->value === 'npm')
    sudo -u {{ $user }} npm install --production
    @elseif($packageManager->value === 'yarn')
    sudo -u {{ $user }} yarn install --production
    @elseif($packageManager->value === 'pnpm')
    sudo -u {{ $user }} pnpm install --prod
    @elseif($packageManager->value === 'bun')
    sudo -u {{ $user }} bun install --production
    @endif

    echo "NPM dependencies installed successfully!"
else
    echo "No package.json found, skipping NPM installation..."
fi

echo "All dependencies installed successfully!"
@endif
