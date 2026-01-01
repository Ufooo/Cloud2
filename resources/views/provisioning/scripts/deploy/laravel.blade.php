$NIP_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $NIP_PHP_FPM reload ) 9>/tmp/fpmlock-$(whoami)

if [ -f artisan ]; then
    $NIP_PHP artisan optimize
    $NIP_PHP artisan storage:link
    $NIP_PHP artisan migrate --force
fi

if [ -f package.json ]; then
    npm ci || npm install
    npm run build
fi
