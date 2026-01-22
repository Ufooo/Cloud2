#!/bin/bash
set -e

SITE_ROOT="{{ $siteRoot }}"
ROOT_DIR="{{ $rootDirectory }}"

if [ -d "$SITE_ROOT" ]; then
    echo -e '\e[33m=> Site directory already exists at $SITE_ROOT\e[0m'
    exit 0
fi

echo -e '\e[32m=> Creating site directory at $SITE_ROOT\e[0m'
mkdir -p "$SITE_ROOT"

@if($siteType->supportsZeroDowntime())
mkdir -p "$SITE_ROOT/releases"

# Initial release clone directory
CLONE_DIR="$SITE_ROOT/releases/000000"
mkdir -p "$CLONE_DIR"

# Project directory (includes root_directory for symlink target)
@if($rootDirectory !== '/')
PROJECT_DIR="$CLONE_DIR{{ $rootDirectory }}"
@else
PROJECT_DIR="$CLONE_DIR"
@endif

mkdir -p "$PROJECT_DIR{{ $webDirectory }}"

{!! $defaultIndexScript !!}

mkdir -p "$SITE_ROOT/storage"
mkdir -p "$SITE_ROOT/storage/app/public"
mkdir -p "$SITE_ROOT/storage/framework/cache"
mkdir -p "$SITE_ROOT/storage/framework/sessions"
mkdir -p "$SITE_ROOT/storage/framework/views"
mkdir -p "$SITE_ROOT/storage/logs"

touch "$SITE_ROOT/.env"

ln -sfn "$SITE_ROOT/.env" "$PROJECT_DIR/.env"
ln -sfn "$SITE_ROOT/storage" "$PROJECT_DIR/storage"

# Symlink current to the project directory (including root_directory)
ln -s "$PROJECT_DIR" "$SITE_ROOT/current-temp" && mv -Tf "$SITE_ROOT/current-temp" "$SITE_ROOT/current"

chmod -R 775 "$SITE_ROOT/storage"
chown -R {{ $user }}:{{ $user }} "$SITE_ROOT"
@else
# Non-ZD: project path includes root_directory
@if($rootDirectory !== '/')
PROJECT_DIR="$SITE_ROOT{{ $rootDirectory }}"
@else
PROJECT_DIR="$SITE_ROOT"
@endif

mkdir -p "$PROJECT_DIR{{ $webDirectory }}"

{!! $defaultIndexScript !!}

chown -R {{ $user }}:{{ $user }} "$SITE_ROOT"
@endif

echo -e '\e[32m=> Site directory created successfully\e[0m'
