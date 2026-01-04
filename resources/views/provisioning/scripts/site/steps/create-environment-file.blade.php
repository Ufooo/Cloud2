#!/bin/bash
set -e

echo -e '\e[32m=> Creating environment file for {{ $site->domain }}\e[0m'

SITE_PATH="{{ $fullPath }}"
CURRENT_PATH="$SITE_PATH/current"
ENV_FILE="$CURRENT_PATH/.env"
SHARED_ENV="$SITE_PATH/.env"

if [ -f "$CURRENT_PATH/.env.example" ]; then
    echo -e '\e[32m=> Copying .env.example to .env\e[0m'
    cp "$CURRENT_PATH/.env.example" "$ENV_FILE"
fi

if [ -f "$CURRENT_PATH/artisan" ] && [ ! -f "$ENV_FILE" ]; then
    echo -e '\e[32m=> Detected Laravel project, generating .env file\e[0m'

    LARAVEL_VERSION=$(cat "$CURRENT_PATH/composer.json" | sed -n -e 's/.*"laravel\/framework": "[^0-9]*\([0-9.]\+\)".*/\1/p1' | cut -d "." -f 1)

    if [ -z "$LARAVEL_VERSION" ]; then
        LARAVEL_VERSION=11
    fi

    if [ "$LARAVEL_VERSION" -gt 10 ]; then
        cat > "$ENV_FILE" << 'ENVEOF'
APP_NAME=Laravel
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=http://localhost

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=sqlite

BROADCAST_CONNECTION=log
CACHE_STORE=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=""
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
ENVEOF
    else
        cat > "$ENV_FILE" << 'ENVEOF'
APP_NAME=Laravel
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

LOG_CHANNEL=stack

DB_CONNECTION=
DB_HOST=127.0.0.1
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=""
REDIS_PORT=6379

MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
ENVEOF
    fi

    echo -e "\e[32m=> Laravel $LARAVEL_VERSION .env template created\e[0m"
fi

if [ -f "$ENV_FILE" ]; then
    echo -e '\e[32m=> Updating environment values\e[0m'

    sed -i -r "s|APP_ENV=.*|APP_ENV=production|" "$ENV_FILE"
    sed -i -r "s|APP_URL=.*|APP_URL=\"https://{{ $site->domain }}\"|" "$ENV_FILE"
    sed -i -r "s|APP_DEBUG=.*|APP_DEBUG=false|" "$ENV_FILE"

@if($database)
    SAFE_DB_PASSWORD=$(printf '%s' '{!! $database->password !!}' | sed 's/[&/\|]/\\&/g')

    sed -i 's/^# DB_HOST=/DB_HOST=/' "$ENV_FILE"
    sed -i 's/^# DB_PORT=/DB_PORT=/' "$ENV_FILE"
    sed -i 's/^# DB_DATABASE=/DB_DATABASE=/' "$ENV_FILE"
    sed -i 's/^# DB_USERNAME=/DB_USERNAME=/' "$ENV_FILE"
    sed -i 's/^# DB_PASSWORD=/DB_PASSWORD=/' "$ENV_FILE"

    sed -i -r "s|^DB_CONNECTION=.*|DB_CONNECTION={!! $database->type !!}|" "$ENV_FILE"
    sed -i -r "s|^DB_HOST=.*|DB_HOST={!! $database->host !!}|" "$ENV_FILE"
    sed -i -r "s|^DB_PORT=.*|DB_PORT={!! $database->port !!}|" "$ENV_FILE"
    sed -i -r "s|^DB_DATABASE=.*|DB_DATABASE={!! $database->name !!}|" "$ENV_FILE"
    sed -i -r "s|^DB_USERNAME=.*|DB_USERNAME={!! $database->username !!}|" "$ENV_FILE"
    sed -i -r "s|^DB_PASSWORD=.*|DB_PASSWORD=\"$SAFE_DB_PASSWORD\"|" "$ENV_FILE"
@endif

    if grep -q "APP_KEY=$" "$ENV_FILE" || grep -q "APP_KEY=\"\"" "$ENV_FILE"; then
        echo -e '\e[32m=> Generating APP_KEY\e[0m'
        APP_KEY="base64:$(openssl rand -base64 32)"
        SAFE_APP_KEY=$(printf '%s' "$APP_KEY" | sed 's/[&/\]/\\&/g')
        sed -i -r "s|APP_KEY=.*|APP_KEY=$SAFE_APP_KEY|" "$ENV_FILE"
    fi
fi

if [ -f "$ENV_FILE" ]; then
    echo -e '\e[32m=> Creating shared .env symlink\e[0m'
    cp "$ENV_FILE" "$SHARED_ENV"
    rm -f "$ENV_FILE"
    ln -sf "$SHARED_ENV" "$ENV_FILE"
    chmod 644 "$SHARED_ENV"
fi

echo -e '\e[32m=> Environment file created successfully!\e[0m'
