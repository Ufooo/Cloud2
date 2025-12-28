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

    # Link auth.json if exists
    if [ -f "$SITE_PATH/auth.json" ]; then
        ln -sf "$SITE_PATH/auth.json" "$CURRENT_PATH/auth.json"
    fi

    php{{ $composerPhpVersion }} {{ $composerBinary }} install \
        --no-interaction \
        --no-dev \
        --prefer-dist \
        --optimize-autoloader

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
    npm install --production
    @elseif($packageManager->value === 'yarn')
    yarn install --production
    @elseif($packageManager->value === 'pnpm')
    pnpm install --prod
    @elseif($packageManager->value === 'bun')
    bun install --production
    @endif

    echo "NPM dependencies installed successfully!"
else
    echo "No package.json found, skipping NPM installation..."
fi

echo "All dependencies installed successfully!"
@endif
