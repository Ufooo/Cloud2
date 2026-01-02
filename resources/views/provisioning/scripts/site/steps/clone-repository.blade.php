#!/bin/bash
set -e

@if($skipClone)
echo "No repository configured, skipping clone step..."
exit 0
@else
echo -e '\e[32m=> Cloning repository for {{ $site->domain }}\e[0m'

SITE_PATH="{{ $fullPath }}"
RELEASE_TIMESTAMP=$(date +%Y%m%d%H%M%S)
RELEASE_DIR="$SITE_PATH/releases/$RELEASE_TIMESTAMP"

echo -e '\e[32m=> Downloading code repository\e[0m'
git clone --branch {{ $branch }} --depth 1 '{{ $repository }}' "$RELEASE_DIR"

if [ -f "$RELEASE_DIR/composer.json" ]; then
    echo -e '\e[32m=> Linking Composer auth.json\e[0m'

    if [ -f "$RELEASE_DIR/auth.json" ]; then
        mv "$RELEASE_DIR/auth.json" "$SITE_PATH/auth.json"
    elif [ ! -f "$SITE_PATH/auth.json" ]; then
        echo '{ "http-basic": {} }' > "$SITE_PATH/auth.json"
    fi

    ln -sf "$SITE_PATH/auth.json" "$RELEASE_DIR/auth.json"
fi

if [ -e "$RELEASE_DIR/storage" ]; then
    echo -e '\e[32m=> Moving storage to shared location\e[0m'
    rm -rf "$SITE_PATH/storage"
    mv "$RELEASE_DIR/storage" "$SITE_PATH/storage"
fi

if [ ! -e "$SITE_PATH/storage" ]; then
    echo -e '\e[32m=> Creating storage directory\e[0m'
    mkdir -p "$SITE_PATH/storage"
    mkdir -p "$SITE_PATH/storage/app/public"
    mkdir -p "$SITE_PATH/storage/framework/cache"
    mkdir -p "$SITE_PATH/storage/framework/sessions"
    mkdir -p "$SITE_PATH/storage/framework/views"
    mkdir -p "$SITE_PATH/storage/logs"
fi

echo -e '\e[32m=> Linking storage directory\e[0m'
rm -rf "$RELEASE_DIR/storage"
ln -sfn "$SITE_PATH/storage" "$RELEASE_DIR/storage"

echo -e '\e[32m=> Linking environment file\e[0m'
rm -f "$RELEASE_DIR/.env"
ln -sfn "$SITE_PATH/.env" "$RELEASE_DIR/.env"

echo -e '\e[32m=> Activating release\e[0m'
ln -s "$RELEASE_DIR" "$SITE_PATH/current-temp" && mv -Tf "$SITE_PATH/current-temp" "$SITE_PATH/current"

chmod -R 775 "$SITE_PATH/storage"

echo -e '\e[32m=> Repository cloned successfully!\e[0m'
@endif
