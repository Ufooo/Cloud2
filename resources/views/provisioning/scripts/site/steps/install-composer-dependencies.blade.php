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

SITE_ROOT="{{ $siteRoot }}"
APPLICATION_PATH="{{ $applicationPath }}"

cd "$APPLICATION_PATH"

#
# Install Composer Dependencies
#

if [ -f "composer.json" ]; then
    echo "Installing Composer dependencies..."

    # Link auth.json if exists and paths are different
    if [ -f "$SITE_ROOT/auth.json" ] && [ "$APPLICATION_PATH" != "$SITE_ROOT" ]; then
        ln -sf "$SITE_ROOT/auth.json" "$APPLICATION_PATH/auth.json"
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
