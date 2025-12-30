#
# Zero-Downtime Deployment for Symfony
#

@include('provisioning.scripts.deploy.partials.clone-repository')

#
# Symfony Environment Setup
#

echo "Linking shared .env.local..."
rm -f "$NIP_NEW_RELEASE_PATH/.env.local"
ln -sfn "$NIP_SITE_ROOT/.env" "$NIP_NEW_RELEASE_PATH/.env.local"

echo "Linking shared var directory..."
rm -rf "$NIP_NEW_RELEASE_PATH/var"
ln -sfn "$NIP_SITE_ROOT/storage" "$NIP_NEW_RELEASE_PATH/var"

#
# Install Dependencies
#

echo "Installing Composer dependencies..."
$NIP_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

#
# Symfony Optimizations
#

echo "Running Symfony optimizations..."
$NIP_PHP bin/console cache:clear --env=prod
$NIP_PHP bin/console doctrine:migrations:migrate --no-interaction

@include('provisioning.scripts.deploy.partials.build-frontend')

@include('provisioning.scripts.deploy.partials.activate-release')

echo "Symfony deployment completed!"
