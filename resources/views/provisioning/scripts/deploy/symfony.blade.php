$NIP_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $NIP_PHP_FPM reload ) 9>/tmp/fpmlock-$(whoami)

$NIP_PHP bin/console cache:clear --env=prod
$NIP_PHP bin/console doctrine:migrations:migrate --no-interaction

if [ -f package.json ]; then
    npm ci || npm install
    npm run build
fi
