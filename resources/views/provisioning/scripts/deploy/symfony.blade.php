$CREATE_RELEASE()

cd $NIP_RELEASE_DIRECTORY

$NIP_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Clear and warm up cache
$NIP_PHP bin/console cache:clear --env=prod
$NIP_PHP bin/console cache:warmup --env=prod

# Run migrations if doctrine is available
if $NIP_PHP bin/console list doctrine:migrations 2>/dev/null; then
    $NIP_PHP bin/console doctrine:migrations:migrate --no-interaction
fi

if [ -f package.json ]; then
    npm ci || npm install
    npm run build
fi

$ACTIVATE_RELEASE()

$RESTART_FPM()
