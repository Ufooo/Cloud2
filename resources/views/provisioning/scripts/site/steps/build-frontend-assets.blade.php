#!/bin/bash
set -e

# Netipar Cloud - Build Frontend Assets
# Site: {{ $site->domain }}
# Step: Building Frontend Assets

@if(!$hasRepository || !$buildCommand)
echo "No build command configured, skipping asset build..."
exit 0
@else
echo "Building frontend assets for {{ $site->domain }}..."

SITE_PATH="{{ $fullPath }}"
CURRENT_PATH="$SITE_PATH/current"

cd "$CURRENT_PATH"

#
# Build Frontend Assets
#

if [ -f "package.json" ]; then
    echo "Installing node dependencies..."

    @if($packageManager->value === 'npm')
    npm ci || npm install
    @elseif($packageManager->value === 'yarn')
    yarn install --frozen-lockfile || yarn install
    @elseif($packageManager->value === 'pnpm')
    pnpm install --frozen-lockfile || pnpm install
    @elseif($packageManager->value === 'bun')
    bun install --frozen-lockfile || bun install
    @endif

    echo "Running build command: {{ $buildCommand }}"

    @if($packageManager->value === 'npm')
    {{ $buildCommand }}
    @elseif($packageManager->value === 'yarn')
    {{ str_replace('npm run', 'yarn', $buildCommand) }}
    @elseif($packageManager->value === 'pnpm')
    {{ str_replace('npm run', 'pnpm', $buildCommand) }}
    @elseif($packageManager->value === 'bun')
    {{ str_replace('npm run', 'bun run', $buildCommand) }}
    @endif

    echo "Frontend assets built successfully!"
else
    echo "No package.json found, skipping build..."
fi
@endif
