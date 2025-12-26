cd $NIP_SITE_PATH

git pull origin $NIP_SITE_BRANCH

$NIP_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

$NIP_PHP bin/console cache:clear --env=prod
$NIP_PHP bin/console doctrine:migrations:migrate --no-interaction

npm ci || npm install && npm run build
