#!/bin/bash
set -e

@if($skipClone)
echo "No repository configured, skipping clone step..."
exit 0
@else
echo -e '\e[32m=> Cloning repository for {{ $site->domain }}\e[0m'

SITE_ROOT="{{ $siteRoot }}"
ROOT_DIR="{{ $rootDirectory }}"

@if($site->zero_downtime)
# Zero-downtime deployment: use releases directory
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
@else
# Non zero-downtime: clone directly to site root
@if($rootDirectory !== '/')
PROJECT_DIR="$SITE_ROOT{{ $rootDirectory }}"
@else
PROJECT_DIR="$SITE_ROOT"
@endif

# Save .env if it exists before clearing directory
if [ -f "$SITE_ROOT/.env" ]; then
    cp "$SITE_ROOT/.env" /tmp/.env.backup.$$
fi

# Clear directory for fresh clone (remove default index.html etc)
echo -e '\e[32m=> Preparing directory for clone\e[0m'
rm -rf "$SITE_ROOT"
mkdir -p "$SITE_ROOT"

echo -e '\e[32m=> Downloading code repository\e[0m'
git clone --branch {{ $branch }} --depth 1 '{{ $repository }}' "$SITE_ROOT"

# Restore .env if it was backed up
if [ -f /tmp/.env.backup.$$ ]; then
    mv /tmp/.env.backup.$$ "$SITE_ROOT/.env"
fi

if [ -f "$PROJECT_DIR/composer.json" ]; then
    echo -e '\e[32m=> Setting up Composer auth.json\e[0m'

    if [ ! -f "$SITE_ROOT/auth.json" ]; then
        echo '{ "http-basic": {} }' > "$SITE_ROOT/auth.json"
    fi

    if [ "$PROJECT_DIR" != "$SITE_ROOT" ]; then
        ln -sf "$SITE_ROOT/auth.json" "$PROJECT_DIR/auth.json"
    fi
fi

if [ -d "$PROJECT_DIR/storage" ]; then
    echo -e '\e[32m=> Setting storage permissions\e[0m'
    chmod -R 775 "$PROJECT_DIR/storage"
fi
@endif

echo -e '\e[32m=> Repository cloned successfully!\e[0m'
@endif
