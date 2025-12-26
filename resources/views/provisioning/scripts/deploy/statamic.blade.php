cd $NIP_SITE_PATH

git pull origin $NIP_SITE_BRANCH

$NIP_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

$NIP_PHP artisan optimize
$NIP_PHP artisan storage:link
$NIP_PHP artisan migrate --force
$NIP_PHP artisan statamic:stache:warm

npm ci || npm install && npm run build
