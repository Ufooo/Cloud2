#!/bin/bash
set -e

@if($skipClone)
echo "No repository configured, skipping clone step..."
exit 0
@else
echo -e '\e[32m=> Cloning repository for {{ $site->domain }}\e[0m'

SITE_ROOT="{{ $siteRoot }}"
ROOT_DIR="{{ $rootDirectory }}"
RELEASE_TIMESTAMP=$(date +%Y%m%d%H%M%S)
CLONE_DIR="$SITE_ROOT/releases/$RELEASE_TIMESTAMP"

# Project directory (where the actual code lives, including root_directory)
@if($rootDirectory !== '/')
PROJECT_DIR="$CLONE_DIR{{ $rootDirectory }}"
@else
PROJECT_DIR="$CLONE_DIR"
@endif

echo -e '\e[32m=> Downloading code repository\e[0m'
git clone --branch {{ $branch }} --depth 1 '{{ $repository }}' "$CLONE_DIR"

if [ -f "$PROJECT_DIR/composer.json" ]; then
    echo -e '\e[32m=> Linking Composer auth.json\e[0m'

    if [ -f "$PROJECT_DIR/auth.json" ]; then
        mv "$PROJECT_DIR/auth.json" "$SITE_ROOT/auth.json"
    elif [ ! -f "$SITE_ROOT/auth.json" ]; then
        echo '{ "http-basic": {} }' > "$SITE_ROOT/auth.json"
    fi

    ln -sf "$SITE_ROOT/auth.json" "$PROJECT_DIR/auth.json"
fi

if [ -e "$PROJECT_DIR/storage" ]; then
    echo -e '\e[32m=> Moving storage to shared location\e[0m'
    rm -rf "$SITE_ROOT/storage"
    mv "$PROJECT_DIR/storage" "$SITE_ROOT/storage"
fi

if [ ! -e "$SITE_ROOT/storage" ]; then
    echo -e '\e[32m=> Creating storage directory\e[0m'
    mkdir -p "$SITE_ROOT/storage"
    mkdir -p "$SITE_ROOT/storage/app/public"
    mkdir -p "$SITE_ROOT/storage/framework/cache"
    mkdir -p "$SITE_ROOT/storage/framework/sessions"
    mkdir -p "$SITE_ROOT/storage/framework/views"
    mkdir -p "$SITE_ROOT/storage/logs"
fi

echo -e '\e[32m=> Linking storage directory\e[0m'
rm -rf "$PROJECT_DIR/storage"
ln -sfn "$SITE_ROOT/storage" "$PROJECT_DIR/storage"

echo -e '\e[32m=> Linking environment file\e[0m'
rm -f "$PROJECT_DIR/.env"
ln -sfn "$SITE_ROOT/.env" "$PROJECT_DIR/.env"

echo -e '\e[32m=> Activating release\e[0m'
# Symlink current points to the project directory (including root_directory)
ln -s "$PROJECT_DIR" "$SITE_ROOT/current-temp" && mv -Tf "$SITE_ROOT/current-temp" "$SITE_ROOT/current"

chmod -R 775 "$SITE_ROOT/storage"

echo -e '\e[32m=> Repository cloned successfully!\e[0m'
@endif
