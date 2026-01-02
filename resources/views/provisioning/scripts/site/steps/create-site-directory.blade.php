#!/bin/bash
set -e

SITE_PATH="{{ $fullPath }}"

if [ -d "$SITE_PATH" ]; then
    echo -e '\e[33m=> Site directory already exists at $SITE_PATH\e[0m'
    exit 0
fi

echo -e '\e[32m=> Creating site directory at $SITE_PATH\e[0m'
mkdir -p "$SITE_PATH"

@if($siteType->supportsZeroDowntime())
mkdir -p "$SITE_PATH/releases"

TARGET_DIR="$SITE_PATH/releases/000000"
mkdir -p "$TARGET_DIR"
mkdir -p "$TARGET_DIR{{ $webDirectory }}"

{!! $defaultIndexScript !!}

mkdir -p "$SITE_PATH/storage"
mkdir -p "$SITE_PATH/storage/app/public"
mkdir -p "$SITE_PATH/storage/framework/cache"
mkdir -p "$SITE_PATH/storage/framework/sessions"
mkdir -p "$SITE_PATH/storage/framework/views"
mkdir -p "$SITE_PATH/storage/logs"

touch "$SITE_PATH/.env"

ln -sfn "$SITE_PATH/.env" "$TARGET_DIR/.env"
ln -sfn "$SITE_PATH/storage" "$TARGET_DIR/storage"

ln -s "$TARGET_DIR" "$SITE_PATH/current-temp" && mv -Tf "$SITE_PATH/current-temp" "$SITE_PATH/current"

chmod -R 775 "$SITE_PATH/storage"
chown -R {{ $user }}:{{ $user }} "$SITE_PATH"
@else
mkdir -p "$SITE_PATH{{ $webDirectory }}"

{!! $defaultIndexScript !!}

chown -R {{ $user }}:{{ $user }} "$SITE_PATH"
@endif

echo -e '\e[32m=> Site directory created successfully\e[0m'
