$CREATE_RELEASE()

cd $NIP_RELEASE_DIRECTORY

$NIP_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f artisan ]; then
    $NIP_PHP artisan optimize
    $NIP_PHP artisan storage:link
    $NIP_PHP artisan migrate --force
    $NIP_PHP artisan statamic:stache:warm
fi

if [ -f package.json ]; then
    npm ci || npm install
    npm run build
fi

$ACTIVATE_RELEASE()

$RESTART_FPM()
